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

use Wekser\Laragram\BotAuth;
use Wekser\Laragram\Routing\Router;
use Wekser\Laragram\Testing\BotUpdateFactory;
use Wekser\Laragram\Testing\InteractsWithBot;
use Wekser\Laragram\Tests\TestCase;
use Wekser\Laragram\View\ComponentContext;

/**
 * End-to-end test of message reactions through InteractsWithBot: a
 * message_reaction update routes to its handler (which reads the reaction and
 * reacts back), and a command handler reacts to the incoming message.
 */
class ReactionsFlowTest extends TestCase
{
    use InteractsWithBot;

    protected function setUp(): void
    {
        parent::setUp();

        config(['laragram.paths.route' => 'reactions']);
        Router::flushCache();
        BotUpdateFactory::reset();
    }

    protected function tearDown(): void
    {
        Router::flushCache();
        BotUpdateFactory::reset();
        ComponentContext::reset();

        parent::tearDown();
    }

    public function test_message_reaction_routes_to_handler(): void
    {
        $this->botReceives(BotUpdateFactory::messageReaction('👍'));

        $this->assertBotRepliedTimes(2);
        $this->assertNthReplyWith(0, 'sendMessage');
        $this->assertNthReplyText(0, 'You reacted: 👍');
        $this->assertNthReplyWith(1, 'setMessageReaction');
    }

    public function test_reaction_reply_carries_chat_and_message_ids(): void
    {
        $this->botReceives(BotUpdateFactory::messageReaction('👍', chatId: 777, messageId: 42));

        $reaction = $this->getBotResponses()[1];

        $this->assertSame(777, $reaction['chat_id']);
        $this->assertSame(42, $reaction['message_id']);
        $this->assertSame([['type' => 'emoji', 'emoji' => '❤️']], $reaction['reaction']);
    }

    public function test_command_handler_reacts_to_the_incoming_message(): void
    {
        $this->botReceives(BotUpdateFactory::message('/like'));

        $this->assertBotRepliedWith('setMessageReaction');
        $this->assertResponseContains('message_id', 1);
        $this->assertResponseContains('is_big', true);
    }

    public function test_senderless_reaction_payloads_are_detected(): void
    {
        $this->assertFalse(BotAuth::isSenderlessPayload(BotUpdateFactory::messageReaction()));
        $this->assertTrue(BotAuth::isSenderlessPayload(BotUpdateFactory::messageReaction(anonymous: true)));
        $this->assertTrue(BotAuth::isSenderlessPayload([
            'update_id'              => 1,
            'message_reaction_count' => [
                'chat'       => ['id' => 100, 'type' => 'channel'],
                'message_id' => 1,
                'date'       => time(),
                'reactions'  => [],
            ],
        ]));
    }
}
