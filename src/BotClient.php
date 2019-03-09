<?php

/*
 * This file is part of Laragram.
 *
 * (c) Sergey Lapin <hello@wekser.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Wekser\Laragram;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use Psr\Http\Message\ResponseInterface;
use Wekser\Laragram\Exceptions\ClientResponseInvalidException;
use Wekser\Laragram\Exceptions\FileInvalidException;
use Wekser\Laragram\Exceptions\TokenInvalidException;
use Wekser\Laragram\Support\Wrapable;

class BotClient
{
    use Wrapable;

    const API_URL = 'https://api.telegram.org/bot';

    /**
     * The bot token.
     *
     * @var string
     */
    protected $token;

    /**
     * The bot prefix.
     *
     * @var string
     */
    protected $prefix;

    /**
     * The bot secret.
     *
     * @var string
     */
    protected $secret;

    /**
     * Indicates if the request to Telegram will be asynchronous (non-blocking).
     *
     * @var bool
     */
    protected $isAsyncRequest = false;

    /**
     * Timeout of the request in seconds.
     *
     * @var int
     */
    protected $timeOut = 60;

    /**
     * Connection timeout of the request in seconds.
     *
     * @var int
     */
    protected $connectTimeOut = 10;

    /**
     * BotClient Constructor
     *
     * @param string $token
     * @param string $prefix
     * @param string $secret
     * @throws TokenInvalidException
     */
    public function __construct($token, $prefix, $secret)
    {
        if (empty($token)) {
            throw new TokenInvalidException();
        }

        $this->token = $token;
        $this->prefix = $prefix;
        $this->secret = $secret;
    }

    /**
     * Get a router prefix.
     *
     * @return string|null
     */
    protected function getPrefix(): ?string
    {
        return $this->prefix;
    }

    /**
     * Get a router secret.
     *
     * @return string
     */
    protected function getSecret(): ?string
    {
        return $this->secret;
    }

    /**
     * Send a request to Telegram Bot API and return the response.
     *
     * @param string $method
     * @param array $params
     * @param bool $fileUpload
     * @return array
     */
    protected function request(string $method, array $params = [], $fileUpload = false)
    {
        $request = $this->isAsyncRequest ? 'requestAsync' : 'request';

        $client = new Client();

        return $this->response($client->{$request}('POST', $this->buildUrl($method), $this->buildOptions($params)));
    }

    /**
     * Prepare a response and return the result.
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     * @return array
     * @throws ClientResponseInvalidException
     */
    protected function response(ResponseInterface $response)
    {
        if ($response->getStatusCode() !== 200) {
            throw new ClientResponseInvalidException();
        }

        $body = $response->getBody();

        if (!$this->isJson($body)) {
            throw new ClientResponseInvalidException();
        }

        $result = json_decode($body, true);

        if (!isset($result['ok'])) {
            throw new ClientResponseInvalidException();
        }

        return $result['ok'] ? $result['result'] : $result;
    }

    /**
     * Finds whether a variable is a json string.
     *
     * @param string $string
     * @return bool
     */
    protected function isJson($string)
    {
        return is_string($string) && is_array(json_decode($string, true)) ? true : false;
    }

    /**
     * Build url for request.
     *
     * @param string $method
     * @return string
     */
    protected function buildUrl(string $method)
    {
        return $this->getApiUrl() . $this->getToken() . '/' . $method;
    }

    /**
     * Get Telegram Bot API url.
     *
     * @return string
     */
    protected function getApiUrl()
    {
        return static::API_URL;
    }

    /**
     * Get the bot token.
     *
     * @return string
     */
    protected function getToken(): string
    {
        return $this->token;
    }

    /**
     * Build options for request.
     *
     * @param array $params
     * @param bool $fileUpload
     * @return array
     */
    protected function buildOptions(array $params = [], $fileUpload = false)
    {
        $settings = [
            'connect_timeout' => $this->connectTimeOut,
            'headers' => [
                'User-Agent' => 'Wekser\Laragram BotClient'
            ],
            'timeout' => $this->timeOut
        ];

        return array_merge($settings, $this->prepareParameters($params, $fileUpload));
    }

    /**
     * Prepare parameters.
     *
     * @param array $params
     * @param bool $fileUpload
     * @return array
     */
    protected function prepareParameters(array $params = [], $fileUpload = false)
    {
        if ($fileUpload) {
            $multipart_params = collect($params)->reject(function ($value) {
                return is_null($value);
            })->map(function ($contents, $name) {
                if (!is_resource($contents) && $this->isValidFileOrUrl($name, $contents)) {
                    $contents = $this->inputFile($contents);
                }
                return ['name' => $name, 'contents' => $contents];
            })->values()->all();

            $parameters = ['multipart' => $multipart_params];
        } else {
            $form_params = collect($params)->reject(function ($value) {
                return is_null($value);
            })->values()->all();

            $parameters = ['form_params' => $form_params];
        }

        return $parameters;
    }

    /**
     * Determines if the string passed to be uploaded is a valid
     * file on the local file system, or a valid remote URL.
     *
     * @param string $name
     * @param string $contents
     * @return bool
     */
    protected function isValidFileOrUrl($name, $contents)
    {
        if ($name == 'url') {
            return false;
        }

        if ($name == 'certificate') {
            return true;
        }

        if (is_readable($contents)) {
            return true;
        }

        return filter_var($contents, FILTER_VALIDATE_URL);
    }

    /**
     * Opens file stream.
     *
     * @param string $contents
     * @return resource
     * @throws FileInvalidException
     */
    protected function inputFile($contents)
    {
        if (is_resource($contents)) {
            return $contents;
        }

        if (!preg_match('/^(https?|ftp):\/\/.*/', $contents) === 1 && !is_readable($contents)) {
            throw new FileInvalidException($contents);
        }

        return Psr7\try_fopen($contents, 'r');
    }
}