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
use Wekser\Laragram\Routing\Router;
use Wekser\Laragram\Scene\SceneManager;
use Wekser\Laragram\Scene\SceneRegistry;
use Wekser\Laragram\Testing\BotUpdateFactory;
use Wekser\Laragram\Testing\InteractsWithBot;
use Wekser\Laragram\Tests\TestCase;
use Wekser\Laragram\View\ComponentContext;

/**
 * End-to-end test of scene ENTRY through the full pipeline: a normal route whose
 * handler returns a SceneTransition is routed to SceneManager::start(), and the
 * first step's question is delivered by the real dispatcher.
 */
#[CoversClass(SceneManager::class)]
#[CoversClass(Router::class)]
class SceneFlowTest extends TestCase
{
    use InteractsWithBot;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'laragram.paths.route'  => 'scene_routes',
            'laragram.paths.scenes' => 'laragram/scenes',
        ]);

        Router::flushCache();
        SceneRegistry::flushCache();
        BotUpdateFactory::reset();
    }

    protected function tearDown(): void
    {
        Router::flushCache();
        SceneRegistry::flushCache();
        BotUpdateFactory::reset();
        ComponentContext::reset();

        parent::tearDown();
    }

    public function test_route_handler_enters_scene_and_sends_first_question(): void
    {
        $this->botReceives(BotUpdateFactory::message('/order'));

        $this->assertBotRepliedWith('sendMessage');
        $this->assertBotRepliedText('Choose a size');
        $this->assertInScene('order');
        $this->assertSceneStep('size');
    }
}
