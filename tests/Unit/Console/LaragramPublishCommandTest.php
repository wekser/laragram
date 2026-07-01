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

use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionMethod;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Wekser\Laragram\Console\LaragramPublishCommand;
use Wekser\Laragram\Tests\TestCase;

#[CoversClass(LaragramPublishCommand::class)]
class LaragramPublishCommandTest extends TestCase
{
    /** Throwaway subdirectory under routes/ so we never touch the real fixtures. */
    private string $dir = 'tmp_publish_test';

    protected function setUp(): void
    {
        parent::setUp();

        // Redirect the route/scenes output into the throwaway subdirectory.
        config([
            'laragram.paths.route'  => "{$this->dir}/routes",
            'laragram.paths.scenes' => "{$this->dir}/scenes",
        ]);

        File::deleteDirectory(base_path("routes/{$this->dir}"));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path("routes/{$this->dir}"));

        parent::tearDown();
    }

    /**
     * Invoke one of the command's protected publish methods in isolation, so the
     * test exercises only the route/scene file handling — not vendor:publish or
     * controller scaffolding, which would clobber the test fixtures.
     */
    private function invoke(string $method, bool $force = false): void
    {
        $command = new LaragramPublishCommand();
        $command->setLaravel($this->app);
        $command->setInput(new ArrayInput($force ? ['--force' => true] : [], $command->getDefinition()));
        $command->setOutput(new OutputStyle(new StringInput(''), new BufferedOutput()));

        (new ReflectionMethod($command, $method))->invoke($command);
    }

    private function routePath(): string
    {
        return base_path("routes/{$this->dir}/routes.php");
    }

    private function scenePath(): string
    {
        return base_path("routes/{$this->dir}/scenes.php");
    }

    /** Reproduce the post-install state: blank route + scenes files already present. */
    private function seedBlankInstallFiles(): void
    {
        $stubs = dirname(__DIR__, 3) . '/src/Console/stubs/routes';

        File::ensureDirectoryExists(base_path("routes/{$this->dir}"));
        File::copy("{$stubs}/routes.stub", $this->routePath());
        File::copy("{$stubs}/scenes_blank.stub", $this->scenePath());
    }

    public function test_creates_scenes_file_with_demo_scene_when_absent(): void
    {
        $this->invoke('createScenes');

        $this->assertFileExists($this->scenePath());
        $this->assertStringContainsString("BotScene::define('order')", File::get($this->scenePath()));
    }

    public function test_appends_demo_scene_to_existing_blank_file(): void
    {
        // Post-install the scenes file already exists (blank). The demo scene
        // must still be appended — otherwise the appended /order route would
        // enter a scene that is never defined.
        $this->seedBlankInstallFiles();

        $this->invoke('createScenes');

        $contents = File::get($this->scenePath());
        $this->assertStringContainsString("BotScene::define('order')", $contents);
        $this->assertStringContainsString('// --- Demo scene (added by laragram:publish) ---', $contents);
        // The original blank header is preserved (we appended, not overwrote).
        $this->assertStringContainsString('Laragram scenes (multi-step wizards)', $contents);
        // BotScene use-import from the blank file is not duplicated.
        $this->assertSame(1, substr_count($contents, 'use Wekser\Laragram\Facades\BotScene;'));
        // OrderController import from the demo scene was added.
        $this->assertStringContainsString('use App\Http\Controllers\Laragram\OrderController;', $contents);
    }

    public function test_scene_append_is_idempotent(): void
    {
        $this->seedBlankInstallFiles();

        $this->invoke('createScenes');
        $this->invoke('createScenes');

        $contents = File::get($this->scenePath());
        // Count a string unique to the real demo body — the blank install header
        // already carries a commented-out BotScene::define('order') example.
        $this->assertSame(1, substr_count($contents, "->onComplete([OrderController::class, 'place'])"));
        $this->assertSame(1, substr_count($contents, '// --- Demo scene (added by laragram:publish) ---'));
    }

    public function test_appends_demo_routes_to_existing_blank_file(): void
    {
        $this->seedBlankInstallFiles();

        $this->invoke('createRoute');

        $contents = File::get($this->routePath());
        $this->assertStringContainsString('// --- Demo routes (added by laragram:publish) ---', $contents);
        $this->assertStringContainsString("->contains('/order')", $contents);
        $this->assertStringContainsString('use App\Http\Controllers\Laragram\OrderController;', $contents);
    }

    public function test_route_append_is_idempotent(): void
    {
        $this->seedBlankInstallFiles();

        $this->invoke('createRoute');
        $this->invoke('createRoute');

        $contents = File::get($this->routePath());
        $this->assertSame(1, substr_count($contents, '// --- Demo routes (added by laragram:publish) ---'));
        $this->assertSame(1, substr_count($contents, "->contains('/start')"));
    }

    public function test_force_overwrites_scenes_file_with_demo(): void
    {
        $this->seedBlankInstallFiles();

        $this->invoke('createScenes', force: true);

        $contents = File::get($this->scenePath());
        $this->assertStringContainsString("BotScene::define('order')", $contents);
        // Overwrite replaces the file wholesale: no append marker, blank header gone.
        $this->assertStringNotContainsString('// --- Demo scene (added by laragram:publish) ---', $contents);
        $this->assertSame(1, substr_count($contents, '<?php'));
    }

    public function test_install_then_publish_yields_a_complete_order_demo(): void
    {
        // End-to-end intent of the fix: after install (blank files) + publish,
        // the /order route and the 'order' scene it enters both exist.
        $this->seedBlankInstallFiles();

        $this->invoke('createRoute');
        $this->invoke('createScenes');

        $this->assertStringContainsString(
            "->contains('/order')",
            File::get($this->routePath())
        );
        $this->assertStringContainsString(
            "BotScene::define('order')",
            File::get($this->scenePath())
        );
    }
}
