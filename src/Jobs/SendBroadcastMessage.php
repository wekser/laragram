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
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Wekser\Laragram\Broadcasting\BroadcastRenderer;
use Wekser\Laragram\Exceptions\ExceptionHandler;
use Wekser\Laragram\Http\ResponseDispatcher;

/**
 * Delivers one broadcast message to one recipient on a queue worker.
 *
 * Dispatched per recipient by PendingBroadcast when laragram.queue.enabled is
 * true. The content is rendered here (not when the broadcast was triggered) so
 * the message is built in the recipient's language against the freshest user
 * record, then sent through the same ResponseDispatcher as every other outbound
 * message. Auto-deactivation of unreachable users is handled globally by the
 * DeactivateUnreachableUser listener via the BotExceptionHandled event, so this
 * job needs no special-casing for blocks/deactivations.
 *
 * Implements ShouldBeEncrypted: the content spec carries user-facing message
 * text, so Laravel encrypts it at rest in the queue store.
 */
class SendBroadcastMessage implements ShouldQueue, ShouldBeEncrypted
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * Retry indefinitely — see ProcessTelegramUpdate for the rationale. The
     * RateLimited middleware applies back-pressure by *releasing* the job, which
     * under the default --tries=1 would otherwise mark a throttled send failed.
     * handle() never rethrows (delivery errors are swallowed by the dispatcher),
     * so there is no poison-loop risk.
     *
     * @var int
     */
    public int $tries = 0;

    /**
     * Safety net bounding one send.
     *
     * It MUST stay above BotClient's own worst case, which is no longer a single
     * call: a send that never connects costs (retries + 1) x connect_timeout plus
     * the retry back-off — roughly 31s with the shipped defaults — and a send that
     * hangs mid-call costs telegram.timeout. A job killed by this timeout is
     * terminated by a signal, outside handle()'s try/catch, so with $tries = 0 it
     * is never failed and simply re-reserved forever. Raise it (and keep it above
     * that budget) if you configure a larger telegram.timeout or telegram.retries.
     *
     * @var int
     */
    public int $timeout = 60;

    /**
     * @param int                  $userId  Primary key of the recipient User.
     * @param array<string, mixed> $content Serializable content spec (see BroadcastRenderer).
     */
    public function __construct(
        public readonly int $userId,
        public readonly array $content,
    ) {}

    /**
     * Cap global throughput with the same limiter as incoming updates, keeping
     * outbound traffic under Telegram's ~30 msg/sec ceiling. No WithoutOverlapping
     * is needed: each job targets a distinct recipient.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited('laragram')];
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        try {
            /** @var class-string<\Wekser\Laragram\Models\User> $model */
            $model = config('laragram.auth.user.model');

            $user = $model::find($this->userId);

            if ($user === null) {
                return; // Recipient was deleted between dispatch and delivery.
            }

            $payload = app(BroadcastRenderer::class)->render($this->content, $user);

            app(ResponseDispatcher::class)->send([$payload]);
        } catch (\Throwable $exception) {
            ExceptionHandler::handle($exception);
        }
    }
}
