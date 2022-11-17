<?php

/*
 * This file is part of Laragram.
 *
 * (c) Sergey Lapin <me@wekser.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication configuration
    |--------------------------------------------------------------------------
    */

    'auth' => [
        'driver' => 'database',
        'model' => \Wekser\Laragram\Models\User::class
    ],

    /*
    |--------------------------------------------------------------------------
    | Bot configuration
    |--------------------------------------------------------------------------
    */

    'bot' => [
        'languages' => ['en']
    ],

    /*
    |--------------------------------------------------------------------------
    | Main configuration
    |--------------------------------------------------------------------------
    */

    'env' => [
        'token' => env('LARAGRAM_BOT_TOKEN'),
        'prefix' => env('LARAGRAM_WEBHOOK_PREFIX', 'laragram'),
        'secret' => env('LARAGRAM_WEBHOOK_SECRET', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Paths configuration
    |--------------------------------------------------------------------------
    */

    'paths' => [
        'route' => 'laragram',
        'views' => 'laragram'
    ],

];