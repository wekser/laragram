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

namespace Wekser\Laragram\Console;

use Illuminate\Console\Command;
use Illuminate\Database\QueryException;

/**
 * Creates an admin-panel account (or resets an existing one's password),
 * keyed on the username. Password hashing is handled by the model's "hashed"
 * cast, so the plaintext is never stored.
 */
class AdminCreateCommand extends Command
{
    protected $signature = 'laragram:admin:create
        {username? : The login username}
        {--name= : Optional display name}
        {--password= : Password (prompted for if omitted)}';

    protected $description = 'Create a Laragram admin-panel account (or reset its password)';

    public function handle(): int
    {
        $username = (string) ($this->argument('username') ?: $this->ask('Username'));

        if (trim($username) === '') {
            $this->error('A username is required.');
            return self::FAILURE;
        }

        $password = (string) ($this->option('password') ?: $this->secret('Password'));

        if (strlen($password) < 8) {
            $this->error('The password must be at least 8 characters.');
            return self::FAILURE;
        }

        /** @var class-string<\Wekser\Laragram\Models\Admin> $model */
        $model = config('laragram.admin.model');

        try {
            $admin = $model::updateOrCreate(
                ['username' => $username],
                array_filter([
                    'name'     => $this->option('name'),
                    'password' => $password, // hashed by the model cast
                ], fn ($v) => $v !== null),
            );
        } catch (QueryException $e) {
            $this->error('Could not write to the admins table.');
            $this->line('Have you run the migration? <fg=cyan>php artisan vendor:publish --tag=laragram-migrations && php artisan migrate</>');
            return self::FAILURE;
        }

        $this->info($admin->wasRecentlyCreated
            ? "Admin [{$admin->username}] created."
            : "Password updated for admin [{$admin->username}].");

        $this->line('Sign in at <fg=cyan>/' . ltrim((string) config('laragram.admin.path', 'laragram/admin'), '/') . '/login</>');

        return self::SUCCESS;
    }
}
