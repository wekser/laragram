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

namespace Wekser\Laragram\Tests\Unit\Scene;

use PHPUnit\Framework\Attributes\CoversClass;
use Wekser\Laragram\Exceptions\NotFoundRouteFileException;
use Wekser\Laragram\Scene\Scene;
use Wekser\Laragram\Scene\SceneRegistry;
use Wekser\Laragram\Tests\TestCase;

#[CoversClass(SceneRegistry::class)]
#[CoversClass(Scene::class)]
class SceneRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['laragram.paths.scenes' => 'laragram/scenes']);
        SceneRegistry::flushCache();
    }

    protected function tearDown(): void
    {
        SceneRegistry::flushCache();

        parent::tearDown();
    }

    public function test_loads_scenes_from_the_configured_file(): void
    {
        $registry = new SceneRegistry();

        $this->assertTrue($registry->has('order'));
        $this->assertInstanceOf(Scene::class, $registry->get('order'));
    }

    public function test_get_returns_null_for_unknown_scene(): void
    {
        $this->assertNull((new SceneRegistry())->get('nope'));
    }

    public function test_all_returns_every_defined_scene(): void
    {
        $all = (new SceneRegistry())->all();

        $this->assertArrayHasKey('order', $all);
        $this->assertArrayHasKey('survey', $all);
    }

    public function test_scene_exposes_ordered_steps_and_navigation(): void
    {
        $scene = (new SceneRegistry())->get('order');

        $this->assertSame('size', $scene->firstStep()->key());
        $this->assertSame('color', $scene->nextStep('size')->key());
        $this->assertNull($scene->nextStep('color'));
        $this->assertSame(['/cancel'], $scene->cancelCommands());
    }

    public function test_missing_scenes_file_is_not_an_error(): void
    {
        config(['laragram.paths.scenes' => 'does_not_exist']);
        SceneRegistry::flushCache();

        $this->assertNull((new SceneRegistry())->get('order'));
    }

    public function test_subdirectory_scenes_file_name_is_allowed(): void
    {
        // The default 'laragram/scenes' lives in a subfolder; loading must work.
        config(['laragram.paths.scenes' => 'laragram/scenes']);
        SceneRegistry::flushCache();

        $this->assertTrue((new SceneRegistry())->has('order'));
    }

    public function test_invalid_scenes_file_name_throws(): void
    {
        // Path traversal is rejected (a subdirectory like 'a/b' is not).
        config(['laragram.paths.scenes' => '../evil']);
        SceneRegistry::flushCache();

        $this->expectException(NotFoundRouteFileException::class);

        (new SceneRegistry())->get('order');
    }
}
