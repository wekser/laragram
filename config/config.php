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
        'languages' => ['en']
    ],

    /*
    |--------------------------------------------------------------------------
    | Main bot configuration
    |--------------------------------------------------------------------------
    */

    'bot' => [
        'token' => env('LARAGRAM_BOT_TOKEN'),
        'prefix' => env('LARAGRAM_WEBHOOK_PREFIX', 'bot'),
        'secret' => env('LARAGRAM_WEBHOOK_SECRET', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | View bot configuration
    |--------------------------------------------------------------------------
    */

    'view' => [
        'path' => 'bot'
    ]

];