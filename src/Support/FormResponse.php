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
     * @param string|\Wekser\Laragram\BotResponse $response
     * @return array|null
     * @throws ResponseInvalidException
     */
    public function getResponse(BotRequest $request, $response): ?array
    {
        $output = $request->getRequest();

        if ($response instanceof BotResponse) {
            $output['response']['view'] = $response->contents ?? [];
            $output['response']['redirect'] = $response->station ?? $request['route']['form'];
        } elseif (is_string($response)) {
            $output['response']['view'] = ['method' => 'sendMessage', 'chat_id' => BotAuth::user()->uid, 'text' => $response];
            $output['response']['redirect'] = $request['route']['form'];
        } elseif (empty($response)) {
            return null;
        } else {
            throw new ResponseInvalidException($request['route']['uses']);
        }

        return $output;
    }
}