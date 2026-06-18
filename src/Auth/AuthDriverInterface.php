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

namespace Wekser\Laragram\Auth;

use Wekser\Laragram\Models\User;

/**
 * Contract for authentication drivers.
 *
 * Each driver is responsible for resolving (finding or creating) a User
 * from Telegram sender data and determining whether that user may interact
 * with the bot.
 */
interface AuthDriverInterface
{
    /**
     * Resolve (find or create) a User from the validated sender data.
     *
     * @param  array{id: int, first_name: string, last_name: string|null, username: string|null, language_code: string|null} $sender
     * @param  string $language  Resolved language code for the user.
     */
    public function resolveUser(array $sender, string $language): User;

    /**
     * Determine whether the given user is allowed to interact with the bot.
     */
    public function isActive(User $user): bool;
}
