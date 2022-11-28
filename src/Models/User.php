<?php

/*
 * This file is part of Laragram.
 *
 * (c) Sergey Lapin <me@wekser.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Wekser\Laragram\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property string $id
 * @property int $uid
 * @property string $first_name
 * @property string|null $last_name
 * @property string|null $username
 * @property array $settings
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|\Session[] $sessions
 */
class User extends Authenticatable
{
    use HasFactory, HasUlids, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'uid', 'first_name', 'last_name', 'username', 'settings',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'settings' => AsArrayObject::class,
    ];

    /**
     * Get name model table.
     */
    public function getTable()
    {
        return config('laragram.auth.user.table', parent::getTable());
    }

    /**
     * Get the current user sessions.
     */
    public function session()
    {
        return $this->sessions()->where('activity', '<=', now()->addMinutes(config('laragram.auth.session.lifetime')))->first();
    }

    /**
     * Get the user sessions.
     */
    public function sessions()
    {
        return $this->hasMany(config('laragram.auth.session.model'))->orderBy('activity', 'desc');
    }
}
