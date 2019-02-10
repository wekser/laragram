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

use GuzzleHttp\Client as Guzzle;
use Wekser\Laragram\Exceptions\TokenInvalidException;

class BotClient
{
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
     * BotClient Constructor
     *
     * @param array $configuration
     * @throws TokenInvalidException
     */
    public function __construct(array $configuration)
    {
        $this->token = array_get($configuration, 'token');

        if (empty($this->getToken())) {
            throw new TokenInvalidException();
        }

        $this->prefix = array_get($configuration, 'prefix');
        $this->secret = array_get($configuration, 'secret');
    }

    /**
     * Get bot token.
     *
     * @return string
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * Send request to Telegram API.
     *
     * @param string $method
     * @param array|null $parameters
     * @return array|string|null
     * @throws Exception
     */
    public function request(string $method, $parameters = [])
    {
        $guzzle = new Guzzle();

        try {
            $response = $guzzle->request('POST', $this->buildUrl($method), ['form_params' => $parameters]);
            $body = json_decode($response->getBody(), true);
        } catch (Exception $e) {
            return $this->response(['ok' => false, 'error_code' => $e->getCode(), 'description' => $e->getMessage()]);
        }

        return $this->response($body);
    }

    /**
     * Build url for request.
     *
     * @param string $method
     * @return string
     */
    protected function buildUrl(string $method)
    {
        return static::API_URL . $this->getToken() . '/' . $method;
    }

    /**
     * Prepare response.
     *
     * @param array $body
     * @return array|string|null
     */
    protected function response(array $body)
    {
        if (array_get($body, 'ok')) {
            return is_array(array_get($body, 'result')) ? array_get($body, 'result') : $body;
        }

        return $body;
    }

    /**
     * Get router prefix.
     *
     * @return string|null
     */
    public function getPrefix(): ?string
    {
        return $this->prefix;
    }

    /**
     * Get router secret.
     *
     * @return string
     */
    public function getSecret(): ?string
    {
        return $this->secret;
    }
}