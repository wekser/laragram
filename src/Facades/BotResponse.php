<?php

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
 * This is the BotResponse facade class.
 *
 * @method static \Wekser\Laragram\BotResponse redirect(string $station)
 * @method static \Wekser\Laragram\BotResponse text(string $view)
 * @method static \Wekser\Laragram\BotResponse view(string $method, string $view, array $data = [])
 * @method static \Wekser\Laragram\BotResponse user(User $user)
 *
 * @see \Wekser\Laragram\BotResponse
 */
class BotResponse extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'laragram.response';
    }
}