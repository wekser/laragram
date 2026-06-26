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

namespace Wekser\Laragram\Scene;

/**
 * Marker returned by BotScene::enter(). When a route handler returns one of
 * these, the Router hands off to SceneManager::start() to begin the scene.
 *
 *   public function order(BotRequest $r)
 *   {
 *       return BotScene::enter('order');
 *   }
 */
final class SceneTransition
{
    /**
     * @param string               $name        Scene name to enter.
     * @param array<string, mixed>  $initialData Pre-seeded collected data.
     */
    public function __construct(
        public readonly string $name,
        public readonly array  $initialData = [],
    ) {}
}
