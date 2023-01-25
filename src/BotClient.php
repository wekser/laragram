<?php

/*
 * This file is part of Laragram.
 *
 * (c) Sergey Lapin <me@wekser.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Wekser\Laragram;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Wekser\Laragram\Exceptions\ClientResponseInvalidException;

class BotClient
{
    /**
     * Telegram Bot API url.
     *
     * @var string
     */
    private string $api = 'https://api.telegram.org/bot';

    /**
     * The bot token.
     *
     * @var string
     */
    private string $token;

    /**
     * BotClient Constructor
     *
     * @param string $token
     */
    public function __construct(string $token)
    {
        $this->token = $token;
    }

    /**
     * Send a request to Telegram Bot API and return the response.
     *
     * @param string $method
     * @param array $data
     * @return mixed
     */
    public function request(string $method, array $data = [])
    {
        return $this->response($this->curl($this->buildUrl($method), $this->prepareData($data)));
    }

    /**
     * Prepare a response and return the result.
     *
     * @param string $response
     * @return mixed
     * @throws ClientResponseInvalidException
     */
    protected function response(string $response)
    {
        if (!Str::isJson($response)) throw new ClientResponseInvalidException();

        $result = json_decode($response, true);

        if (Arr::get($result, 'ok') === null) throw new ClientResponseInvalidException($response);

        return $result['result'] ?? $result;
    }

    /**
     * The CURL request implementation.
     *
     * @param string $url
     * @param array $data
     * @return string
     * @throws ClientResponseInvalidException
     */
    private function curl(string $url, array $data): string
    {
        $options = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_SSL_VERIFYPEER => false,
        ];

        $ch = curl_init($url);

        curl_setopt_array($ch, $options);

        $result = curl_exec($ch);

        curl_close($ch);

        if ($result === false) throw new ClientResponseInvalidException('Curl error: ' . curl_error($ch), curl_errno($ch));

        return $result;
    }

    /**
     * Build url for request.
     *
     * @param string $method
     * @return string
     */
    protected function buildUrl(string $method): string
    {
        return $this->api . $this->token . '/' . $method;
    }

    /**
     * Prepare data for request.
     *
     * @param array $data
     * @return array
     */
    protected function prepareData(array $data)
    {
        return collect($data)->reject(function ($value, $key) {
            return is_null($value);
        })->all();
    }
}