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

class SetRoleCommand extends Command
{
    protected $signature = 'laragram:set-role
        {uid : Telegram user ID}
        {role : Role to assign (e.g. admin, moderator, user)}';

    protected $description = 'Assign a role to a Laragram bot user by their Telegram ID';

    public function handle(): int
    {
        $uid  = (int) $this->argument('uid');
        $role = (string) $this->argument('role');

        $model = config('laragram.auth.user.model');

        /** @var \Wekser\Laragram\Models\User|null $user */
        $user = $model::where('uid', $uid)->first();

        if ($user === null) {
            $this->error("User with Telegram ID [{$uid}] not found in the database.");
            $this->line('Make sure the user has started the bot at least once.');
            return self::FAILURE;
        }

        $previousRole = $user->role ?? 'user';
        $user->role   = $role;
        $user->save();

        $this->info("Role updated for user [{$user->first_name} ({$uid})]:");
        $this->line("  {$previousRole}  →  {$role}");

        return self::SUCCESS;
    }
}
