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
use Wekser\Laragram\Http\ResponseTransformer;
use Wekser\Laragram\Routing\Router;
use Wekser\Laragram\Testing\BotUpdateFactory;
use Wekser\Laragram\Testing\InteractsWithBot;
use Wekser\Laragram\Tests\TestCase;

/**
 * Group-chat routing: command matching with the "@botusername" suffix and the
 * chat-type route filters. Runs under the array driver (no session persistence).
 */
#[CoversClass(Router::class)]
#[CoversClass(ResponseTransformer::class)]
#[CoversClass(BotRequest::class)]
class GroupChatTest extends TestCase
{
    use InteractsWithBot;

    protected function setUp(): void
    {
        parent::setUp();

        config(['laragram.paths.route' => 'group']);
        Router::flushCache();
        BotUpdateFactory::reset();
    }

    protected function tearDown(): void
    {
        Router::flushCache();
        BotUpdateFactory::reset();

        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Command matching with @botusername (Phase 1)
    // -------------------------------------------------------------------------

    public function test_command_with_matching_bot_mention_matches_in_group(): void
    {
        config(['laragram.telegram.username' => 'MyBot']);

        $this->botReceives(BotUpdateFactory::groupMessage('/start@MyBot'));

        $this->assertBotRepliedText('started');
    }

    public function test_command_with_other_bot_mention_does_not_match_when_username_set(): void
    {
        config(['laragram.telegram.username' => 'MyBot']);

        $this->botReceives(BotUpdateFactory::groupMessage('/start@OtherBot'));

        $this->assertBotRepliedText('FALLBACK');
    }

    public function test_command_with_any_mention_matches_when_username_empty(): void
    {
        config(['laragram.telegram.username' => null]);

        $this->botReceives(BotUpdateFactory::groupMessage('/start@Whatever'));

        $this->assertBotRepliedText('started');
    }

    public function test_plain_command_still_matches_in_private_chat(): void
    {
        config(['laragram.telegram.username' => 'MyBot']);

        $this->botReceives(BotUpdateFactory::message('/start'));

        $this->assertBotRepliedText('started');
    }

    // -------------------------------------------------------------------------
    // Chat-type route filters (Phase 3)
    // -------------------------------------------------------------------------

    public function test_group_only_route_matches_in_group(): void
    {
        $this->botReceives(BotUpdateFactory::groupMessage('/groupcmd'));

        $this->assertBotRepliedText('GROUP_ONLY');
    }

    public function test_group_only_route_falls_through_in_private(): void
    {
        $this->botReceives(BotUpdateFactory::message('/groupcmd'));

        $this->assertBotRepliedText('FALLBACK');
    }

    public function test_private_only_route_matches_in_private(): void
    {
        $this->botReceives(BotUpdateFactory::message('/privatecmd'));

        $this->assertBotRepliedText('PRIVATE_ONLY');
    }

    public function test_private_only_route_falls_through_in_group(): void
    {
        $this->botReceives(BotUpdateFactory::groupMessage('/privatecmd'));

        $this->assertBotRepliedText('FALLBACK');
    }

    // -------------------------------------------------------------------------
    // Outbound targeting: reply goes to the group chat, not the member's DM
    // -------------------------------------------------------------------------

    public function test_reply_is_addressed_to_the_group_chat(): void
    {
        $this->botReceives(BotUpdateFactory::groupMessage('/start@x', chatId: -4242));

        $this->assertResponseContains('chat_id', -4242);
    }

    // -------------------------------------------------------------------------
    // BotRequest chat-type helpers
    // -------------------------------------------------------------------------

    public function test_bot_request_chat_type_helpers_for_group(): void
    {
        $request = new BotRequest(['update' => ['object' => ['chat' => ['id' => -1, 'type' => 'supergroup']]]]);

        $this->assertSame('supergroup', $request->chatType());
        $this->assertTrue($request->isGroup());
        $this->assertTrue($request->isSupergroup());
        $this->assertFalse($request->isPrivate());
    }

    public function test_bot_request_chat_type_helpers_for_private(): void
    {
        $request = new BotRequest(['update' => ['object' => ['chat' => ['id' => 5, 'type' => 'private']]]]);

        $this->assertSame('private', $request->chatType());
        $this->assertTrue($request->isPrivate());
        $this->assertFalse($request->isGroup());
    }

    public function test_bot_request_resolves_chat_nested_in_callback_message(): void
    {
        $request = new BotRequest(['update' => ['object' => ['message' => ['chat' => ['id' => -1, 'type' => 'group']]]]]);

        $this->assertSame('group', $request->chatType());
        $this->assertTrue($request->isGroup());
    }
}
