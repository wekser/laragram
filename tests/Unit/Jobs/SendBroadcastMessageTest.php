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

namespace Wekser\Laragram\Tests\Unit\Jobs;

use Illuminate\Queue\Middleware\RateLimited;
use PHPUnit\Framework\Attributes\CoversClass;
use Wekser\Laragram\Jobs\SendBroadcastMessage;
use Wekser\Laragram\Testing\RecordingBotAPI;
use Wekser\Laragram\Tests\Concerns\UsesUserDatabase;
use Wekser\Laragram\Tests\TestCase;

#[CoversClass(SendBroadcastMessage::class)]
class SendBroadcastMessageTest extends TestCase
{
    use UsesUserDatabase;

    private RecordingBotAPI $api;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpUserDatabase();

        $this->api = new RecordingBotAPI();
        $this->app->instance('laragram.api', $this->api);
    }

    public function test_renders_and_delivers_to_the_recipient(): void
    {
        $user = $this->makeUser(['first_name' => 'Zoe']);

        (new SendBroadcastMessage($user->id, ['type' => 'text', 'text' => 'Hi there', 'format' => 'HTML']))->handle();

        $this->assertCount(1, $this->api->calls);
        $this->assertSame('sendMessage', $this->api->calls[0]['method']);
        $this->assertSame('Hi there', $this->api->calls[0]['params']['text']);
        $this->assertSame($user->uid, $this->api->calls[0]['params']['chat_id']);
    }

    public function test_missing_recipient_is_skipped_without_error(): void
    {
        (new SendBroadcastMessage(999_999, ['type' => 'text', 'text' => 'x', 'format' => 'HTML']))->handle();

        $this->assertCount(0, $this->api->calls);
    }

    public function test_middleware_includes_the_laragram_rate_limiter(): void
    {
        $middleware = (new SendBroadcastMessage(1, ['type' => 'text', 'text' => 'x']))->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(RateLimited::class, $middleware[0]);
    }
}
