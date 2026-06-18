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

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property int $id
 * @property int $uid
 * @property string $first_name
 * @property string|null $last_name
 * @property string|null $username
 * @property array $settings
 * @property string $role
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|\Session[] $sessions
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'uid', 'first_name', 'last_name', 'username', 'settings', 'role', 'is_active', 'deactivated_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'settings' => AsCollection::class,
        'is_active' => 'boolean',
        'deactivated_at' => 'datetime',
    ];

    /**
     * Get name model table.
     */
    public function getTable(): string
    {
        return config('laragram.auth.user.table', parent::getTable());
    }

    /**
     * Get the most recent active session within the configured lifetime.
     */
    public function session(): ?Session
    {
        return $this->sessions()
            ->where('last_activity', '>=', now()->subMinutes(config('laragram.auth.session.lifetime')))
            ->first();
    }

    /**
     * Get all user sessions ordered by most recent activity.
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(config('laragram.auth.session.model'))->orderByDesc('last_activity');
    }

    /**
     * Check if user is active.
     */
    public function isActive(): bool
    {
        return $this->is_active ?? true;
    }

    /**
     * Deactivate user.
     */
    public function deactivate(): bool
    {
        return $this->update([
            'is_active' => false,
            'deactivated_at' => now(),
        ]);
    }

    /**
     * Activate user.
     */
    public function activate(): bool
    {
        return $this->update([
            'is_active' => true,
            'deactivated_at' => null,
        ]);
    }

    /**
     * Scope for active users.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for inactive users.
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }

    /**
     * Check if the user has the given role (or one of the given roles).
     *
     * @param string|string[] $role
     */
    public function hasRole(string|array $role): bool
    {
        return in_array($this->role ?? 'user', (array) $role, true);
    }

    /**
     * Check if the user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Scope for users with a specific role.
     */
    public function scopeByRole(Builder $query, string $role): Builder
    {
        return $query->where('role', $role);
    }
}
