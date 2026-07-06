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

namespace Wekser\Laragram\Tests\Unit\Console;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Wekser\Laragram\Models\Admin;
use Wekser\Laragram\Tests\Concerns\UsesUserDatabase;
use Wekser\Laragram\Tests\TestCase;

class AdminCreateCommandTest extends TestCase
{
    use UsesUserDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpUserDatabase();

        Schema::create('laragram_admins', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('username')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function test_creates_admin_with_hashed_password(): void
    {
        $this->artisan('laragram:admin:create', [
            'username'   => 'root',
            '--name'     => 'Root',
            '--password' => 'password123',
        ])->assertSuccessful();

        $admin = Admin::where('username', 'root')->first();

        $this->assertNotNull($admin);
        $this->assertSame('Root', $admin->name);
        $this->assertNotSame('password123', $admin->password);
        $this->assertTrue(Hash::check('password123', $admin->password));
    }

    public function test_updates_password_of_existing_admin(): void
    {
        Admin::create(['username' => 'root', 'password' => 'oldpassword']);

        $this->artisan('laragram:admin:create', [
            'username'   => 'root',
            '--password' => 'newpassword',
        ])->assertSuccessful();

        $this->assertSame(1, Admin::where('username', 'root')->count());
        $this->assertTrue(Hash::check('newpassword', Admin::where('username', 'root')->first()->password));
    }

    public function test_rejects_short_password(): void
    {
        $this->artisan('laragram:admin:create', [
            'username'   => 'root',
            '--password' => 'short',
        ])->assertFailed();

        $this->assertSame(0, Admin::count());
    }
}
