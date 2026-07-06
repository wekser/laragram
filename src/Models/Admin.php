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

namespace Wekser\Laragram\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A Laragram admin-panel account. Distinct from Models\User (the Telegram bot
 * user) — these are the humans who log into the web dashboard, authenticated by
 * the "laragram_admin" session guard against the laragram_admins table.
 *
 * @property int $id
 * @property string|null $name
 * @property string $username
 * @property string $password
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Admin extends Authenticatable
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'username',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * The "hashed" cast transparently bcrypt-hashes the password on assignment,
     * so callers may pass a plaintext password and never hash it themselves.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'password' => 'hashed',
    ];

    /**
     * Resolve the table name from config so host apps can rename it.
     */
    public function getTable(): string
    {
        return (string) config('laragram.admin.table', 'laragram_admins');
    }
}
