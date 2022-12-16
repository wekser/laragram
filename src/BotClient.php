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

use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use Psr\Http\Message\ResponseInterface;
use Wekser\Laragram\Exceptions\ClientResponseInvalidException;
use Wekser\Laragram\Exceptions\FileInvalidException;

class BotClient
{
    const API_URL = 'https://api.telegram.org/bot';

    /**
     * Connection timeout of the request in seconds.
     *
     * @var int
     */
    protected int $connectTimeOut;

    /**
     * Indicates if the request to Telegram will be asynchronous (non-blocking).
     *
     * @var bool
     */
    protected bool $isAsyncRequest;

    /**
     * HTTP Request Method.
     *
     * @var string
     */
    protected string $method;

    /**
     * Timeout of the request in seconds.
     *
     * @var int
     */
    protected int $timeOut;

    /**
     * The bot token.
     *
     * @var string
     */
    protected string $token;

    /**
     * BotClient Constructor
     *
     * @param string $token
     * @param array $config
     */
    public function __construct(string $token, array $config)
    {
        $this->token = $token;
        $this->method = $config['method'];
        $this->connectTimeOut = $config['connectTimeOut'];
        $this->isAsyncRequest = $config['isAsyncRequest'];
        $this->timeOut = $config['timeOut'];
    }

    /**
     * Send a request to Telegram Bot API and return the response.
     *
     * @param string $method
     * @param array $params
     * @param bool $fileUpload
     * @return mixed
     */
    public function request(string $method, array $params = [], bool $fileUpload = false)
    {
        return $this->response((new Client())->{$this->isAsyncRequest ? 'requestAsync' : 'request'}(
            $this->method,
            $this->buildUrl($method),
            $this->buildOptions($params, $fileUpload)
        ));
    }

    /**
     * Prepare a response and return the result.
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     * @return mixed
     * @throws ClientResponseInvalidException
     */
    protected function response(ResponseInterface $response)
    {
        if ($response->getStatusCode() !== 200) {
            throw new ClientResponseInvalidException();
        }

        $body = trim((string)$response->getBody());

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
     * @param void $string
     * @return bool
     */
    protected function isJson($string): bool
    {
        return is_string($string) && is_array(json_decode($string, true));
    }

    /**
     * Build url for request.
     *
     * @param string $method
     * @return string
     */
    protected function buildUrl(string $method): string
    {
        return $this->getApiUrl() . $this->getToken() . '/' . $method;
    }

    /**
     * Get Telegram Bot API url.
     *
     * @return string
     */
    protected function getApiUrl(): string
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
    protected function buildOptions(array $params = [], bool $fileUpload = false): array
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
    protected function prepareParameters(array $params = [], bool $fileUpload = false): array
    {
        $data = collect($params)->reject(function ($value) {
            return is_null($value);
        });

        return $fileUpload ? ['multipart' => $data->map(function ($contents, $name) {
            if (!is_resource($contents) && $this->isValidFileOrUrl($name, $contents)) {
                $contents = $this->inputFile($contents);
            }
            return ['name' => $name, 'contents' => $contents];
        })->all()] : ['form_params' => $data->all()];
    }

    /**
     * Determines if the string passed to be uploaded is a valid
     * file on the local file system, or a valid remote URL.
     *
     * @param string $name
     * @param mixed $contents
     * @return bool
     */
    protected function isValidFileOrUrl(string $name, string $contents): bool
    {
        if ($name == 'url') return false;

        if ($name == 'certificate') return true;

        if (is_file($contents) || is_readable($contents)) return true;

        return filter_var($contents, FILTER_VALIDATE_URL);
    }

    /**
     * Opens file stream.
     *
     * @param mixed $contents
     * @return resource
     * @throws FileInvalidException
     */
    protected function inputFile(mixed $contents)
    {
        if (is_string($contents) && (!(preg_match('/^(https|ftp):\/\/.*/', $contents) == 1) || !strpos(get_headers($contents)[0], '200'))) {
            throw new FileInvalidException($contents);
        }

        return fopen($contents, 'r');
    }
}