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

use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\CoversNothing;
use Wekser\Laragram\BotResponse;
use Wekser\Laragram\Tests\TestCase;

/**
 * Regression guard for the demo formatting bug: the published demo fed lang
 * strings carrying <b>…</b> markup through BotResponse::text(), whose
 * whole-string escaping turned them into &lt;b&gt;… so Telegram displayed the
 * literal tags. Every formatted demo message must instead render through a view
 * (which emits the translation raw with parse_mode = HTML). This test renders
 * the *actual* published stub views with the *actual* demo lang strings and
 * asserts the bold markup survives unescaped.
 */
#[CoversNothing]
class DemoStubRenderingTest extends TestCase
{
    /** @var list<string> View directories copied into the fixture resources path. */
    private array $created = [];

    private string $stubDir;
    private string $viewsRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stubDir   = dirname(__DIR__, 3) . '/src/Console/stubs';
        $this->viewsRoot = resource_path(config('laragram.paths.views'));

        // Load the real demo translations (lang/en/laragram.stub) into the
        // translator under the 'laragram' group so __('laragram.*') resolves.
        $lines = require $this->stubDir . '/lang/en/laragram.stub';
        app('translator')->addLines($this->flatten($lines, 'laragram'), 'en');

        // Copy the published demo view stubs into the fixture views path.
        $this->publishView('views/click.stub', 'click/text.php');
        $this->publishView('views/order_size.stub', 'order/size/text.php');
        $this->publishView('views/order_address.stub', 'order/address/text.php');
        $this->publishView('views/order_placed.stub', 'order/placed/text.php');
    }

    protected function tearDown(): void
    {
        // Remove only the directories this test created, deepest first.
        foreach (array_reverse($this->created) as $dir) {
            array_map('unlink', glob($dir . '/*') ?: []);
            @rmdir($dir);
        }
        @rmdir($this->viewsRoot . '/order');

        BotResponse::flushTemplateCache();

        parent::tearDown();
    }

    public function test_order_size_prompt_renders_bold_raw(): void
    {
        $contents = $this->render('order.size');

        $this->assertSame('HTML', $contents['parse_mode']);
        $this->assertStringContainsString('<b>What size</b>', $contents['text']);
        $this->assertStringNotContainsString('&lt;b&gt;', $contents['text']);
    }

    public function test_order_address_prompt_renders_bold_raw_with_size(): void
    {
        $contents = $this->render('order.address', ['size' => 'Medium']);

        $this->assertSame('HTML', $contents['parse_mode']);
        $this->assertStringContainsString('<b>Medium</b>', $contents['text']);
        $this->assertStringContainsString('<b>delivery address</b>', $contents['text']);
        $this->assertStringNotContainsString('&lt;', $contents['text']);
    }

    public function test_order_placed_confirmation_renders_bold_raw(): void
    {
        $contents = $this->render('order.placed', ['size' => 'Large', 'address' => 'Main St 1']);

        $this->assertSame('HTML', $contents['parse_mode']);
        $this->assertStringContainsString('<b>Order confirmed!</b>', $contents['text']);
        $this->assertStringContainsString('Large', $contents['text']);
        $this->assertStringContainsString('Main St 1', $contents['text']);
        $this->assertStringNotContainsString('&lt;', $contents['text']);
    }

    public function test_callback_reply_renders_bold_raw(): void
    {
        $contents = $this->render('click', ['name' => 'Foo']);

        $this->assertSame('HTML', $contents['parse_mode']);
        $this->assertStringContainsString('<b>Foo</b>', $contents['text']);
        $this->assertStringNotContainsString('&lt;b&gt;', $contents['text']);
    }

    /** Render a demo view and return its assembled payload. */
    private function render(string $view, array $data = []): array
    {
        return (new BotResponse(config('laragram.paths.views')))->view($view, $data)->contents;
    }

    /** Copy a stub file to the given view path (dot-dir), tracking it for cleanup. */
    private function publishView(string $stub, string $target): void
    {
        $path = $this->viewsRoot . '/' . $target;
        $dir  = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->created[] = $dir;
        file_put_contents($path, file_get_contents($this->stubDir . '/' . $stub));
    }

    /** Flatten a nested lang array to dot keys under a group prefix. */
    private function flatten(array $lines, string $group): array
    {
        $flat = [];

        foreach (Arr::dot($lines) as $key => $value) {
            $flat["{$group}.{$key}"] = $value;
        }

        return $flat;
    }
}
