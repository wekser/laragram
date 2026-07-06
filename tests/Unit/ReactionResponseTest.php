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

use PHPUnit\Framework\Attributes\CoversClass;
use Wekser\Laragram\BotRequest;
use Wekser\Laragram\BotResponse;
use Wekser\Laragram\Http\ResponseTransformer;
use Wekser\Laragram\Tests\TestCase;

/**
 * BotResponse::react() builds the setMessageReaction payload, and
 * ResponseTransformer injects chat_id + message_id from the update object.
 */
#[CoversClass(BotResponse::class)]
#[CoversClass(ResponseTransformer::class)]
class ReactionResponseTest extends TestCase
{
    private function response(): BotResponse
    {
        return new BotResponse(config('laragram.paths.views'));
    }

    public function test_react_builds_set_message_reaction_payload(): void
    {
        $contents = $this->response()->react('👍')->contents;

        $this->assertSame('setMessageReaction', $contents['method']);
        $this->assertSame([['type' => 'emoji', 'emoji' => '👍']], $contents['reaction']);
        $this->assertFalse($contents['is_big']);
    }

    public function test_react_accepts_a_list_of_emoji_and_big_flag(): void
    {
        $contents = $this->response()->react(['❤️', '🔥'], big: true)->contents;

        $this->assertSame([
            ['type' => 'emoji', 'emoji' => '❤️'],
            ['type' => 'emoji', 'emoji' => '🔥'],
        ], $contents['reaction']);
        $this->assertTrue($contents['is_big']);
    }

    public function test_react_passes_raw_reaction_type_arrays_verbatim(): void
    {
        $custom = ['type' => 'custom_emoji', 'custom_emoji_id' => '5368324170671202286'];

        $contents = $this->response()->react([$custom])->contents;

        $this->assertSame([$custom], $contents['reaction']);
    }

    public function test_react_with_empty_array_clears_the_reaction(): void
    {
        $contents = $this->response()->react([])->contents;

        $this->assertSame('setMessageReaction', $contents['method']);
        $this->assertSame([], $contents['reaction']);
    }

    public function test_react_returns_distinct_instances(): void
    {
        $shared = $this->response();
        $a = $shared->react('👍');
        $b = $shared->react('🔥');

        $this->assertNotSame($a, $b);
        $this->assertSame('👍', $a->contents['reaction'][0]['emoji']);
        $this->assertSame('🔥', $b->contents['reaction'][0]['emoji']);
    }

    public function test_chat_id_and_message_id_are_injected_from_reaction_update(): void
    {
        $request = new BotRequest([
            'update' => ['id' => 1, 'object' => [
                'chat'         => ['id' => 777, 'type' => 'private'],
                'message_id'   => 42,
                'user'         => ['id' => 100, 'first_name' => 'Test'],
                'new_reaction' => [['type' => 'emoji', 'emoji' => '👍']],
                'old_reaction' => [],
            ]],
            'route'  => [
                'event'    => 'message_reaction',
                'listener' => 'user',
                'contains' => null,
                'uses'     => 'callback',
                'form'     => 'home',
            ],
            'data'   => ['all' => []],
        ]);

        $output = (new ResponseTransformer())->getResponse(
            $request,
            $this->response()->react('❤️'),
        );

        $view = $output['response']['views'][0];

        $this->assertSame(777, $view['chat_id']);
        $this->assertSame(42, $view['message_id']);
    }

    public function test_message_reaction_accessor_returns_object_only_for_its_event(): void
    {
        $object = [
            'chat'         => ['id' => 100, 'type' => 'private'],
            'message_id'   => 1,
            'new_reaction' => [['type' => 'emoji', 'emoji' => '👍']],
        ];

        $request = new BotRequest([
            'update' => ['id' => 1, 'object' => $object],
            'route'  => ['event' => 'message_reaction', 'listener' => 'user', 'contains' => null, 'uses' => 'callback', 'form' => 'home'],
            'data'   => ['all' => []],
        ]);

        $this->assertSame($object, $request->messageReaction());

        $other = new BotRequest([
            'update' => ['id' => 2, 'object' => ['text' => 'hi']],
            'route'  => ['event' => 'message', 'listener' => 'text', 'contains' => null, 'uses' => 'callback', 'form' => 'home'],
            'data'   => ['all' => []],
        ]);

        $this->assertNull($other->messageReaction());
    }
}
