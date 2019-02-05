<?php

/*
 * This file is part of Laragram.
 *
 * (c) Sergey Lapin <hello@wekser.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Wekser\Laragram\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * This is the BotClient facade class.
 *
 * @method static string getToken()
 * @method static string|null getPrefix()
 * @method static string|null getSecret()
 * @method static array request(string $method, array|null $parameters = [])
 */
class BotClient extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'laragram.client';
    }
}