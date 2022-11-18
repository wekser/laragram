<?php

/*
 * This file is part of Laragram.
 *
 * (c) Sergey Lapin <me@wekser.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Wekser\Laragram\Events;

use Illuminate\Queue\SerializesModels;

class CallbackFormed
{
    use SerializesModels;

    /**
     * @return User
     */
    public $user;

    /**
     * @return array
     */
    public $response;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($user, $response)
    {
        $this->user = $user;
        $this->response = $response;
    }
}
