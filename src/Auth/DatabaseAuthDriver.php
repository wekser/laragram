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
 * Persists users and their settings to the database on every request.
 */
class DatabaseAuthDriver implements AuthDriverInterface
{
    /**
     * @param class-string<User> $userModel
     */
    public function __construct(private readonly string $userModel) {}

    /**
     * Upsert the user's profile fields atomically, then sync settings.
     *
     * updateOrCreate wraps the INSERT-or-UPDATE in a single statement, eliminating
     * the create/save gap of firstOrCreate. Settings are merged after the upsert
     * so existing keys (e.g. theme, notifications) are preserved on every request.
     *
     * The settings merge is a read-modify-write: two updates from the SAME user
     * arriving concurrently could each read settings before the other's write and
     * the later save() wins (last-write-wins on the JSON column). In practice this
     * is harmless — the only field written here is `language`, and the queued path
     * already serialises per-user via WithoutOverlapping (see ProcessTelegramUpdate).
     * A fully atomic update would need DB-level JSON operations and is out of scope.
     */
    public function resolveUser(array $sender, string $language): User
    {
        /** @var User $user */
        $user = $this->userModel::updateOrCreate(
            ['uid' => $sender['id']],
            [
                'first_name' => $sender['first_name'],
                'last_name'  => $sender['last_name'],
                'username'   => $sender['username'],
            ]
        );

        // Merge settings only when the language actually changed — avoids
        // a second UPDATE on every request when nothing has changed.
        $currentLang = $user->settings?->get('language');

        if ($currentLang !== $language) {
            $user->settings = ($user->settings ?? collect())->merge(['language' => $language]);
            $user->save();
        }

        return $user;
    }

    /**
     * Delegate to the User model's is_active column.
     */
    public function isActive(User $user): bool
    {
        return $user->isActive();
    }
}
