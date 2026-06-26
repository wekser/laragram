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

namespace Wekser\Laragram\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for the scene (wizard) system.
 *
 * Define scenes inside routes/laragram/scenes.php:
 *
 *   use Wekser\Laragram\Facades\BotScene;
 *
 *   BotScene::define('order')
 *       ->step('size')->ask('order.size')->rules(['required', 'in:S,M,L'])
 *       ->step('address')->ask(fn ($ctx) => BotResponse::text('Address?'))->rules(['min:5'])
 *       ->onComplete([OrderController::class, 'place']);
 *
 * Enter a scene from a route handler:
 *
 *   public function order(BotRequest $r)
 *   {
 *       return BotScene::enter('order');
 *   }
 *
 * @method static \Wekser\Laragram\Scene\Scene define(string $name)
 * @method static \Wekser\Laragram\Scene\SceneTransition enter(string $name, array $data = [])
 * @method static bool isSceneStation(string $station)
 *
 * @see \Wekser\Laragram\Scene\SceneManager
 */
class BotScene extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'laragram.scene';
    }
}
