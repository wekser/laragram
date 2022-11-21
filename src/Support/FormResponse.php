<?php

/*
 * This file is part of Laragram.
 *
 * (c) Sergey Lapin <me@wekser.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Wekser\Laragram\Support;

use Wekser\Laragram\BotRequest;
use Wekser\Laragram\BotResponse;
use Wekser\Laragram\Exceptions\ResponseEmptyException;
use Wekser\Laragram\Exceptions\ResponseInvalidException;
use Wekser\Laragram\Facades\BotAuth;

class FormResponse
{
    /**
     * Prepares the Response before it is sent to the client.
     *
     * @param \Wekser\Laragram\BotRequest $request
     * @param \Wekser\Laragram\BotResponse|string $response
     * @return array
     * @throws ResponseEmptyException|ResponseInvalidException
     */
    public function getResponse(BotRequest $request, $response): array
    {
        $request = $request->getRequest();

        if ($response instanceof BotResponse) {
            $request['view'] = $response->contents ?? [];
            $request['location'] = $response->location ?? $request['route']['location'];
        } elseif (is_string($response)) {
            $request['view'] = ['method' => 'sendMessage', 'chat_id' => BotAuth::user()->uid, 'text' => $response];
            $request['location'] = $request['route']['location'];
        } elseif (empty($response)) {
            throw new ResponseEmptyException();
        } else {
            throw new ResponseInvalidException($request['route']['uses']);
        }

        return $request;
    }
}