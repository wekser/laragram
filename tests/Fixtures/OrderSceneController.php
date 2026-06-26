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

namespace Wekser\Laragram\Tests\Fixtures;

use Wekser\Laragram\Facades\BotResponse;
use Wekser\Laragram\Scene\SceneContext;
use Wekser\Laragram\Scene\SceneTransition;

/**
 * Fixture controller exercising both the scene-entry return value and the
 * [Controller, method] onComplete handler form.
 */
class OrderSceneController
{
    /**
     * Route handler that begins the 'order' scene.
     */
    public function start(): SceneTransition
    {
        // Constructed directly (not via BotScene::enter) so the wiring can be
        // tested under the array driver without the database-driver guard.
        return new SceneTransition('order');
    }

    /**
     * onComplete handler — receives every collected answer.
     */
    public function place(SceneContext $ctx): mixed
    {
        return BotResponse::text("Done {$ctx->get('size')}/{$ctx->get('color')}")
            ->redirect('home');
    }
}
