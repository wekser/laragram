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

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\CoversClass;
use Wekser\Laragram\Console\MakeSceneCommand;
use Wekser\Laragram\Scene\SceneRegistry;
use Wekser\Laragram\Tests\TestCase;

#[CoversClass(MakeSceneCommand::class)]
class MakeSceneCommandTest extends TestCase
{
    private string $sceneFile = 'tmp_scenes_test';

    private function path(): string
    {
        return base_path("routes/{$this->sceneFile}.php");
    }

    protected function setUp(): void
    {
        parent::setUp();

        config(['laragram.paths.scenes' => $this->sceneFile]);
        File::delete($this->path());
        SceneRegistry::flushCache();
    }

    protected function tearDown(): void
    {
        File::delete($this->path());
        SceneRegistry::flushCache();

        parent::tearDown();
    }

    public function test_creates_scenes_file_with_header_and_block(): void
    {
        $this->artisan('laragram:make:scene', ['name' => 'demo', '--steps' => 'size,color'])
            ->assertSuccessful();

        $this->assertFileExists($this->path());

        $contents = File::get($this->path());
        $this->assertStringContainsString('use Wekser\Laragram\Facades\BotScene;', $contents);
        $this->assertStringContainsString("BotScene::define('demo')", $contents);
        $this->assertStringContainsString("->step('size')", $contents);
        $this->assertStringContainsString("->step('color')", $contents);
        $this->assertStringContainsString('->onComplete(', $contents);
    }

    public function test_generated_file_is_loadable_by_the_registry(): void
    {
        $this->artisan('laragram:make:scene', ['name' => 'demo'])->assertSuccessful();

        SceneRegistry::flushCache();
        $scene = (new SceneRegistry())->get('demo');

        $this->assertNotNull($scene);
        $this->assertSame('step', $scene->firstStep()->key());
    }

    public function test_appends_second_scene_to_existing_file(): void
    {
        $this->artisan('laragram:make:scene', ['name' => 'first'])->assertSuccessful();
        $this->artisan('laragram:make:scene', ['name' => 'second'])->assertSuccessful();

        $contents = File::get($this->path());
        $this->assertStringContainsString("BotScene::define('first')", $contents);
        $this->assertStringContainsString("BotScene::define('second')", $contents);
        // The header must be written only once.
        $this->assertSame(1, substr_count($contents, '<?php'));
    }

    public function test_rejects_duplicate_scene_name(): void
    {
        $this->artisan('laragram:make:scene', ['name' => 'dupe'])->assertSuccessful();
        $this->artisan('laragram:make:scene', ['name' => 'dupe'])->assertFailed();
    }

    public function test_rejects_invalid_scene_name(): void
    {
        $this->artisan('laragram:make:scene', ['name' => 'bad-name!'])->assertFailed();

        $this->assertFileDoesNotExist($this->path());
    }
}
