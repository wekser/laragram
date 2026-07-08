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

namespace Wekser\Laragram\Tests\Unit\Admin;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Wekser\Laragram\Jobs\SendBroadcastMessage;
use Wekser\Laragram\Models\Session;
use Wekser\Laragram\Tests\Concerns\UsesUserDatabase;
use Wekser\Laragram\Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use UsesUserDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpUserDatabase();

        Schema::create('laragram_sessions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->bigInteger('chat_id')->nullable();
            $table->unsignedBigInteger('update_id')->unique();
            $table->string('station')->default('start');
            $table->json('payload')->nullable();
            $table->timestamp('last_activity')->nullable();
        });

        // Grant access to the panel for the test run.
        Gate::define('viewLaragram', fn ($user = null) => true);
    }

    // -------------------------------------------------------------------------
    // Access control
    // -------------------------------------------------------------------------

    public function test_panel_is_forbidden_when_gate_denies(): void
    {
        Gate::define('viewLaragram', fn ($user = null) => false);

        $this->get('/laragram/admin')->assertForbidden();
    }

    public function test_panel_requires_database_driver(): void
    {
        config(['laragram.auth.driver' => 'array']);

        $this->get('/laragram/admin')->assertStatus(503);
    }

    // -------------------------------------------------------------------------
    // Dashboard / metrics
    // -------------------------------------------------------------------------

    public function test_dashboard_shows_metrics(): void
    {
        $this->makeUser(['role' => 'admin']);
        $this->makeUser(['role' => 'user']);
        $this->makeUser(['role' => 'user', 'is_active' => false]);

        $this->get('/laragram/admin')
            ->assertOk()
            ->assertSee('Total users')
            ->assertSee('Users by role');
    }

    // -------------------------------------------------------------------------
    // Users
    // -------------------------------------------------------------------------

    public function test_users_page_lists_and_filters_by_role(): void
    {
        $this->makeUser(['first_name' => 'Alice', 'role' => 'admin']);
        $this->makeUser(['first_name' => 'Bob', 'role' => 'user']);

        $this->get('/laragram/admin/users')
            ->assertOk()
            ->assertSee('Alice')
            ->assertSee('Bob');

        $this->get('/laragram/admin/users?role=admin')
            ->assertOk()
            ->assertSee('Alice')
            ->assertDontSee('Bob');
    }

    public function test_update_role(): void
    {
        $user = $this->makeUser(['role' => 'user']);

        $this->post("/laragram/admin/users/{$user->id}/role", ['role' => 'moderator'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame('moderator', $user->fresh()->role);
    }

    public function test_toggle_active(): void
    {
        $user = $this->makeUser(['is_active' => true]);

        $this->post("/laragram/admin/users/{$user->id}/toggle")->assertRedirect();
        $this->assertFalse($user->fresh()->is_active);

        $this->post("/laragram/admin/users/{$user->id}/toggle")->assertRedirect();
        $this->assertTrue($user->fresh()->is_active);
    }

    // -------------------------------------------------------------------------
    // Sessions
    // -------------------------------------------------------------------------

    public function test_sessions_page_and_prune(): void
    {
        $user = $this->makeUser();

        Session::create([
            'user_id' => $user->id, 'update_id' => 1, 'station' => 'home',
            'last_activity' => Carbon::now(),
        ]);
        Session::create([
            'user_id' => $user->id, 'update_id' => 2, 'station' => 'old',
            'last_activity' => Carbon::now()->subMinutes(20_000),
        ]);

        $this->get('/laragram/admin/sessions')->assertOk()->assertSee('home');

        $this->post('/laragram/admin/sessions/prune')
            ->assertRedirect()
            ->assertSessionHas('status');

        // The stale session is gone; the recent one remains.
        $this->assertSame(1, Session::query()->count());
        $this->assertNull(Session::query()->where('station', 'old')->first());
    }

    // -------------------------------------------------------------------------
    // Broadcast
    // -------------------------------------------------------------------------

    public function test_broadcast_page_renders(): void
    {
        $this->get('/laragram/admin/broadcast')->assertOk()->assertSee('Broadcast');
    }

    public function test_broadcast_preview_reports_recipient_count(): void
    {
        $this->makeUser(['is_active' => true]);
        $this->makeUser(['is_active' => true]);
        $this->makeUser(['is_active' => false]); // excluded from active audience

        $this->post('/laragram/admin/broadcast', [
            'message' => 'Hello everyone',
            'action'  => 'preview',
        ])
            ->assertRedirect()
            ->assertSessionHas('status', fn ($status) => str_contains($status, '2 user'));
    }

    public function test_broadcast_send_dispatches_jobs_when_queued(): void
    {
        config(['laragram.queue.enabled' => true]);
        Queue::fake();

        $this->makeUser(['is_active' => true]);
        $this->makeUser(['is_active' => true]);

        $this->post('/laragram/admin/broadcast', [
            'message' => 'Shipping now',
            'action'  => 'send',
        ])->assertRedirect()->assertSessionHas('status');

        Queue::assertPushed(SendBroadcastMessage::class, 2);
    }

    public function test_broadcast_requires_a_message(): void
    {
        $this->post('/laragram/admin/broadcast', ['action' => 'preview'])
            ->assertSessionHasErrors('message');
    }

    public function test_broadcast_refuses_large_synchronous_send_from_web(): void
    {
        config(['laragram.queue.enabled' => false, 'laragram.broadcast.web_sync_limit' => 1]);

        $this->makeUser(['is_active' => true]);
        $this->makeUser(['is_active' => true]); // 2 recipients > limit of 1

        // Must be refused (never reaches the blocking synchronous send / real API).
        $this->post('/laragram/admin/broadcast', ['message' => 'hi', 'action' => 'send'])
            ->assertRedirect()
            ->assertSessionHasErrors('message');
    }

    public function test_broadcast_page_lists_available_views(): void
    {
        $this->get('/laragram/admin/broadcast')
            ->assertOk()
            ->assertSee('broadcast_view');
    }

    public function test_broadcast_view_mode_sends_a_rendered_view(): void
    {
        config(['laragram.queue.enabled' => false]);

        $api = new \Wekser\Laragram\Testing\RecordingBotAPI();
        $this->app->instance('laragram.api', $api);

        $this->makeUser(['first_name' => 'Alice']);

        $this->post('/laragram/admin/broadcast', [
            'content_type' => 'view',
            'view'         => 'broadcast_view',
            'data'         => '{"headline":"we are live"}',
            'action'       => 'send',
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertCount(1, $api->calls);
        $this->assertSame('Hi Alice, we are live!', $api->calls[0]['params']['text']);
    }

    public function test_broadcast_view_mode_rejects_an_unknown_view(): void
    {
        $this->post('/laragram/admin/broadcast', [
            'content_type' => 'view',
            'view'         => 'no_such_view',
            'action'       => 'send',
        ])->assertSessionHasErrors('view');
    }

    public function test_broadcast_view_mode_rejects_invalid_json_data(): void
    {
        $this->makeUser(['is_active' => true]);

        $this->post('/laragram/admin/broadcast', [
            'content_type' => 'view',
            'view'         => 'broadcast_view',
            'data'         => 'not-json',
            'action'       => 'send',
        ])->assertSessionHasErrors('data');
    }
}
