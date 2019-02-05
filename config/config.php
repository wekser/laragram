<?php

/*
 * This file is part of Laragram.
 *
 * (c) Sergey Lapin <hello@wekser.com>
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
        'languages' => ['en'],
        'secure_payload' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Main bot configuration
    |--------------------------------------------------------------------------
    */

    'bot' => [
        'token' => env('LARAGRAM_BOT_TOKEN'),
        'prefix' => env('LARAGRAM_WEBHOOK_PREFIX', 'bot'),
        'secret' => env('LARAGRAM_WEBHOOK_SECRET'),
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