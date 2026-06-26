<?php
declare(strict_types=1);

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
use Wekser\Laragram\Exceptions\ClientResponseInvalidException;
use Wekser\Laragram\Services\TelegramErrorHandler;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * BotClient handles HTTP requests to Telegram Bot API.
 * 
 * @package Wekser\Laragram
 */
class BotClient
{
    /**
     * Telegram Bot API base URL.
     */
    private const API_BASE_URL = 'https://api.telegram.org/bot';

    /**
     * Upper bound (seconds) for request/connection timeouts — guards against a
     * caller accidentally setting a value that hangs the worker indefinitely.
     */
    private const MAX_TIMEOUT = 300;

    /**
     * Default cURL options.
     * Note: CURLOPT_TIMEOUT and CURLOPT_CONNECTTIMEOUT are intentionally absent —
     * they are always set from $this->timeout / $this->connectTimeout properties.
     */
    private const DEFAULT_CURL_OPTIONS = [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_USERAGENT      => 'Laragram Bot Client/2.0',
    ];

    /**
     * The bot token.
     */
    private string $token;

    /**
     * Logger instance.
     */
    private LoggerInterface $logger;

    /**
     * Telegram error handler.
     */
    private TelegramErrorHandler $errorHandler;

    /**
     * Custom cURL options.
     */
    private array $curlOptions = [];

    /**
     * Request timeout in seconds.
     */
    private int $timeout = 30;

    /**
     * Connection timeout in seconds.
     */
    private int $connectTimeout = 10;

    /**
     * BotClient Constructor
     *
     * @param string $token The bot token
     * @param LoggerInterface|null $logger Logger instance
     */
    public function __construct(string $token, ?LoggerInterface $logger = null)
    {
        $this->validateToken($token);
        $this->token = $token;
        $this->logger = $logger ?? new NullLogger();
        $this->errorHandler = new TelegramErrorHandler();
    }

    /**
     * Send a request to Telegram Bot API and return the response.
     *
     * @param string $method The API method name
     * @param array $data Request data
     * @return mixed The API response
     * @throws ClientResponseInvalidException
     */
    public function request(string $method, array $data = []): mixed
    {
        $this->validateMethod($method);
        
        $url = $this->buildUrl($method);
        $preparedData = $this->prepareData($data);
        
        $this->logger->info('Sending request to Telegram API', [
            'method' => $method,
            'data'   => $this->sanitizeDataForLogging($preparedData),
        ]);

        try {
            $response = $this->makeCurlRequest($url, $preparedData);
            $result   = $this->processResponse($response, $method, $data);

            $this->logger->info('Received response from Telegram API', [
                'method'  => $method,
                'success' => true,
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Error making request to Telegram API', [
                'method' => $method,
                'error'  => $e->getMessage(),
                'code'   => $e->getCode(),
            ]);

            throw $e;
        }
    }

    /**
     * Set custom cURL options.
     *
     * @param array $options cURL options
     * @return $this
     */
    public function setCurlOptions(array $options): self
    {
        $this->curlOptions = $options + $this->curlOptions;
        return $this;
    }

    /**
     * Set request timeout.
     *
     * @param int $timeout Timeout in seconds
     * @return $this
     */
    public function setTimeout(int $timeout): self
    {
        if ($timeout <= 0 || $timeout > self::MAX_TIMEOUT) {
            throw new \InvalidArgumentException(
                sprintf('Request timeout must be between 1 and %d seconds.', self::MAX_TIMEOUT)
            );
        }

        $this->timeout = $timeout;
        return $this;
    }

    /**
     * Set connection timeout.
     *
     * @param int $timeout Connection timeout in seconds
     * @return $this
     */
    public function setConnectTimeout(int $timeout): self
    {
        if ($timeout <= 0 || $timeout > self::MAX_TIMEOUT) {
            throw new \InvalidArgumentException(
                sprintf('Connection timeout must be between 1 and %d seconds.', self::MAX_TIMEOUT)
            );
        }

        $this->connectTimeout = $timeout;
        return $this;
    }

    /**
     * Get the bot token (masked for security).
     *
     * @return string Masked token
     */
    public function getMaskedToken(): string
    {
        return substr($this->token, 0, 10) . '...' . substr($this->token, -4);
    }

    /**
     * Validate the bot token format.
     *
     * @param string $token The token to validate
     * @throws ClientResponseInvalidException
     */
    private function validateToken(string $token): void
    {
        if (empty($token)) {
            throw new ClientResponseInvalidException('Bot token cannot be empty');
        }

        if (!preg_match('/^\d+:[A-Za-z0-9_-]+$/', $token)) {
            throw new ClientResponseInvalidException('Invalid bot token format');
        }
    }

