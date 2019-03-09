<?php

/*
 * This file is part of Laragram.
 *
 * (c) Sergey Lapin <hello@wekser.com>
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
 * @property string $hook
 * @property string $controller
 * @property string $method
 * @property string $last_state
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \Wekser\Laragram\Models\User $user
 */
class Session extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'laragram_sessions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id', 'update_id', 'event', 'listener', 'hook', 'controller', 'method', 'last_state'
    ];

    /**
     * Get the user that owns the hook.
     */
    public function user()
    {
        return $this->belongsTo('Wekser\Laragram\Models\User');
    }
}
