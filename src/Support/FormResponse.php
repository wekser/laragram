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

use Wekser\Laragram\BotRequest;
use Wekser\Laragram\BotResponse;

class FormResponse
{
    /**
     * Prepares the Response before it is sent to the client.
     *
     * @param \Wekser\Laragram\BotResponse $response
     * @param \Wekser\Laragram\BotRequest $request
     * @return array
     */
    public function getResponse(BotResponse $response, BotRequest $request): array
    {
        $request = $request->getRequest();

        $request['view'] = $response->contents;
        $request['state'] = $response->state ?? $request['state'];

        return $request;
    }
}