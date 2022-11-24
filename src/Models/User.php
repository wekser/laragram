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
 * @property array $settings
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
    protected $table;

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
        'settings' => 'array',
    ];

    /**
     * User Model Constructor.
     */
    public function __construct()
    {
        $this->table = config('laragram.auth.user.table');
    }

    /**
     * The attributes that should be cast.
     *
     * @param null $key
     * @param null $value
     * @param bool $saving
     * @return mixed
     */
    public function setting($key = null, $value = null, bool $saving = false)
    {
        if ($saving && !empty($key)) {
            return $this->setJsonColumn('settings', $key, $value);
        }
        else {
            return $this->getJsonColumn('settings', $key, $value);
        }
    }

    /**
     * Get the sessions associated with the user.
     */
    public function sessions()
    {
        return $this->hasMany(config('laragram.auth.session.model'))->orderBy('updated_at', 'desc');
    }
}
