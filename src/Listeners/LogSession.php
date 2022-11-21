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

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Wekser\Laragram\Events\CallbackFormed;
use Wekser\Laragram\Models\Session;

class LogSession
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param CallbackFormed $event
     * @return void
     */
    public function handle(CallbackFormed $event)
    {
        $session = new Session();
        $session->user_id = $event->user->id;
        $session->update_id = $event->response['update']['id'];
        $session->event = $event->response['route']['event'];
        $session->listener = $event->response['route']['listener'];
        $session->contains = json_encode($event->response['route']['contains']);
        $session->uses = $event->response['route']['uses'];
        $session->location = $event->response['route']['location'];
        $session->save();
    }
}
