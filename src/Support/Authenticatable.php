<?php

/*
 * This file is part of Laragram.
 *
 * (c) Sergey Lapin <hello@wekser.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Wekser\Laragram\Support;

trait Authenticatable
{
    /**
     * Get the unique identifier for the user.
     *
     * @return int
     */
    public function id()
    {
        return $this->current->id;
    }

    /**
     * Get the unique identifier in Telegram for the user.
     *
     * @return int
     */
    public function uid()
    {
        return $this->current->uid;
    }

    /**
     * Get the unique identifier in Telegram for the user.
     *
     * @return array
     */
    public function getLastPayload()
    {
        $payload = $this->current->sessions()->latest()->first()->payload;

        return empty($payload) ? null : ($this->isSecurePayload() ? decrypt($payload) : json_decode($payload, true));
    }
}