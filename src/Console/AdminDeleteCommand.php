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

/**
 * Deletes an admin-panel account by username.
 */
class AdminDeleteCommand extends Command
{
    protected $signature = 'laragram:admin:delete
        {username : The login username to remove}';

    protected $description = 'Delete a Laragram admin-panel account';

    public function handle(): int
    {
        $username = (string) $this->argument('username');

        /** @var class-string<\Wekser\Laragram\Models\Admin> $model */
        $model = config('laragram.admin.model');

        $admin = $model::where('username', $username)->first();

        if ($admin === null) {
            $this->error("No admin found with username [{$username}].");
            return self::FAILURE;
        }

        if (! $this->confirm("Delete admin [{$username}]?", true)) {
            $this->line('Aborted.');
            return self::SUCCESS;
        }

        $admin->delete();

        $this->info("Admin [{$username}] deleted.");

        return self::SUCCESS;
    }
}
