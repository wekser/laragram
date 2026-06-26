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

/**
 * Outcome of a broadcast send.
 *
 * For the synchronous path "sent" / "failed" are the real per-message results
 * and "queued" is 0. For the queued path "queued" is the number of jobs pushed
 * (the actual delivery happens later on a worker) and sent/failed stay 0.
 * "total" is the number of recipients the broadcast addressed in either mode.
 */
class BroadcastResult
{
    public function __construct(
        public readonly int $total = 0,
        public readonly int $sent = 0,
        public readonly int $failed = 0,
        public readonly int $queued = 0,
    ) {}

    /**
     * @return array{total: int, sent: int, failed: int, queued: int}
     */
    public function toArray(): array
    {
        return [
            'total'  => $this->total,
            'sent'   => $this->sent,
            'failed' => $this->failed,
            'queued' => $this->queued,
        ];
    }
}
