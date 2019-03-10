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
            $request['state'] = $response->state ?? $request['state'];
        } elseif (is_string($response)) {
            $request['view'] = $this->createBasicResponse($response);
            $request['state'] = $request['state'];
        } elseif (empty($response)) {
            throw new ResponseEmptyException();
        } else {
            throw new ResponseInvalidException($request['method'], $request['controller']);
        }

        return $request;
    }

    /**
     * Create the basic view response.
     *
     * @param string $response
     * @return array
     */
    protected function createBasicResponse(string $response): array
    {
        $user = BotAuth::user();

        $view['method'] = 'sendMessage';
        $view['chat_id'] = $user->uid;
        $view['text'] = $response;

        return $view;
    }
}