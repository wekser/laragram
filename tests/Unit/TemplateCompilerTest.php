<?php
declare(strict_types=1);

/*
 * This file is part of Laragram.
 *
 * (c) Sergey Lapin <me@wekser.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Wekser\Laragram\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use Wekser\Laragram\BotResponse;
use Wekser\Laragram\Exceptions\ViewInvalidException;
use Wekser\Laragram\Tests\TestCase;
use Wekser\Laragram\View\ComponentContext;

/**
 * The view template language: {{-- --}} comments, the two interpolation forms,
 * and the guards around a component that renders to nothing.
 *
 * Comments exist because the language previously had no comment syntax at all —
 * a scaffolded view carried its guidance in a PHP block comment, and a compiler
 * pass that rewrote {{ }} inside it emitted the whole header to the user.
 */
#[CoversClass(BotResponse::class)]
class TemplateCompilerTest extends TestCase
{
    /** @var list<string> View directories created by the running test. */
    private array $created = [];

    private string $viewsRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->viewsRoot = resource_path(config('laragram.paths.views'));
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->created) as $dir) {
            array_map('unlink', glob($dir . '/*') ?: []);
            @rmdir($dir);
        }

        // Compiled templates are cached by path+mtime, and these cases reuse one
        // fixture path with differing contents within the same second.
        BotResponse::flushTemplateCache();
        ComponentContext::reset();

        parent::tearDown();
    }

    public function test_comment_block_is_stripped_entirely(): void
    {
        $text = $this->renderText("{{-- a note to the view author --}}\nHello!");

        $this->assertSame('Hello!', $text);
    }

    /**
     * The comment pass has to run BEFORE the two interpolation passes. If it did
     * not, the {{ }} inside a comment would compile to real PHP first, and the
     * '?>' the compiler injects would end up terminating PHP mode — spilling the
     * rest of the comment into the message and executing the example code.
     */
    public function test_interpolation_inside_a_comment_is_never_compiled_or_emitted(): void
    {
        $text = $this->renderText(<<<'TPL'
        {{--
            {{ $secret }}   — escaped output
            {!! $secret !!} — raw output
            a literal ?> and a stray <?php in the prose
        --}}
        Hello!
        TPL, ['secret' => 'LEAKED']);

        $this->assertSame('Hello!', $text);
        $this->assertStringNotContainsString('LEAKED', $text);
    }

    public function test_multiple_comments_coexist_with_real_interpolation(): void
    {
        $text = $this->renderText(
            "{{-- header --}}Hi {{ \$name }}!{{-- trailer --}} Bye {!! \$sign !!}",
            ['name' => 'Ada', 'sign' => '<b>!</b>']
        );

        $this->assertSame('Hi Ada! Bye <b>!</b>', $text);
    }

    public function test_escaped_and_raw_forms_still_differ(): void
    {
        $text = $this->renderText(
            "{{ \$value }}|{!! \$value !!}",
            ['value' => '<b>x</b>']
        );

        $this->assertSame('&lt;b&gt;x&lt;/b&gt;|<b>x</b>', $text);
    }

    /**
     * media.php and the single-media components throw on an empty result, and so
     * does text.php — the one component every view has. Before that, an empty
     * render silently became text => '' and Telegram answered
     * 400 "message text is empty" with nothing to point at.
     *
     * The keyboard components are the exception: see the conditional-keyboard
     * cases below.
     */
    public function test_empty_text_component_throws(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('text.php produced no text');

        $this->renderText("{{-- nothing but a comment --}}\n");
    }

    public function test_empty_media_component_throws(): void
    {
        $dir = $this->makeView('compiler_media', [
            'text.php'  => 'Caption',
            'photo.php' => "{{ \$file_id ?? '' }}",
        ]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('photo.php produced no file_id or URL');

        (new BotResponse(config('laragram.paths.views')))->view(basename($dir));
    }

    /**
     * A keyboard component is ordinary PHP, so buttons routinely sit behind a
     * condition. When none fires the component renders empty — which used to
     * throw, ExceptionHandler swallowed it, and the user got no message at all
     * even though the text had rendered fine.
     */
    public function test_empty_inline_keyboard_component_sends_message_without_markup(): void
    {
        $dir = $this->makeView('compiler_empty_inline', [
            'text.php'            => 'Hi',
            'inline_keyboard.php' => "<?php\nif (false) {\n    button('Panel', 'admin');\n}",
        ]);

        $contents = (new BotResponse(config('laragram.paths.views')))->view(basename($dir))->contents;

        $this->assertSame('sendMessage', $contents['method']);
        $this->assertSame('Hi', $contents['text']);
        $this->assertArrayNotHasKey('reply_markup', $contents);
    }

    /** Options without buttons are empty too — resize() alone is not a keyboard. */
    public function test_empty_reply_keyboard_component_sends_message_without_markup(): void
    {
        $dir = $this->makeView('compiler_empty_reply', [
            'text.php'           => 'Hi',
            'reply_keyboard.php' => "<?php\nresize();\n\nif (false) {\n    reply('Order');\n}",
        ]);

        $contents = (new BotResponse(config('laragram.paths.views')))->view(basename($dir))->contents;

        $this->assertArrayNotHasKey('reply_markup', $contents);
    }

    /**
     * An omitted reply_markup leaves the keyboard already on the user's screen in
     * place, so the else branch needs an explicit removal — which wins over any
     * button the same component added.
     */
    public function test_remove_keyboard_helper_emits_remove_markup(): void
    {
        $dir = $this->makeView('compiler_remove_reply', [
            'text.php'           => 'Hi',
            'reply_keyboard.php' => "<?php\nreply('Order');\nremove_keyboard();",
        ]);

        $contents = (new BotResponse(config('laragram.paths.views')))->view(basename($dir))->contents;

        $this->assertSame(['remove_keyboard' => true], $contents['reply_markup']);
    }

    /**
     * The guard the keyboard guards stood in for: a view that ends up with nothing
     * to send still fails loudly, instead of a Telegram 400.
     */
    public function test_view_with_only_an_empty_keyboard_throws(): void
    {
        $dir = $this->makeView('compiler_keyboard_only', [
            'inline_keyboard.php' => "<?php\nif (false) {\n    button('Panel', 'admin');\n}",
        ]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('produced no content');

        (new BotResponse(config('laragram.paths.views')))->view(basename($dir));
    }

    /**
     * A component file builds its payload through the global helpers and should
     * emit nothing. Stray bytes before its <?php used to be echoed into the
     * ambient output stream — i.e. the webhook's HTTP response body.
     */
    public function test_component_output_never_reaches_the_output_stream(): void
    {
        $dir = $this->makeView('compiler_component', [
            'text.php'            => 'Hi',
            'inline_keyboard.php' => "STRAY BYTES\n<?php\nbutton('Ok', 'ok');",
        ]);

        $level = ob_get_level();
        ob_start();

        $contents = (new BotResponse(config('laragram.paths.views')))->view(basename($dir))->contents;

        $ambient = (string) ob_get_clean();

        $this->assertSame($level, ob_get_level(), 'renderer left the buffer stack unbalanced');
        $this->assertSame('', $ambient);
        $this->assertNotEmpty($contents['reply_markup']['inline_keyboard']);
    }

    public function test_broken_component_throws_view_invalid_exception(): void
    {
        $dir = $this->makeView('compiler_broken', [
            'text.php'            => 'Hi',
            'inline_keyboard.php' => "<?php\nbutton('Ok', ;",
        ]);

        $this->expectException(ViewInvalidException::class);

        (new BotResponse(config('laragram.paths.views')))->view(basename($dir));
    }

    public function test_broken_template_throws_view_invalid_exception(): void
    {
        $this->expectException(ViewInvalidException::class);

        $this->renderText("{{ \$a ?? }}");
    }

    /** Write a single-file view, render it, and return the resulting text. */
    private function renderText(string $template, array $data = []): string
    {
        $dir = $this->makeView('compiler_text', ['text.php' => $template]);

        return (new BotResponse(config('laragram.paths.views')))->view(basename($dir), $data)->contents['text'];
    }

    /**
     * Create a view directory from a filename => contents map.
     *
     * @param array<string, string> $files
     */
    private function makeView(string $name, array $files): string
    {
        $dir = $this->viewsRoot . '/' . $name;

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->created[] = $dir;

        foreach ($files as $file => $contents) {
            file_put_contents($dir . '/' . $file, $contents);
        }

        return $dir;
    }
}
