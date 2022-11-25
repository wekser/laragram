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
        $session = new ($this->model)();
        $session->user_id = $event->user->id;
        $session->update_id = $event->output['update']['id'];
        $session->station = $event->output['response']['redirect'];
        $session->payload = ['route' => $event->output['route']];
        $session->activity = now();
        $session->save();
    }
}
