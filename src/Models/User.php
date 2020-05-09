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

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $uid
 * @property string $first_name
 * @property string|null $last_name
 * @property string|null $username
 * @property string|null $language
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|\Session[] $sessions
 */
class User extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'laragram_users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'uid', 'first_name', 'last_name', 'username', 'language'
    ];

    /**
     * Get the sessions associated with the user.
     */
    public function sessions()
    {
        return $this->hasMany('Wekser\Laragram\Models\Session');
    }
}
