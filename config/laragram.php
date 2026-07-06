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

        // The bot's @username (without the leading @). Used to strip the
        // "@botusername" suffix Telegram appends to commands in group chats
        // ("/start@MyBot"). When empty, any "@suffix" on a command is stripped.
        'username' => env('LARAGRAM_BOT_USERNAME'),
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
    | Filenames (without extension) resolved relative to routes/ and resources/.
    | "route" and "scenes" may include a subdirectory (the default keeps both bot
    | files together in routes/laragram/); ".." and absolute paths are rejected.
    |
    */

    'paths' => [
        'route'  => 'laragram/routes',   // routes/laragram/routes.php
        'views'  => 'laragram',          // resources/laragram/
        'scenes' => 'laragram/scenes',   // routes/laragram/scenes.php
    ],

    /*
    |--------------------------------------------------------------------------
    | Scenes (wizards)
    |--------------------------------------------------------------------------
    |
    | Scenes are multi-step conversation flows defined in routes/laragram/scenes.php
    | (see paths.scenes). "cancel_commands" lists the commands that abort any scene
    | at any step unless a scene overrides them with ->cancelOn(). Scenes require
    | the "database" auth driver to persist step state across updates.
    |
    | "global_commands" lists commands that escape ANY scene at any step and are
    | then handled by the normal router (e.g. ['/start']) — empty means scenes are
    | only left via their own steps or a cancel command.
    |
    */

    'scenes' => [
        'cancel_commands' => ['/cancel'],
        'global_commands' => [],
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
    | Broadcast (mass messaging)
    |--------------------------------------------------------------------------
    |
    | Settings for laragram:broadcast / the BotBroadcast facade, which push a
    | single message (a rendered view or raw text) to many users at once.
    |
    | "chunk_size" controls how many recipients are loaded into memory at a time
    | while iterating the user base (chunkById).
    |
    | When queue.enabled is true the broadcast dispatches one queued job per
    | recipient, throttled by the same "laragram" limiter as incoming updates
    | (see queue.rate_limit). When it is false the command sends synchronously
    | and "sync_delay_ms" is the pause inserted between each send to stay under
    | Telegram's ~30 msg/sec outbound limit (40ms ≈ 25/sec).
    |
    | "deactivate_unreachable" marks a user inactive (User::deactivate()) the
    | first time a send to them fails with an unreachable condition (blocked,
    | deactivated, chat gone), so future broadcasts skip them. This applies to
    | every send, not just broadcasts, and requires the "database" auth driver.
    |
    */

    'broadcast' => [
        'chunk_size'             => env('LARAGRAM_BROADCAST_CHUNK_SIZE', 500),
        'sync_delay_ms'          => env('LARAGRAM_BROADCAST_SYNC_DELAY_MS', 40),
        'deactivate_unreachable' => env('LARAGRAM_BROADCAST_DEACTIVATE_UNREACHABLE', true),

        // Max recipients the admin panel will send to synchronously (queue off).
        // Above this the panel refuses to block the web request and asks you to
        // enable the queue or use the laragram:broadcast command instead.
        'web_sync_limit'         => env('LARAGRAM_BROADCAST_WEB_SYNC_LIMIT', 200),
    ],

    /*
    |--------------------------------------------------------------------------
    | Downloads (incoming files)
    |--------------------------------------------------------------------------
    |
    | Settings for the MediaDownloader service (alias laragram.downloader) /
    | BotRequest::file(), which fetch a file a user sent to the bot.
    |
    | "disk" is the default Laravel filesystem disk that ->save() writes to.
    | "max_size" caps how many bytes will be downloaded (Telegram's getFile
    | endpoint itself serves files up to 20 MB); 0 disables the check.
    |
    */

    'downloads' => [
        'disk'     => env('LARAGRAM_DOWNLOADS_DISK', 'local'),
        'max_size' => env('LARAGRAM_DOWNLOADS_MAX_SIZE', 20 * 1024 * 1024),
        'timeout'  => env('LARAGRAM_DOWNLOADS_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payments
    |--------------------------------------------------------------------------
    |
    | Defaults for fiat invoices (Telegram Payments 2.0) built with the Invoice
    | builder / BotResponse::invoice(). "provider_token" is the payment provider
    | token from @BotFather; "currency" is the fallback ISO 4217 code when an
    | invoice does not set one explicitly.
    |
    | Telegram Stars invoices (Invoice::stars()) ignore both — they use the
    | "XTR" currency and never require a provider token.
    |
    */

    'payments' => [
        'provider_token' => env('LARAGRAM_PAYMENT_PROVIDER_TOKEN', ''),
        'currency'       => env('LARAGRAM_PAYMENT_CURRENCY', 'USD'),

        // Persist every successful payment to the payments table (idempotent).
        // Requires the "database" driver and the published payments migration.
        // The PaymentReceived event fires regardless of this flag.
        'store' => env('LARAGRAM_PAYMENTS_STORE', false),
        'table' => 'laragram_payments',
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
    | Admin panel
    |--------------------------------------------------------------------------
    |
    | A bundled, server-rendered dashboard for the bot's user base: metrics,
    | users & roles, sessions, and a broadcast launcher. It requires the
    | "database" auth driver (there is nothing to show under "array").
    |
    | "path" is the URL prefix it mounts on (e.g. https://app.test/laragram/admin).
    | "middleware" is the middleware group applied to its routes ("web" gives you
    | sessions + CSRF for the forms).
    |
    | Access is protected by a login page backed by the laragram_admins table (the
    | "laragram_admin" session guard). Create accounts with:
    |
    |     php artisan laragram:admin:create
    |
    | As an escape hatch, if you define a "viewLaragram" Gate ability in a service
    | provider it overrides the login and decides access itself (e.g. to reuse your
    | host app's own web auth). "guard" / "model" / "table" let you rename the
    | pieces the login uses.
    |
    */

    'admin' => [
        'enabled'    => env('LARAGRAM_ADMIN_ENABLED', true),
        'path'       => env('LARAGRAM_ADMIN_PATH', 'laragram/admin'),
        'middleware' => ['web'],
        'guard'      => 'laragram_admin',
        'model'      => \Wekser\Laragram\Models\Admin::class,
        'table'      => 'laragram_admins',
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
