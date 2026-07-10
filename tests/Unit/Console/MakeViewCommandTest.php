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

namespace Wekser\Laragram\Tests\Unit\Console;

use PHPUnit\Framework\Attributes\CoversClass;
use Wekser\Laragram\BotResponse;
use Wekser\Laragram\Console\MakeViewCommand;
use Wekser\Laragram\Tests\TestCase;
use Wekser\Laragram\View\ComponentContext;

/**
 * Scaffolds views with the *actual* shipped stubs and renders them. No component
 * file opens with a PHP tag, so a stub that assumed one would blow up here rather
 * than in a host app the first time the view is sent.
 */
#[CoversClass(MakeViewCommand::class)]
class MakeViewCommandTest extends TestCase
{
    /** @var list<string> View directories created by the command. */
    private array $created = [];

    private string $viewsRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->viewsRoot = resource_path(config('laragram.paths.views'));
    }

    protected function tearDown(): void
    {
        foreach ($this->created as $dir) {
            array_map('unlink', glob($dir . '/*') ?: []);
            @rmdir($dir);
        }

        ComponentContext::reset();
        BotResponse::flushTemplateCache();

        parent::tearDown();
    }

    public function test_scaffolded_text_view_renders_without_leaking_its_header_comment(): void
    {
        $contents = $this->scaffoldAndRender('scaffold_text', null, ['name' => 'Ann']);

        $this->assertSame('sendMessage', $contents['method']);
        $this->assertStringNotContainsString('Write your message text', $contents['text']);
        $this->assertStringNotContainsString('never sent', $contents['text']);
        $this->assertStringNotContainsString('{{', $contents['text']);
    }

    public function test_scaffolded_inline_keyboard_renders(): void
    {
        $contents = $this->scaffoldAndRender('scaffold_inline', 'inline_keyboard', ['name' => 'Ann']);
        $keyboard = $contents['reply_markup']['inline_keyboard'];

        $this->assertSame('action', $keyboard[0][0]['callback_data']);
        $this->assertArrayHasKey('url', $keyboard[1][0]);
    }

    public function test_scaffolded_reply_keyboard_renders(): void
    {
        $contents = $this->scaffoldAndRender('scaffold_reply', 'reply_keyboard', ['name' => 'Ann']);
        $markup   = $contents['reply_markup'];

        $this->assertTrue($markup['resize_keyboard']);
        $this->assertCount(2, $markup['keyboard'][0]);
    }

    public function test_scaffolded_media_group_renders(): void
    {
        $contents = $this->scaffoldAndRender('scaffold_media', 'media', [
            'name'     => 'Ann',
            'photo_id' => 'p1',
            'video_id' => 'v1',
        ]);

        $this->assertSame('sendMediaGroup', $contents['method']);
        $this->assertSame('p1', $contents['media'][0]['media']);
        $this->assertSame('v1', $contents['media'][1]['media']);
    }

    public function test_scaffolded_photo_view_renders_the_file_id_verbatim(): void
    {
        $contents = $this->scaffoldAndRender('scaffold_photo', 'photo', [
            'name'    => 'Ann',
            'file_id' => 'AgACAgIAAx0',
        ]);

        $this->assertSame('sendPhoto', $contents['method']);
        $this->assertSame('AgACAgIAAx0', $contents['photo']);
    }

    /** Run laragram:make:view, then render the resulting view directory. */
    private function scaffoldAndRender(string $name, ?string $with, array $data): array
    {
        $params = ['name' => $name];

        if ($with !== null) {
            $params['--with'] = $with;
        }

        $this->artisan('laragram:make:view', $params)->assertSuccessful();

        $this->created[] = $this->viewsRoot . '/' . $name;

        return (new BotResponse(config('laragram.paths.views')))->view($name, $data)->contents;
    }
}
