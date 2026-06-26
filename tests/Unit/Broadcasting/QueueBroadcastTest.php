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

namespace Wekser\Laragram\Tests\Unit\Broadcasting;

use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\CoversClass;
use Wekser\Laragram\Broadcasting\PendingBroadcast;
use Wekser\Laragram\Facades\BotBroadcast;
use Wekser\Laragram\Jobs\SendBroadcastMessage;
use Wekser\Laragram\Tests\Concerns\UsesUserDatabase;
use Wekser\Laragram\Tests\TestCase;

#[CoversClass(PendingBroadcast::class)]
class QueueBroadcastTest extends TestCase
{
    use UsesUserDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpUserDatabase();

        config([
            'laragram.queue.enabled'    => true,
            'laragram.queue.connection' => null,
            'laragram.queue.queue'      => 'telegram',
        ]);
    }

    public function test_dispatches_one_job_per_recipient_on_configured_queue(): void
    {
        Bus::fake();

        $a = $this->makeUser();
        $b = $this->makeUser();
        $this->makeUser(['is_active' => false]); // excluded by default

        $result = BotBroadcast::text('Queued hello')->send();

        $this->assertSame(2, $result->queued);
        $this->assertSame(2, $result->total);

        Bus::assertDispatchedTimes(SendBroadcastMessage::class, 2);

        Bus::assertDispatched(
            SendBroadcastMessage::class,
            static fn (SendBroadcastMessage $job): bool =>
                $job->userId === $a->id
                && $job->content['type'] === 'text'
                && $job->content['text'] === 'Queued hello'
                && $job->queue === 'telegram',
        );

        Bus::assertDispatched(
            SendBroadcastMessage::class,
            static fn (SendBroadcastMessage $job): bool => $job->userId === $b->id,
        );
    }
}
