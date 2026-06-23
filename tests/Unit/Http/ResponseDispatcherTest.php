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

namespace Wekser\Laragram\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use Wekser\Laragram\Exceptions\BotBlockedException;
use Wekser\Laragram\Http\ResponseDispatcher;
use Wekser\Laragram\Testing\RecordingBotAPI;
use Wekser\Laragram\Tests\TestCase;

#[CoversClass(ResponseDispatcher::class)]
class ResponseDispatcherTest extends TestCase
{
    public function test_sends_each_view_in_order(): void
    {
        $api = new RecordingBotAPI();

        (new ResponseDispatcher($api))->send([
            ['method' => 'sendMessage', 'chat_id' => 1, 'text' => 'one'],
            ['method' => 'sendPhoto', 'chat_id' => 1, 'photo' => 'abc'],
        ]);

        $this->assertCount(2, $api->calls);
        $this->assertSame('sendMessage', $api->calls[0]['method']);
        $this->assertSame('sendPhoto', $api->calls[1]['method']);
    }

    public function test_strips_internal_keys_from_params(): void
    {
        $api = new RecordingBotAPI();

        (new ResponseDispatcher($api))->send([
            ['method' => 'sendMessage', 'chat_id' => 1, 'text' => 'hi', '_escaped' => true],
        ]);

        $params = $api->calls[0]['params'];

        $this->assertArrayNotHasKey('method', $params);
        $this->assertArrayNotHasKey('_escaped', $params);
        $this->assertSame(['chat_id' => 1, 'text' => 'hi'], $params);
    }

    public function test_skips_view_without_method(): void
    {
        $api = new RecordingBotAPI();

        (new ResponseDispatcher($api))->send([
            ['chat_id' => 1, 'text' => 'no method here'],
            ['method' => 'sendMessage', 'chat_id' => 1, 'text' => 'ok'],
        ]);

        $this->assertCount(1, $api->calls);
        $this->assertSame('sendMessage', $api->calls[0]['method']);
    }

    public function test_continues_after_non_terminal_error(): void
    {
        $api = new RecordingBotAPI(throwOn: 'sendMessage', exception: new \RuntimeException('transient'));

        (new ResponseDispatcher($api))->send([
            ['method' => 'sendMessage', 'chat_id' => 1, 'text' => 'fails'],
            ['method' => 'sendPhoto', 'chat_id' => 1, 'photo' => 'abc'],
        ]);

        // Both were attempted; the second still went through.
        $this->assertSame(['sendMessage', 'sendPhoto'], array_column($api->calls, 'method'));
    }

    public function test_stops_after_terminal_error(): void
    {
        $api = new RecordingBotAPI(throwOn: 'sendMessage', exception: new BotBlockedException(123));

        (new ResponseDispatcher($api))->send([
            ['method' => 'sendMessage', 'chat_id' => 1, 'text' => 'blocked'],
            ['method' => 'sendPhoto', 'chat_id' => 1, 'photo' => 'abc'],
        ]);

        // User unreachable — the remaining message is skipped.
        $this->assertSame(['sendMessage'], array_column($api->calls, 'method'));
    }
}
