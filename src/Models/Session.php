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
 * @property string $event
 * @property string $listener
 * @property string $contains
 * @property string $uses
 * @property string $location
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read User $user
 */
class Session extends Model
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
        'user_id', 'update_id', 'event', 'listener', 'contains', 'uses', 'location',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'contains' => 'array',
    ];

    /**
     * Session Model Constructor.
     */
    public function __construct()
    {
        $this->table = config('laragram.auth.session.table');
    }

    /**
     * Get the user that owns the hook.
     */
    public function user()
    {
        return $this->belongsTo(config('laragram.auth.user.model'));
    }
}
