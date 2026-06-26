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

namespace Wekser\Laragram\Broadcasting;

use Illuminate\Database\Eloquent\Builder;
use Wekser\Laragram\Exceptions\ExceptionHandler;
use Wekser\Laragram\Jobs\SendBroadcastMessage;
use Wekser\Laragram\Support\OutboundPayload;

/**
 * A configured-but-not-yet-sent broadcast.
 *
 * Returned by Broadcaster::view() / text(); refine the audience with role() /
 * includeInactive() / query(), then count() or send(). Built fresh per call so
 * the shared Broadcaster singleton never leaks recipient filters between
 * broadcasts (mirrors BotResponse's clone-on-entry).
 */
class PendingBroadcast
{
    /** @var array<int, string> */
    private array $roles = [];

    private bool $includeInactive = false;

    /** @var (callable(Builder): mixed)|null */
    private $queryCallback = null;

    /**
     * @param array<string, mixed> $content Serializable content spec (see BroadcastRenderer).
     */
    public function __construct(
        private readonly array $content,
        private readonly BroadcastRenderer $renderer,
    ) {}

    /**
     * Restrict recipients to one or more roles.
     *
     * @param string|array<int, string> $roles
     */
    public function role(string|array $roles): self
    {
        $this->roles = array_values((array) $roles);

        return $this;
    }

    /**
     * Also include deactivated users (by default only active users receive it).
     */
    public function includeInactive(bool $include = true): self
    {
        $this->includeInactive = $include;

        return $this;
    }

    /**
     * Apply an arbitrary constraint to the recipient query.
     *
     * @param callable(Builder): mixed $callback
     */
    public function query(callable $callback): self
    {
        $this->queryCallback = $callback;

        return $this;
    }

    /**
     * Number of recipients the current filters match.
     */
    public function count(): int
    {
        return $this->baseQuery()->count();
    }

    /**
     * Send the broadcast.
     *
     * Defers to a queue worker (one SendBroadcastMessage per recipient) when
     * queue.enabled is true; otherwise sends synchronously, throttled by
     * broadcast.sync_delay_ms.
     */
    public function send(): BroadcastResult
    {
        return (bool) config('laragram.queue.enabled', false)
            ? $this->dispatchToQueue()
            : $this->sendSync();
    }

    /**
     * Build the recipient query from the configured filters.
     */
    protected function baseQuery(): Builder
    {
        /** @var class-string<\Wekser\Laragram\Models\User> $model */
        $model = config('laragram.auth.user.model');

        $query = $model::query();

        if (!$this->includeInactive) {
            $query->where('is_active', true);
        }

        if ($this->roles !== []) {
            $query->whereIn('role', $this->roles);
        }

        if ($this->queryCallback !== null) {
            ($this->queryCallback)($query);
        }

        return $query;
    }

    /**
     * Dispatch one queued job per recipient.
     */
    protected function dispatchToQueue(): BroadcastResult
    {
        $queued = 0;

        // Constant for the whole send — read once, not per recipient.
        $connection = config('laragram.queue.connection');
        $queue      = config('laragram.queue.queue') ?? 'default';

        $this->recipients()->chunkById($this->chunkSize(), function ($users) use (&$queued, $connection, $queue): void {
            foreach ($users as $user) {
                SendBroadcastMessage::dispatch((int) $user->id, $this->content)
                    ->onConnection($connection)
                    ->onQueue($queue);

                $queued++;
            }
        });

        return new BroadcastResult(total: $queued, queued: $queued);
    }

    /**
     * Send to every recipient inline, throttled between sends.
     */
    protected function sendSync(): BroadcastResult
    {
        $delayMicros = max(0, (int) config('laragram.broadcast.sync_delay_ms', 40)) * 1000;

        $total = 0;
        $sent  = 0;

        $this->recipients()->chunkById($this->chunkSize(), function ($users) use (&$total, &$sent, $delayMicros): void {
            foreach ($users as $user) {
                $total++;

                if ($this->deliverTo($user)) {
                    $sent++;
                }

                if ($delayMicros > 0) {
                    usleep($delayMicros);
                }
            }
        });

        return new BroadcastResult(total: $total, sent: $sent, failed: $total - $sent);
    }

    /**
     * Render and deliver the broadcast to one recipient, returning success.
     *
     * Rendering is inside the try/catch so a render-time error (missing view,
     * bad content spec, unresolved locale, …) counts as a single failed send
     * and the broadcast continues, rather than aborting the whole run. A failure
     * is funnelled through ExceptionHandler::handle() — that fires
     * BotExceptionHandled, which drives auto-deactivation of unreachable users.
     */
    protected function deliverTo(\Wekser\Laragram\Models\User $user): bool
    {
        try {
            $payload = $this->renderer->render($this->content, $user);
            $method  = OutboundPayload::method($payload);

            if ($method === null) {
                return false;
            }

            app('laragram.api')->{$method}(OutboundPayload::params($payload));

            return true;
        } catch (\Throwable $exception) {
            ExceptionHandler::handle($exception);

            return false;
        }
    }

    /**
     * Recipient query for iteration: strips any caller-supplied ordering so
     * chunkById can impose its own primary-key cursor. A query() callback that
     * added e.g. orderBy('created_at') would otherwise corrupt chunkById's
     * `id > lastId` pagination and skip/duplicate recipients.
     */
    protected function recipients(): Builder
    {
        return $this->baseQuery()->reorder();
    }

    /**
     * How many recipients to load per chunk.
     */
    protected function chunkSize(): int
    {
        return max(1, (int) config('laragram.broadcast.chunk_size', 500));
    }
}
