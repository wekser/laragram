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
 * @property int $user_id
 * @property int $update_id
 * @property string $station
 * @property array $payload
 * @property \Carbon\Carbon $activity
 * @property-read User $user
 */
class Session extends Model
{
    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id', 'update_id', 'station', 'payload', 'activity',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'payload' => 'array',
    ];

    /**
     * Get name model table.
     */
    public function getTable()
    {
        return config('laragram.auth.session.table', parent::getTable());
    }

    /**
     * Get the user that owns the hook.
     */
    public function user()
    {
        return $this->belongsTo(config('laragram.auth.user.model'));
    }
}
