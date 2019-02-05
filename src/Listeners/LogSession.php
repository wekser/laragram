<?php

/*
 * This file is part of Laragram.
 *
 * (c) Sergey Lapin <hello@wekser.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Wekser\Laragram\Listeners;

use Wekser\Laragram\Models\Session;
use Wekser\Laragram\Events\CallbackFormed;
use Wekser\Laragram\Facades\BotAuth;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

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
        $session->update_id = $event->response['update_id'];
        $session->event = $event->response['event'];
        $session->listener = $event->response['listener'];
        $session->hook = $event->response['hook'];
        $session->controller = $event->response['controller'];
        $session->method = $event->response['method'];
        $session->payload = BotAuth::isSecurePayload() ? encrypt($event->response['all']) : json_encode($event->response['all']);
        $session->last_state = $event->response['state'];
        $session->save();
    }
}
