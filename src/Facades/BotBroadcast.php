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
 * Facade for mass messaging.
 *
 *   use Wekser\Laragram\Facades\BotBroadcast;
 *
 *   // Raw text to every active user
 *   BotBroadcast::text('We are back online!')->send();
 *
 *   // A rendered view to admins only (rendered per recipient, in their language)
 *   BotBroadcast::view('news.release', ['version' => '2.0'])
 *       ->role('admin')
 *       ->send();
 *
 * Delivery is queued when laragram.queue.enabled is true and synchronous
 * otherwise. Requires the "database" auth driver (there are no persisted users
 * under "array").
 *
 * @method static \Wekser\Laragram\Broadcasting\PendingBroadcast view(string $view, array $data = [], ?string $format = 'HTML')
 * @method static \Wekser\Laragram\Broadcasting\PendingBroadcast text(string $text, ?string $format = 'HTML')
 *
 * @see \Wekser\Laragram\Broadcasting\Broadcaster
 */
class BotBroadcast extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'laragram.broadcast';
    }
}
