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
 * Stateless in-memory driver: builds a transient User without any database I/O.
 *
 * Station is always 'start' when this driver is active (no session persistence).
 */
class ArrayAuthDriver implements AuthDriverInterface
{
    /**
     * @param class-string<User> $userModel
     */
    public function __construct(private readonly string $userModel) {}

    /**
     * Instantiate a User model with sender data — not saved to the database.
     */
    public function resolveUser(array $sender, string $language): User
    {
        return new $this->userModel([
            'uid'        => $sender['id'],
            'first_name' => $sender['first_name'],
            'last_name'  => $sender['last_name'],
            'username'   => $sender['username'],
            'settings'   => ['language' => $language],
        ]);
    }

    /**
     * Delegate to the User model's is_active column.
     * In-memory users default to active (is_active is null → true via User::isActive()).
     */
    public function isActive(User $user): bool
    {
        return $user->isActive();
    }
}
