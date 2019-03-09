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
 * This is the BotAuth facade class.
 *
 * @method static \Wekser\Laragram\Models\User|object user()
 *
 * @see \Wekser\Laragram\Models\User
 */
class BotAuth extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'laragram.auth';
    }
}