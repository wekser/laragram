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
use Wekser\Laragram\Listeners\LogSession;
use Wekser\Laragram\Models\Session;
use Wekser\Laragram\Models\User;
use Wekser\Laragram\Routing\Router;
use Wekser\Laragram\Testing\BotUpdateFactory;
use Wekser\Laragram\Testing\InteractsWithBot;
use Wekser\Laragram\Tests\Concerns\UsesUserDatabase;
use Wekser\Laragram\Tests\TestCase;

/**
 * Per-(user, chat) state isolation under the database driver: a user's station
 * in one chat must not leak into another chat, and a private chat keeps its own
 * state (chat_id == uid). Exercises LogSession's composite (user_id, chat_id) key.
 */
#[CoversClass(LogSession::class)]
#[CoversClass(Router::class)]
class GroupSessionIsolationTest extends TestCase
{
    use InteractsWithBot;
    use UsesUserDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpUserDatabase(); // driver=database + laragram_users table

        Schema::create('laragram_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->bigInteger('chat_id')->nullable();
            $table->unsignedBigInteger('update_id')->unique();
            $table->string('station');
            $table->json('payload');
            $table->timestamp('last_activity');
            $table->unique(['user_id', 'chat_id']);
        });

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

    public function test_station_is_isolated_per_chat_for_the_same_user(): void
    {
        // Alice starts the flow in group A → advances to 'step1' there.
        $this->botReceives(BotUpdateFactory::groupMessage('/start', chatId: -1000, userId: 100));
        $this->assertBotRepliedText('started');

        // Back in group A she is at step1.
        $this->botReceives(BotUpdateFactory::groupMessage('ping', chatId: -1000, userId: 100));
        $this->assertBotRepliedText('AT_STEP1');

        // In group B the SAME user has independent state → still 'start'.
        $this->botReceives(BotUpdateFactory::groupMessage('ping', chatId: -2000, userId: 100));
        $this->assertBotRepliedText('FALLBACK');
    }

    public function test_separate_session_rows_are_written_per_chat(): void
    {
        $this->botReceives(BotUpdateFactory::groupMessage('/start', chatId: -1000, userId: 100));
        $this->botReceives(BotUpdateFactory::groupMessage('/start', chatId: -2000, userId: 100));

        $user = User::where('uid', 100)->firstOrFail();

        $this->assertSame(2, Session::where('user_id', $user->id)->count());
        $this->assertSame('step1', $user->session(-1000)->station);
        $this->assertSame('step1', $user->session(-2000)->station);
    }

    public function test_private_chat_keeps_its_own_state(): void
    {
        // In a private chat chat.id == uid, so state persists as before.
        $this->botReceives(BotUpdateFactory::message('/start', userId: 100, chatId: 100));
        $this->botReceives(BotUpdateFactory::message('ping', userId: 100, chatId: 100));

        $this->assertBotRepliedText('AT_STEP1');
    }

    public function test_different_users_in_one_group_do_not_share_state(): void
    {
        // Alice advances to step1 in group A.
        $this->botReceives(BotUpdateFactory::groupMessage('/start', chatId: -1000, userId: 100));

        // Bob, in the same group, has his own (empty) state → 'start'.
        $this->botReceives(BotUpdateFactory::groupMessage('ping', chatId: -1000, userId: 200));
        $this->assertBotRepliedText('FALLBACK');
    }
}
