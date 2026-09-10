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
use Wekser\Laragram\Exceptions\TransportException;
use Wekser\Laragram\Services\TelegramErrorHandler;
use Wekser\Laragram\Support\ReplyMarkup;
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
     * Upper bound for the retry count a caller may configure.
     */
    private const MAX_RETRIES = 5;

    /**
     * Upper bound (milliseconds) for the base retry delay.
     */
    private const MAX_RETRY_DELAY = 10000;

    /**
     * cURL error numbers worth retrying: these mean the call failed on the
     * network, not on Telegram's side, so another attempt has a real chance.
     *
     * A Telegram API error (ok: false) is never in here — it is deterministic
     * and repeating it would only waste a round-trip. Neither are the codes for
     * a connection lost mid-flight (52 GOT_NOTHING, 55 SEND_ERROR, 56
     * RECV_ERROR): by the time they can fire the request body is already on the
     * wire, so Telegram may well have acted on it and a retry would deliver the
     * same message twice. Without an idempotency key that is not a trade worth
     * making — those surface as a TransportException on the first attempt.
     */
    private const RETRYABLE_CURL_ERRORS = [
        5,  // CURLE_COULDNT_RESOLVE_PROXY
        6,  // CURLE_COULDNT_RESOLVE_HOST
        7,  // CURLE_COULDNT_CONNECT
        28, // CURLE_OPERATION_TIMEDOUT — includes "SSL connection timeout"
        35, // CURLE_SSL_CONNECT_ERROR
    ];

    /**
     * The subset of RETRYABLE_CURL_ERRORS that can only fire before a byte of
     * the request body has left the machine, so a retry can never duplicate a
     * message. Only errno 28 is left out: a timeout can land either side of the
     * send, so it needs the runtime check in isSafeToRetry().
     */
    private const CONNECT_PHASE_CURL_ERRORS = [5, 6, 7, 35];

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
     * Extra attempts made after a transport-level failure (0 = no retry).
     *
     * This mirrors the config default on purpose. Laravel's mergeConfigFrom()
     * merges only at the top level, so a host app that published config/laragram.php
     * before these keys existed replaces the whole 'telegram' block and passes
     * none of them — the client must still behave as documented.
     */
    private int $retries = 2;

    /**
     * Base delay between retries in milliseconds; doubled on every attempt.
     */
    private int $retryDelay = 300;

    /**
     * Force an IP version for outgoing requests: 4, 6, or null for automatic.
     */
    private ?int $ipVersion = null;

    /**
     * Outgoing proxy for API calls, e.g. "http://user:pass@host:3128".
     */
    private ?string $proxy = null;

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

        $data = $this->sanitizeReplyMarkup($method, $data);

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
     * Set how many extra attempts are made after a transport-level failure.
     *
     * @param int $retries Number of retries (0 disables retrying)
     * @return $this
     */
    public function setRetries(int $retries): self
    {
        if ($retries < 0 || $retries > self::MAX_RETRIES) {
            throw new \InvalidArgumentException(
                sprintf('Retries must be between 0 and %d.', self::MAX_RETRIES)
            );
        }

        $this->retries = $retries;
        return $this;
    }

    /**
     * Set the base delay between retries. The delay doubles on each attempt,
     * so 300ms yields 300ms, 600ms, 1200ms, …
     *
     * @param int $milliseconds Base delay (0 retries immediately)
     * @return $this
     */
    public function setRetryDelay(int $milliseconds): self
    {
        if ($milliseconds < 0 || $milliseconds > self::MAX_RETRY_DELAY) {
            throw new \InvalidArgumentException(
                sprintf('Retry delay must be between 0 and %d milliseconds.', self::MAX_RETRY_DELAY)
            );
        }

        $this->retryDelay = $milliseconds;
        return $this;
    }

    /**
     * Pin outgoing requests to an IP version.
     *
     * Hosts with broken or blackholed IPv6 routing to api.telegram.org stall in
     * the TLS handshake until the connect timeout expires; forcing 4 skips the
     * AAAA record entirely.
     *
     * @param int|null $version 4, 6, or null for automatic
     * @return $this
     */
    public function setIpVersion(?int $version): self
    {
        if ($version !== null && $version !== 4 && $version !== 6) {
            throw new \InvalidArgumentException('IP version must be 4, 6 or null.');
        }

        $this->ipVersion = $version;
        return $this;
    }

    /**
     * Route API calls through a proxy (null or an empty string disables it).
     *
     * @param string|null $proxy Proxy URL accepted by CURLOPT_PROXY
     * @return $this
     */
    public function setProxy(?string $proxy): self
    {
        $this->proxy = ($proxy === null || $proxy === '') ? null : $proxy;
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
     * Make a cURL request to the API, retrying transport-level failures.
     *
     * A failure that never reached Telegram (DNS, connect, TLS handshake,
     * timeout, dropped connection) is repeated up to $retries times, but only
     * when isSafeToRetry() can prove the request body never went out — see
     * there for why that matters.
     *
     * @param string $url The request URL
     * @param array $data Request data
     * @return string Response body
     * @throws TransportException When the transport failed and no retry is left
     * @throws ClientResponseInvalidException
     */
    private function makeCurlRequest(string $url, array $data): string
    {
        $attempt = 0;

        while (true) {
            $attempt++;

            $outcome = $this->executeCurl($url, $data);

            if ($outcome['result'] !== false) {
                // Telegram returns JSON error bodies for 4xx/5xx — let processResponse()
                // parse them and dispatch to TelegramErrorHandler for typed exceptions.
                // Only throw here for transport-level failures (no body at all).
                if ($outcome['httpCode'] >= 400 && empty($outcome['result'])) {
                    throw new ClientResponseInvalidException(
                        "HTTP error {$outcome['httpCode']} with empty response body",
                        $outcome['httpCode']
                    );
                }

                return (string) $outcome['result'];
            }

            $canRetry = $attempt <= $this->retries
                && $this->isSafeToRetry($outcome['errorCode'], $outcome['pretransferTime']);

            if (!$canRetry) {
                throw new TransportException(
                    "cURL error: {$outcome['error']}",
                    $outcome['errorCode'],
                    $attempt
                );
            }

            $this->logger->warning('Retrying Telegram API request after a transport failure', [
                'attempt' => $attempt,
                'error'   => $outcome['error'],
                'code'    => $outcome['errorCode'],
            ]);

            $this->sleepBeforeRetry($attempt);
        }
    }

    /**
     * Perform a single cURL attempt.
     *
     * Returns the raw outcome instead of throwing so makeCurlRequest() can decide
     * whether the failure is worth another attempt. 'pretransferTime' is what the
     * idempotency check is built on: cURL only sets it once the connection is
     * established and it is about to push the request body, so a zero means
     * nothing was ever sent.
     *
     * Protected rather than private so a test double can simulate a transport
     * outcome without a network.
     *
     * @return array{result: string|bool, httpCode: int, error: string, errorCode: int, pretransferTime: float}
     * @throws ClientResponseInvalidException
     */
    protected function executeCurl(string $url, array $data): array
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

        $result    = curl_exec($ch);
        $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $preTime   = (float) curl_getinfo($ch, CURLINFO_PRETRANSFER_TIME);
        $error     = curl_error($ch);
        $errorCode = curl_errno($ch);

        curl_close($ch);

        return [
            'result'          => $result,
            'httpCode'        => $httpCode,
            'error'           => $error,
            'errorCode'       => $errorCode,
            'pretransferTime' => $preTime,
        ];
    }

    /**
     * Decide whether a failed attempt may be repeated.
     *
     * Telegram has no idempotency key, so replaying a sendMessage that actually
     * reached the API would deliver the message twice. Connect-phase errors are
     * unconditionally safe — the connection never came up. The remaining codes
     * (timeout, dropped connection) can fire either side of the send, so they
     * are only repeated when cURL never got as far as writing the body.
     */
    private function isSafeToRetry(int $errorCode, float $pretransferTime): bool
    {
        if (!in_array($errorCode, self::RETRYABLE_CURL_ERRORS, true)) {
            return false;
        }

        if (in_array($errorCode, self::CONNECT_PHASE_CURL_ERRORS, true)) {
            return true;
        }

        return $pretransferTime <= 0.0;
    }

    /**
     * Wait before the next attempt, doubling the base delay each time.
     *
     * The delay is jittered down by up to half. Without it every worker that hit
     * the same outage retries in lockstep and hammers the endpoint hardest at the
     * moment it recovers.
     */
    private function sleepBeforeRetry(int $attempt): void
    {
        if ($this->retryDelay <= 0) {
            return;
        }

        $delay = $this->retryDelay * 1000 * (2 ** ($attempt - 1));

        usleep((int) ($delay * random_int(50, 100) / 100));
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

        // A connect budget larger than the whole-call budget is meaningless:
        // CURLOPT_TIMEOUT would fire first, so every handshake failure would cost
        // a full timeout instead of a connect timeout and the retry chain would
        // run (retries + 1) x timeout. Clamped rather than rejected in the
        // setters, which are independent and would otherwise reject a valid pair
        // depending on the order they are called in.
        $options[CURLOPT_CONNECTTIMEOUT] = min($this->connectTimeout, $this->timeout);

        // Transport tuning wins over setCurlOptions(): both come from the host
        // app, and the explicit setter is the documented knob.
        if ($this->ipVersion !== null) {
            $options[CURLOPT_IPRESOLVE] = $this->ipVersion === 4
                ? CURL_IPRESOLVE_V4
                : CURL_IPRESOLVE_V6;
        }

        if ($this->proxy !== null) {
            $options[CURLOPT_PROXY] = $this->proxy;
        }

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
     * Drop inline keyboard buttons Telegram would refuse, keeping the message.
     *
     * A button with a label but no action field — a url or callback_data that
     * came out null or empty — makes the API reject the *whole* request with
     * "can't parse InlineKeyboardButton: Text buttons are not allowed in the
     * inline keyboard", so a cosmetic bug in one button silently costs the user
     * the entire message. The builders refuse to create such a button, but a
     * hand-built reply_markup array reaches the client unchecked; here it loses
     * the broken button (and any row left empty) and the message still goes out,
     * with a warning naming what was dropped.
     *
     * ReplyKeyboard / ForceReply / ReplyKeyboardRemove markups are untouched —
     * a reply-keyboard button legitimately carries nothing but its text.
     *
     * @param string $method The API method being called
     * @param array $data Request data
     * @return array Request data with an unusable button removed
     */
    private function sanitizeReplyMarkup(string $method, array $data): array
    {
        $markup = $data['reply_markup'] ?? null;

        if ($markup === null) {
            return $data;
        }

        // A caller may pass the markup pre-encoded; keep whichever shape it came in.
        $wasJson = is_string($markup);

        if ($wasJson) {
            $markup = json_decode($markup, true);
        }

        if (!is_array($markup) || !isset($markup['inline_keyboard'])) {
            return $data;
        }

        $dropped = [];
        $sanitized = ReplyMarkup::sanitize($markup, $dropped);

        if ($dropped === []) {
            return $data;
        }

        $this->logger->warning('Dropped unusable inline keyboard buttons before sending', [
            'method'  => $method,
            'chat_id' => $data['chat_id'] ?? null,
            'buttons' => $dropped,
            'reason'  => 'A button had no action field (callback_data, url, web_app, …) or no text. '
                . 'Telegram would have rejected the whole request with '
                . '"Text buttons are not allowed in the inline keyboard".',
        ]);

        $data['reply_markup'] = $wasJson
            ? json_encode($sanitized, JSON_UNESCAPED_UNICODE)
            : $sanitized;

        return $data;
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