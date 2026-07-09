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

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use Wekser\Laragram\BotAuth;
use Wekser\Laragram\Http\ResponseTransformer;
use Wekser\Laragram\Listeners\LogSession;
use Wekser\Laragram\Models\Session;
use Wekser\Laragram\Models\User;
use Wekser\Laragram\Routing\Router;
use Wekser\Laragram\Testing\BotUpdateFactory;
use Wekser\Laragram\Testing\InteractsWithBot;
use Wekser\Laragram\Tests\Concerns\UsesUserDatabase;
use Wekser\Laragram\Tests\TestCase;

/**
 * Forum topic (message_thread_id) support: replies land back in the topic they
 * came from, routes can be constrained to a topic, and each topic keeps its own
 * station via the (user_id, chat_id, thread_id) session key.
 */
#[CoversClass(BotAuth::class)]
#[CoversClass(ResponseTransformer::class)]
#[CoversClass(Router::class)]
#[CoversClass(LogSession::class)]
class ForumTopicTest extends TestCase
{
    use InteractsWithBot;
    use UsesUserDatabase;

    private const FORUM = -1_000_000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpUserDatabase();

        Schema::create('laragram_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->bigInteger('chat_id')->nullable();
            $table->unsignedBigInteger('thread_id')->default(0);
            $table->unsignedBigInteger('update_id')->unique();
            $table->string('station');
            $table->json('payload');
            $table->timestamp('last_activity');
            $table->unique(['user_id', 'chat_id', 'thread_id']);
        });

        config(['laragram.paths.route' => 'topics']);
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
    // Payload detection
    // -------------------------------------------------------------------------

    public function test_thread_is_detected_only_for_topic_messages(): void
    {
        $topic = BotUpdateFactory::topicMessage('hi', threadId: 42);

        $this->assertSame(42, BotAuth::findThreadInPayload($topic));
    }

    public function test_a_reply_chain_thread_in_a_non_forum_group_is_not_a_topic(): void
    {
        // Telegram sets message_thread_id on plain replies in a supergroup too,
        // without is_topic_message. Such an id is not a valid send target.
        $update = BotUpdateFactory::groupMessage('hi');
        $update['message']['message_thread_id'] = 999;

        $this->assertNull(BotAuth::findThreadInPayload($update));
    }

    public function test_private_and_general_topic_messages_carry_no_thread(): void
    {
        $this->assertNull(BotAuth::findThreadInPayload(BotUpdateFactory::message('hi')));
        $this->assertNull(BotAuth::findThreadInPayload(BotUpdateFactory::groupMessage('hi')));
    }

    public function test_thread_is_detected_inside_a_callback_query(): void
    {
        $update = BotUpdateFactory::callbackQuery('btn', chatId: self::FORUM, chatType: 'supergroup', threadId: 42);

        $this->assertSame(42, BotAuth::findThreadInPayload($update));
    }

    // -------------------------------------------------------------------------
    // Outbound injection
    // -------------------------------------------------------------------------

    public function test_reply_to_a_topic_message_is_sent_back_into_that_topic(): void
    {
        $this->botReceives(BotUpdateFactory::topicMessage('/start', threadId: 5, chatId: self::FORUM));

        $this->assertBotRepliedText('started');
        $this->assertBotRepliedInThread(5);
        $this->assertResponseContains('chat_id', self::FORUM);
    }

    public function test_reply_in_a_private_chat_carries_no_thread(): void
    {
        $this->botReceives(BotUpdateFactory::message('/start'));

        $this->assertBotRepliedInThread(null);
    }

    public function test_thread_null_opts_out_of_the_injection(): void
    {
        $this->botReceives(BotUpdateFactory::topicMessage('/general', threadId: 5, chatId: self::FORUM));

        $this->assertBotRepliedText('TO_GENERAL');
        $this->assertBotRepliedInThread(null);

        // The '_no_thread' sentinel is internal and must never reach Telegram.
        $this->assertArrayNotHasKey('_no_thread', $this->getBotResponse());
    }

    public function test_an_explicit_thread_overrides_the_originating_topic(): void
    {
        $this->botReceives(BotUpdateFactory::topicMessage('/notify', threadId: 5, chatId: self::FORUM));

        $this->assertBotRepliedText('NOTIFIED');
        $this->assertBotRepliedInThread(7);
    }

    public function test_an_explicit_thread_works_from_a_private_chat(): void
    {
        $this->botReceives(BotUpdateFactory::message('/notify'));

        $this->assertBotRepliedInThread(7);
    }

    public function test_methods_without_a_thread_param_never_receive_one(): void
    {
        $this->botReceives(
            BotUpdateFactory::callbackQuery('btn', chatId: self::FORUM, chatType: 'supergroup', threadId: 5)
        );

        $this->assertBotRepliedWith('answerCallbackQuery');
        $this->assertArrayNotHasKey('message_thread_id', $this->getBotResponse());
    }

    // -------------------------------------------------------------------------
    // Routing
    // -------------------------------------------------------------------------

    public function test_a_thread_constrained_route_matches_only_its_topic(): void
    {
        $this->botReceives(BotUpdateFactory::topicMessage('/start', threadId: 42, chatId: self::FORUM));
        $this->assertBotRepliedText('STARTED_IN_42');
    }

    public function test_a_thread_constrained_route_is_skipped_in_another_topic(): void
    {
        $this->botReceives(BotUpdateFactory::topicMessage('/start', threadId: 43, chatId: self::FORUM));
        $this->assertBotRepliedText('started');
    }

    public function test_a_thread_constrained_route_is_skipped_outside_a_forum(): void
    {
        $this->botReceives(BotUpdateFactory::message('/start'));
        $this->assertBotRepliedText('started');
    }

    // -------------------------------------------------------------------------
    // Session isolation
    // -------------------------------------------------------------------------

    public function test_station_is_isolated_per_topic_for_the_same_user(): void
    {
        // Advance to 'step1' inside topic 5.
        $this->botReceives(BotUpdateFactory::topicMessage('/start', threadId: 5, chatId: self::FORUM));
        $this->botReceives(BotUpdateFactory::topicMessage('ping', threadId: 5, chatId: self::FORUM));
        $this->assertBotRepliedText('AT_STEP1');

        // Topic 6 in the SAME chat has independent state → still 'start'.
        $this->botReceives(BotUpdateFactory::topicMessage('ping', threadId: 6, chatId: self::FORUM));
        $this->assertBotRepliedText('FALLBACK');

        // The forum's General topic is independent too.
        $this->botReceives(BotUpdateFactory::groupMessage('ping', chatId: self::FORUM));
        $this->assertBotRepliedText('FALLBACK');
    }

    public function test_separate_session_rows_are_written_per_topic(): void
    {
        $this->botReceives(BotUpdateFactory::topicMessage('/start', threadId: 5, chatId: self::FORUM));
        $this->botReceives(BotUpdateFactory::topicMessage('/start', threadId: 6, chatId: self::FORUM));
        $this->botReceives(BotUpdateFactory::groupMessage('/start', chatId: self::FORUM));

        $user = User::where('uid', 100)->firstOrFail();

        $this->assertSame(3, Session::where('user_id', $user->id)->count());
        $this->assertSame('step1', $user->session(self::FORUM, 5)->station);
        $this->assertSame('step1', $user->session(self::FORUM, 6)->station);
        $this->assertSame('step1', $user->session(self::FORUM)->station); // General → thread_id 0
    }

    public function test_a_topic_session_does_not_leak_into_the_general_topic(): void
    {
        $this->botReceives(BotUpdateFactory::topicMessage('/start', threadId: 5, chatId: self::FORUM));

        $user = User::where('uid', 100)->firstOrFail();

        $this->assertNull($user->session(self::FORUM));
        $this->assertNotNull($user->session(self::FORUM, 5));
    }

    public function test_repeated_updates_in_one_topic_reuse_the_same_session_row(): void
    {
        $this->botReceives(BotUpdateFactory::topicMessage('/start', threadId: 5, chatId: self::FORUM));
        $this->botReceives(BotUpdateFactory::topicMessage('ping', threadId: 5, chatId: self::FORUM));

        $user = User::where('uid', 100)->firstOrFail();

        $this->assertSame(1, Session::where('user_id', $user->id)->count());
    }
}
