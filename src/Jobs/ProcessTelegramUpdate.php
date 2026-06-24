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

namespace Wekser\Laragram\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Wekser\Laragram\BotAuth;
use Wekser\Laragram\Exceptions\ExceptionHandler;
use Wekser\Laragram\Laragram;

/**
 * Processes a single Telegram update on a queue worker.
 *
 * Dispatched by Laragram::index() when laragram.queue.enabled is true, so the
 * webhook can return 'OK' 200 instantly while the router and the outbound Bot
 * API calls run here — off the web request path.
 *
 * There is no live HTTP request inside a worker, so handle() rebuilds an
 * Illuminate Request from the stored update payload, rebinds it as the current
 * request, and forgets the request-scoped Laragram singletons (laragram.auth,
 * laragram.response) so they re-resolve against this update rather than reusing
 * a previous one cached by a long-running worker process.
 *
 * Implements ShouldBeEncrypted: the stored payload carries user PII (names,
 * username, message text), so Laravel encrypts it transparently with the app
 * key before it sits in Redis/the database queue, and decrypts on the worker.
 * Encryption is end-to-end within the app — no listener or consumer of the raw
 * queue store can read the update.
 */
class ProcessTelegramUpdate implements ShouldQueue, ShouldBeEncrypted
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * Retry indefinitely.
     *
     * Both job middleware (WithoutOverlapping and RateLimited) handle back-pressure
     * by releasing the job back onto the queue rather than throwing. Under the
     * default `queue:work --tries=1`, a released job would exceed its attempt limit
     * on the very next reservation and be marked failed — silently dropping the
     * user's update under exactly the burst load this feature exists to absorb.
     * tries=0 (unlimited) lets a throttled/overlapping update wait its turn instead.
     *
     * This is safe from poison-job loops because handle() catches every Throwable
     * (delivery errors are logged, never rethrown), so the only thing that ever
     * re-queues this job is benign, self-clearing back-pressure: the overlap lock
     * expires after 30s and the rate limiter drains as load falls.
     *
     * @var int
     */
    public int $tries = 0;

    /**
     * Hard cap on how long one update may take on a worker.
     *
     * BotClient already caps each outbound call (CURLOPT_TIMEOUT = 30s), so a
     * single send can't hang a worker; this bounds a whole multi-message batch
     * as a safety net and releases the per-sender overlap lock promptly.
     *
     * @var int
     */
    public int $timeout = 60;

    /**
     * @param array<string, mixed> $update The raw Telegram update payload.
     */
    public function __construct(public readonly array $update) {}

    /**
     * The job middleware.
     *
     * - WithoutOverlapping serializes processing per sender, so two updates from
     *   the same user never run concurrently — avoiding races on the user's
     *   session. Note this is mutual exclusion, not strict FIFO: with multiple
     *   workers per queue, two updates from one user can still be processed out
     *   of order. Run a single worker per queue when strict station ordering
     *   matters. A blocked update is released back onto the queue and retried.
     * - RateLimited caps the global execution rate (see the 'laragram' limiter)
     *   to stay under Telegram's outbound message limit.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        // Serialize per sender. When an update has no identifiable sender (e.g.
        // some channel posts), fall back to its unique update_id so unrelated
        // senderless updates each get their own lock instead of all colliding
        // on one global key and being processed strictly one at a time.
        $key = BotAuth::findFromInPayload($this->update)['id']
            ?? $this->update['update_id']
            ?? uniqid('laragram_', true);

        return [
            (new WithoutOverlapping((string) $key))->releaseAfter(5)->expireAfter(30),
            new RateLimited('laragram'),
        ];
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        $request = Request::create(
            '/',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode($this->update),
        );

        app()->instance('request', $request);
        app()->forgetInstance('laragram.auth');
        app()->forgetInstance('laragram.response');

        try {
            (new Laragram())->handle($request);
        } catch (\Throwable $exception) {
            ExceptionHandler::handle($exception);
        }
    }
}