    /**
     * Validate the API method name.
     *
     * @param string $method The method to validate
     * @throws ClientResponseInvalidException
     */
    private function validateMethod(string $method): void
    {
        if (empty($method)) {
            throw new ClientResponseInvalidException('API method cannot be empty');
        }

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $method)) {
            throw new ClientResponseInvalidException('Invalid API method format');
        }
    }

    /**
     * Process the API response.
     *
     * @param string $response Raw response from API
     * @param string $method The API method that was called
     * @return mixed Processed response
     * @throws ClientResponseInvalidException
     */
    private function processResponse(string $response, string $method, array $requestData = []): mixed
    {
        if (empty($response)) {
            throw new ClientResponseInvalidException('Empty response from Telegram API');
        }

        $decoded = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ClientResponseInvalidException(
                'Invalid JSON response from Telegram API: ' . json_last_error_msg()
            );
        }

        if (!isset($decoded['ok'])) {
            throw new ClientResponseInvalidException('Invalid response format from Telegram API');
        }

        if (!$decoded['ok']) {
            // Carry the outbound recipient into the error context so typed
            // unreachable-user exceptions (BotBlocked / UserDeactivated /
            // ChatNotFound) expose a real id. For private chats chat_id is the
            // user's uid, so it doubles as user_id when none was supplied.
            $chatId = $requestData['chat_id'] ?? null;

            throw $this->errorHandler->handleError([
                'error_code'  => $decoded['error_code']  ?? 0,
                'description' => $decoded['description'] ?? 'Unknown error',
                'parameters'  => $decoded['parameters']  ?? [],
            ], [
                'chat_id' => $chatId,
                'user_id' => $requestData['user_id'] ?? $chatId,
            ]);
        }

        return $decoded['result'] ?? $decoded;
    }

    /**
     * Make a cURL request to the API.
     *
     * @param string $url The request URL
     * @param array $data Request data
     * @return string Response body
     * @throws ClientResponseInvalidException
     */
    private function makeCurlRequest(string $url, array $data): string
    {
        $ch = curl_init();
        
        if ($ch === false) {
            throw new ClientResponseInvalidException('Failed to initialize cURL');
        }

        $options = $this->buildCurlOptions($url, $data);
        
        if (!curl_setopt_array($ch, $options)) {
            curl_close($ch);
            throw new ClientResponseInvalidException('Failed to set cURL options');
        }

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $errorCode = curl_errno($ch);
        
        curl_close($ch);

        if ($result === false) {
            throw new ClientResponseInvalidException(
                "cURL error: {$error}",
                $errorCode
            );
        }

        // Telegram returns JSON error bodies for 4xx/5xx — let processResponse()
        // parse them and dispatch to TelegramErrorHandler for typed exceptions.
        // Only throw here for transport-level failures (no body at all).
        if ($httpCode >= 400 && empty($result)) {
            throw new ClientResponseInvalidException(
                "HTTP error {$httpCode} with empty response body",
                $httpCode
            );
        }

        return $result;
    }

    /**
     * Build cURL options array.
     *
     * @param string $url The request URL
     * @param array $data Request data
     * @return array cURL options
     */
    private function buildCurlOptions(string $url, array $data): array
    {
        $options = $this->curlOptions + self::DEFAULT_CURL_OPTIONS;

        $options[CURLOPT_URL] = $url;
        $options[CURLOPT_POSTFIELDS] = $data;
        $options[CURLOPT_TIMEOUT] = $this->timeout;
        $options[CURLOPT_CONNECTTIMEOUT] = $this->connectTimeout;

        // Security-critical: user-supplied curlOptions must never be able to
        // weaken TLS verification or open unbounded redirects (SSRF). Re-apply
        // these AFTER the union merge so they always win.
        $options[CURLOPT_SSL_VERIFYPEER] = true;
        $options[CURLOPT_SSL_VERIFYHOST] = 2;
        $options[CURLOPT_MAXREDIRS]      = 3;

        return $options;
    }

    /**
     * Build URL for the API request.
     *
     * @param string $method The API method
     * @return string Complete API URL
     */
    private function buildUrl(string $method): string
    {
        return self::API_BASE_URL . $this->token . '/' . $method;
    }

    /**
     * Prepare data for the request.
     *
     * Removes null values and JSON-encodes any nested array values. Telegram
     * expects structured fields (reply_markup, entities, media, …) as JSON
     * strings, and passing a multidimensional array to CURLOPT_POSTFIELDS would
     * otherwise trigger an "Array to string conversion" error. CURLFile objects
     * (used for multipart file uploads) and scalars are passed through untouched.
     *
     * @param array $data Raw data
     * @return array Cleaned data
     */
    private function prepareData(array $data): array
    {
        $prepared = [];

        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }

            $prepared[$key] = is_array($value)
                ? json_encode($value, JSON_UNESCAPED_UNICODE)
                : $value;
        }

        return $prepared;
    }

    /**
     * Sanitize data for logging by removing sensitive information.
     *
     * @param array $data Data to sanitize
     * @return array Sanitized data
     */
    private function sanitizeDataForLogging(array $data): array
    {
        $sensitiveKeys = ['token', 'password', 'secret', 'key'];

        foreach ($data as $key => &$value) {
            if (in_array($key, $sensitiveKeys, true)) {
                $value = '***';
            } elseif (is_array($value)) {
                $value = $this->sanitizeDataForLogging($value);
            }
        }

        return $data;
    }

    /**
     * Test the connection to Telegram API.
     *
     * @return bool True if connection is successful
     */
    public function testConnection(): bool
    {
        try {
            $this->getApiInfo();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @throws ClientResponseInvalidException */
    public function getApiInfo(): array
    {
        return $this->request('getMe');
    }

}