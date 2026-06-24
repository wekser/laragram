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
    | Telegram connection
    |--------------------------------------------------------------------------
    |
    | Your bot token from @BotFather, the webhook URL prefix, and the optional
    | secret token used to authenticate incoming webhook requests.
    |
    */

    'telegram' => [
        'token'  => env('LARAGRAM_BOT_TOKEN'),
        'prefix' => env('LARAGRAM_WEBHOOK_PREFIX', 'laragram'),
        'secret' => env('LARAGRAM_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    |
    | Driver: "database" persists every sender to laragram_users; "array" keeps
    | an in-memory user and performs no DB I/O (useful for testing / stateless
    | bots). Any other value throws InvalidArgumentException at boot.
    |
    */

    'auth' => [
        'driver'  => env('LARAGRAM_AUTH_DRIVER', 'database'),
        'session' => [
            'lifetime' => 10080, // minutes (default: 7 days)
            'model'    => \Wekser\Laragram\Models\Session::class,
            'table'    => 'laragram_sessions',
        ],
        'user' => [
            'model' => \Wekser\Laragram\Models\User::class,
            'table' => 'laragram_users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Paths
    |--------------------------------------------------------------------------
    |
    | Filenames (without extension) resolved relative to routes/ and
    | resources/ respectively. Do not include path separators.
    |
    */

    'paths' => [
        'route' => 'laragram',
        'views' => 'laragram',
    ],

    /*
    |--------------------------------------------------------------------------
    | Bot
    |--------------------------------------------------------------------------
    |
    | Languages supported by the bot. The first entry is used as the fallback
    | locale when no match is found for the user's language.
    |
    */

    'bot' => [
        'languages' => ['en'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | When enabled, an incoming webhook update is pushed onto a queue and the
    | webhook answers "OK" 200 immediately, instead of running the router and
    | the outbound Telegram calls inside the web request. This keeps the webhook
    | fast and frees the web worker — strongly recommended once you expect bursts
    | of concurrent users (the outbound Bot API call is a network round-trip that
    | would otherwise block a PHP-FPM worker for its whole duration).
    |
    | "connection" is the queue connection (null = your default connection); use
    | a Redis-backed connection in production. "queue" is the queue name workers
    | should consume. Run workers with: php artisan queue:work --queue=<name>.
    |
    | Verification, bot rejection, deduplication and rate limiting still run
    | synchronously in the webhook (they are cheap and must gate what is queued);
    | only the routing + delivery are deferred to the worker.
    |
    | "rate_limit" caps how many update jobs may run per second across all
    | workers, keeping outbound traffic under Telegram's global ~30 msg/sec limit
    | (leave some headroom). Enforced by the RateLimited job middleware via a
    | named limiter; requires a shared cache store (Redis) to be accurate across
    | multiple worker processes.
    |
    */

    'queue' => [
        'enabled'    => env('LARAGRAM_QUEUE_ENABLED', false),
        'connection' => env('LARAGRAM_QUEUE_CONNECTION'),
        'queue'      => env('LARAGRAM_QUEUE_NAME', 'default'),
        'rate_limit' => env('LARAGRAM_QUEUE_RATE_LIMIT', 25),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate limiting
    |--------------------------------------------------------------------------
    |
    | Maximum number of requests allowed per window. The window length is
    | controlled by decay_seconds. Keyed per user ID (or IP as fallback).
    |
    */

    'rate' => [
        'max_attempts'  => env('LARAGRAM_RATE_MAX_ATTEMPTS', 60),
        'decay_seconds' => env('LARAGRAM_RATE_DECAY_SECONDS', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    |
    | Set verify_secret to false only in local/testing environments where you
    | do not want to validate the X-Telegram-Bot-Api-Secret-Token header.
    |
    */

    'security' => [
        'verify_secret' => env('LARAGRAM_VERIFY_SECRET', true),
    ],

];
