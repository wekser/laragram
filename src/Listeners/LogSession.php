<?php

/*
 * This file is part of Laragram.
 *
 * (c) Sergey Lapin <me@wekser.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Wekser\Laragram\Listeners;

use Wekser\Laragram\Events\CallbackFormed;

class LogSession
{
    /**
     * Session model.
     */
    protected $model;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        $this->model = config('laragram.auth.session.model');
    }

    /**
     * Handle the event.
     *
     * @param CallbackFormed $event
     * @return void
     */
    public function handle(CallbackFormed $event)
    {
        (new ($this->model))::firstOrCreate(
            ['user_id' => $event->user->id],
            [
                'update_id' => $event->output['update']['id'],
                'station' => $event->output['response']['redirect'],
                'payload' => ['route' => $event->output['route']],
                'last_activity' => now()
            ]
        );
    }
}
