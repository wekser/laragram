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

namespace Wekser\Laragram\Tests\Concerns;

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Wekser\Laragram\Models\User;

/**
 * Sets up the "database" auth driver against an in-memory SQLite connection and
 * builds the laragram_users table, mirroring the package migration stub. Used by
 * the broadcast tests, which need real persisted users to iterate over.
 */
trait UsesUserDatabase
{
    protected function setUpUserDatabase(): void
    {
        config([
            'laragram.auth.driver'          => 'database',
            'database.default'              => 'laragram_testing',
            'database.connections.laragram_testing' => [
                'driver'   => 'sqlite',
                'database' => ':memory:',
                'prefix'   => '',
            ],
        ]);

        Schema::create('laragram_users', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('uid')->unique();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('username')->nullable();
            $table->json('settings')->nullable();
            $table->string('role')->default('user');
            $table->boolean('is_active')->default(true);
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * @param array<string, mixed> $attributes
     */
    protected function makeUser(array $attributes = []): User
    {
        static $seq = 0;
        $seq++;

        return User::create(array_merge([
            'uid'        => 100_000 + $seq,
            'first_name' => 'User' . $seq,
            'role'       => 'user',
            'is_active'  => true,
            'settings'   => [],
        ], $attributes));
    }
}
