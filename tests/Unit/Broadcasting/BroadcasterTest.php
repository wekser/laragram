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

use PHPUnit\Framework\Attributes\CoversClass;
use Wekser\Laragram\Broadcasting\Broadcaster;
use Wekser\Laragram\Broadcasting\BroadcastRenderer;
use Wekser\Laragram\Broadcasting\PendingBroadcast;
use Wekser\Laragram\BotResponse;
use Wekser\Laragram\Facades\BotBroadcast;
use Wekser\Laragram\Testing\RecordingBotAPI;
use Wekser\Laragram\Tests\Concerns\UsesUserDatabase;
use Wekser\Laragram\Tests\TestCase;

#[CoversClass(Broadcaster::class)]
#[CoversClass(PendingBroadcast::class)]
#[CoversClass(BroadcastRenderer::class)]
class BroadcasterTest extends TestCase
{
    use UsesUserDatabase;

    private RecordingBotAPI $api;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpUserDatabase();
        config(['laragram.queue.enabled' => false]);

        $this->api = new RecordingBotAPI();
        $this->app->instance('laragram.api', $this->api);

        BotResponse::flushTemplateCache();
    }

    public function test_sends_text_to_all_active_users(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $this->makeUser(['is_active' => false]);

        $result = BotBroadcast::text('Hello everyone')->send();

        $this->assertCount(2, $this->api->calls);
        $this->assertSame(['total' => 2, 'sent' => 2, 'failed' => 0, 'queued' => 0], $result->toArray());

        $chatIds = array_map(static fn (array $c): int => $c['params']['chat_id'], $this->api->calls);
        sort($chatIds);
        $this->assertSame([$a->uid, $b->uid], $chatIds);

        $this->assertSame('sendMessage', $this->api->calls[0]['method']);
        $this->assertSame('Hello everyone', $this->api->calls[0]['params']['text']);
    }

    public function test_role_filter_targets_only_matching_users(): void
    {
        $this->makeUser(['role' => 'user']);
        $admin = $this->makeUser(['role' => 'admin']);

        BotBroadcast::text('Admins only')->role('admin')->send();

        $this->assertCount(1, $this->api->calls);
        $this->assertSame($admin->uid, $this->api->calls[0]['params']['chat_id']);
    }

    public function test_include_inactive_reaches_deactivated_users(): void
    {
        $this->makeUser();
        $this->makeUser(['is_active' => false]);

        BotBroadcast::text('Everyone')->includeInactive()->send();

        $this->assertCount(2, $this->api->calls);
    }

    public function test_count_reflects_filters_without_sending(): void
    {
        $this->makeUser(['role' => 'admin']);
        $this->makeUser(['role' => 'admin']);
        $this->makeUser(['role' => 'user']);

        $this->assertSame(2, BotBroadcast::text('x')->role('admin')->count());
        $this->assertCount(0, $this->api->calls);
    }

    public function test_sends_when_there_is_no_authenticated_sender(): void
    {
        // A broadcast runs in a CLI/queue context with no Telegram sender, so the
        // real laragram.auth factory throws on resolution. Building the per-
        // recipient BotResponse must not propagate that — delivery must proceed.
        $this->app->singleton('laragram.auth', static function (): void {
            throw new \Wekser\Laragram\Exceptions\AuthenticationException('No sender');
        });

        $a = $this->makeUser();
        $b = $this->makeUser();

        $result = BotBroadcast::text('Hello')->send();

        $this->assertCount(2, $this->api->calls);
        $this->assertSame(['total' => 2, 'sent' => 2, 'failed' => 0, 'queued' => 0], $result->toArray());
    }

    public function test_explicit_null_format_is_not_escaped(): void
    {
        $this->makeUser();

        BotBroadcast::text('<b>bold</b> & co', null)->send();

        $this->assertCount(1, $this->api->calls);
        // With format=null the text is sent verbatim; the bug coerced null to
        // HTML and escaped it to &lt;b&gt;bold&lt;/b&gt; &amp; co.
        $this->assertSame('<b>bold</b> & co', $this->api->calls[0]['params']['text']);
    }

    public function test_view_is_rendered_per_recipient_with_user_in_scope(): void
    {
        $this->makeUser(['first_name' => 'Alice']);
        $this->makeUser(['first_name' => 'Bob']);

        BotBroadcast::view('broadcast_view', ['headline' => 'we are live'])->send();

        $texts = array_map(static fn (array $c): string => $c['params']['text'], $this->api->calls);
        sort($texts);

        $this->assertSame(
            ['Hi Alice, we are live!', 'Hi Bob, we are live!'],
            $texts,
        );
    }
}
