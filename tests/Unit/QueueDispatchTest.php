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

namespace Wekser\Laragram\Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\CoversClass;
use Wekser\Laragram\BotAuth;
use Wekser\Laragram\Jobs\ProcessTelegramUpdate;
use Wekser\Laragram\Laragram;
use Wekser\Laragram\Routing\Router;
use Wekser\Laragram\Testing\BotUpdateFactory;
use Wekser\Laragram\Tests\TestCase;

#[CoversClass(Laragram::class)]
#[CoversClass(ProcessTelegramUpdate::class)]
class QueueDispatchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        BotUpdateFactory::reset();
    }

    protected function tearDown(): void
    {
        Router::flushCache();
        parent::tearDown();
    }

    private function jsonRequest(array $update): Request
    {
        return Request::create(
            '/',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode($update),
        );
    }

    public function test_webhook_queues_update_when_queueing_enabled(): void
    {
        Bus::fake();
        $this->app['config']->set('laragram.queue.enabled', true);

        $update   = BotUpdateFactory::message('/start');
        $response = (new Laragram())->index($this->jsonRequest($update));

        // Webhook answers immediately, body carries no message.
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('OK', $response->getContent());

        Bus::assertDispatched(
            ProcessTelegramUpdate::class,
            static fn (ProcessTelegramUpdate $job): bool =>
                $job->update['update_id'] === $update['update_id']
        );
    }

    public function test_webhook_runs_synchronously_when_queueing_disabled(): void
    {
        Bus::fake();
        $this->app['config']->set('laragram.queue.enabled', false);

        // Fixture routes resolve to closures returning null, so the synchronous
        // path produces no outbound call — nothing hits the network here.
        $response = (new Laragram())->index($this->jsonRequest(BotUpdateFactory::message('/start')));

        $this->assertSame(200, $response->getStatusCode());
        Bus::assertNotDispatched(ProcessTelegramUpdate::class);
    }

    public function test_queued_job_rebuilds_request_and_reauthenticates_from_payload(): void
    {
        // TestCase installs a null auth stub; restore the real binding so we can
        // verify the worker re-authenticates from the payload, as in production.
        $this->app->singleton('laragram.auth', static function ($app) {
            return (new BotAuth(
                $app['request'],
                config('laragram.auth.driver'),
                (array) config('laragram.bot.languages', ['en']),
                config('laragram.auth.user.model'),
            ))->authenticate();
        });

        // Array driver + null-returning fixture routes → no DB writes, no network.
        (new ProcessTelegramUpdate(BotUpdateFactory::message('/start', userId: 100)))->handle();

        // The worker rebuilt a request from the payload and re-resolved auth on it.
        $this->assertSame(100, app('request')->input('message.from.id'));
        $this->assertSame(100, app('laragram.auth')->user()?->uid);
    }

    public function test_job_middleware_serializes_per_sender_and_rate_limits(): void
    {
        $middleware = (new ProcessTelegramUpdate(BotUpdateFactory::message('/start', userId: 100)))
            ->middleware();

        $this->assertInstanceOf(WithoutOverlapping::class, $middleware[0]);
        $this->assertSame('100', $middleware[0]->key, 'Overlap key should be the sender id.');

        $this->assertInstanceOf(RateLimited::class, $middleware[1]);
    }

    public function test_blocked_overlapping_update_is_released_not_dropped(): void
    {
        $job = new ProcessTelegramUpdate(BotUpdateFactory::message('/start', userId: 100));

        /** @var WithoutOverlapping $overlap */
        $overlap = $job->middleware()[0];

        $fake = new class {
            public bool $released = false;
            public function release(mixed $delay = 0): void
            {
                $this->released = true;
            }
        };

        // Simulate another in-flight update for user 100 by holding the same lock.
        $cache = \Illuminate\Container\Container::getInstance()
            ->make(\Illuminate\Contracts\Cache\Repository::class);
        $this->assertTrue($cache->lock($overlap->getLockKey($fake), 30)->get());

        $ran = false;
        $overlap->handle($fake, function () use (&$ran) {
            $ran = true;
        });

        $this->assertFalse($ran, 'Handler must not run while another update for the same user is in flight.');
        $this->assertTrue($fake->released, 'A blocked update must be released back to the queue, not dropped.');

        // The release above only avoids dropping the update because tries=0: under the
        // default queue:work --tries=1 the released job would fail on its next reservation.
        $this->assertSame(0, $job->tries);
    }

    public function test_job_payload_is_encrypted_at_rest(): void
    {
        // The update carries user PII (names, username, message text); the job must
        // be marked ShouldBeEncrypted so Laravel encrypts the payload in the queue store.
        $job = new ProcessTelegramUpdate(BotUpdateFactory::message('/start', userId: 100));

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldBeEncrypted::class, $job);
    }

    public function test_named_rate_limiter_is_registered(): void
    {
        $this->app['config']->set('laragram.queue.rate_limit', 25);

        $limiter = RateLimiter::limiter('laragram');
        $this->assertNotNull($limiter, "The 'laragram' rate limiter should be registered.");

        $limit = $limiter(Request::create('/'));
        $this->assertSame(25, $limit->maxAttempts);
        $this->assertSame(1, $limit->decaySeconds);
    }
}
