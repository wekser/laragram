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
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Wekser\Laragram\Models\Admin;
use Wekser\Laragram\Tests\Concerns\UsesUserDatabase;
use Wekser\Laragram\Tests\TestCase;

/**
 * Login-page access to the panel via the laragram_admins table.
 *
 * Note: this class does NOT define a "viewLaragram" Gate (unlike AdminPanelTest),
 * because a defined Gate always wins and would bypass the login path.
 */
class AdminLoginTest extends TestCase
{
    use UsesUserDatabase;

    private string $password = 'super-secret-pw';

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

        Schema::create('laragram_admins', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('username')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    private function makeAdmin(): Admin
    {
        return Admin::create([
            'name'     => 'Root',
            'username' => 'root',
            'password' => $this->password, // hashed by the model cast
        ]);
    }

    // -------------------------------------------------------------------------
    // Access gating
    // -------------------------------------------------------------------------

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/laragram/admin')
            ->assertRedirect(route('laragram.admin.login'));
    }

    public function test_login_page_renders(): void
    {
        $this->get('/laragram/admin/login')
            ->assertOk()
            ->assertSee('Sign in');
    }

    public function test_authenticated_admin_can_view_dashboard(): void
    {
        $this->makeUser(['role' => 'admin']);

        $this->actingAs($this->makeAdmin(), 'laragram_admin')
            ->get('/laragram/admin')
            ->assertOk()
            ->assertSee('Total users');
    }

    // -------------------------------------------------------------------------
    // Login / logout
    // -------------------------------------------------------------------------

    public function test_valid_credentials_log_in(): void
    {
        $this->makeAdmin();

        $this->post('/laragram/admin/login', [
            'username' => 'root',
            'password' => $this->password,
        ])->assertRedirect(route('laragram.admin.dashboard'));

        $this->assertAuthenticated('laragram_admin');
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $this->makeAdmin();

        $this->post('/laragram/admin/login', [
            'username' => 'root',
            'password' => 'wrong',
        ])->assertSessionHasErrors('username');

        $this->assertGuest('laragram_admin');
    }

    public function test_logout(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'laragram_admin')
            ->post('/laragram/admin/logout')
            ->assertRedirect(route('laragram.admin.login'));

        $this->assertGuest('laragram_admin');
    }

    // -------------------------------------------------------------------------
    // Model / Gate
    // -------------------------------------------------------------------------

    public function test_password_is_stored_hashed(): void
    {
        $admin = $this->makeAdmin();

        $this->assertNotSame($this->password, $admin->password);
        $this->assertTrue(Hash::check($this->password, $admin->password));
    }

    public function test_defined_gate_overrides_the_login(): void
    {
        $this->makeUser(['role' => 'admin']);
        Gate::define('viewLaragram', fn ($user = null) => true);

        // No admin logged in, but the Gate grants access outright.
        $this->get('/laragram/admin')->assertOk();
    }

    public function test_denying_gate_is_a_hard_403(): void
    {
        Gate::define('viewLaragram', fn ($user = null) => false);

        $this->get('/laragram/admin')->assertForbidden();
    }
}
