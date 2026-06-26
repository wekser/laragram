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
use Wekser\Laragram\Console\SceneListCommand;
use Wekser\Laragram\Scene\SceneRegistry;
use Wekser\Laragram\Tests\TestCase;

#[CoversClass(SceneListCommand::class)]
class SceneListCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        SceneRegistry::flushCache();

        parent::tearDown();
    }

    public function test_lists_registered_scenes(): void
    {
        config(['laragram.paths.scenes' => 'laragram/scenes']);
        SceneRegistry::flushCache();

        $this->artisan('laragram:scene:list')
            ->assertSuccessful()
            ->expectsOutputToContain('order')
            ->expectsOutputToContain('signup');
    }

    public function test_reports_when_no_scenes_are_registered(): void
    {
        config(['laragram.paths.scenes' => 'does_not_exist']);
        SceneRegistry::flushCache();

        $this->artisan('laragram:scene:list')
            ->assertSuccessful()
            ->expectsOutput('No scenes registered.');
    }
}
