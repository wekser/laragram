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

namespace Wekser\Laragram\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Wekser\Laragram\BotClient;
use Wekser\Laragram\Exceptions\ClientResponseInvalidException;
use Wekser\Laragram\Exceptions\TransportException;

#[CoversClass(BotClient::class)]
class BotClientTest extends TestCase
{
    private const VALID_TOKEN = '123456789:ABCDEFGHIJKlmnopqrstuvwxyz01234';

    // -------------------------------------------------------------------------
    // Token validation
    // -------------------------------------------------------------------------

    public function test_constructor_throws_when_token_is_empty(): void
    {
        $this->expectException(ClientResponseInvalidException::class);
        $this->expectExceptionMessageMatches('/empty/i');

        new BotClient('');
    }

    public function test_constructor_throws_when_token_has_no_colon_separator(): void
    {
        $this->expectException(ClientResponseInvalidException::class);
        $this->expectExceptionMessageMatches('/invalid.*token/i');

        new BotClient('1234567890ABCDEFabcdef');
    }

    public function test_constructor_throws_when_token_id_part_is_not_numeric(): void
    {
        $this->expectException(ClientResponseInvalidException::class);

        new BotClient('LETTERS:ABCDEFGHIJKlmnop');
    }

    public function test_constructor_throws_when_token_contains_invalid_characters(): void
    {
        $this->expectException(ClientResponseInvalidException::class);

        new BotClient('123456:token with spaces');
    }

    public function test_constructor_succeeds_with_valid_token(): void
    {
        $client = new BotClient(self::VALID_TOKEN);

        $this->assertInstanceOf(BotClient::class, $client);
    }

    // -------------------------------------------------------------------------
    // getMaskedToken
    // -------------------------------------------------------------------------

    public function test_get_masked_token_starts_with_token_id_part(): void
    {
        $client = new BotClient(self::VALID_TOKEN);

        $this->assertStringStartsWith('123456789:', $client->getMaskedToken());
    }

    public function test_get_masked_token_ends_with_last_four_chars(): void
    {
        $client = new BotClient(self::VALID_TOKEN);

        $this->assertStringEndsWith('1234', $client->getMaskedToken());
    }

    public function test_get_masked_token_contains_ellipsis(): void
    {
        $client = new BotClient(self::VALID_TOKEN);

        $this->assertStringContainsString('...', $client->getMaskedToken());
    }

    public function test_get_masked_token_does_not_expose_full_secret(): void
    {
        $token  = '987654321:VerySecretTokenValue1234';
        $client = new BotClient($token);
        $masked = $client->getMaskedToken();

        $this->assertNotSame($token, $masked);
        $this->assertStringNotContainsString('VerySecretTokenValue', $masked);
    }

    // -------------------------------------------------------------------------
    // Fluent setters
    // -------------------------------------------------------------------------

    public function test_set_timeout_returns_same_instance(): void
    {
        $client = new BotClient(self::VALID_TOKEN);

        $this->assertSame($client, $client->setTimeout(15));
    }

    public function test_set_timeout_throws_when_zero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/between 1 and 300/i');

        (new BotClient(self::VALID_TOKEN))->setTimeout(0);
    }

    public function test_set_timeout_throws_when_above_max(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/between 1 and 300/i');

        (new BotClient(self::VALID_TOKEN))->setTimeout(301);
    }

    public function test_set_timeout_throws_when_negative(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new BotClient(self::VALID_TOKEN))->setTimeout(-5);
    }

    public function test_set_connect_timeout_returns_same_instance(): void
    {
        $client = new BotClient(self::VALID_TOKEN);

        $this->assertSame($client, $client->setConnectTimeout(5));
    }

    public function test_set_connect_timeout_throws_when_zero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/between 1 and 300/i');

        (new BotClient(self::VALID_TOKEN))->setConnectTimeout(0);
    }

    public function test_set_connect_timeout_throws_when_negative(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new BotClient(self::VALID_TOKEN))->setConnectTimeout(-1);
    }

    public function test_set_curl_options_returns_same_instance(): void
    {
        $client = new BotClient(self::VALID_TOKEN);

        $this->assertSame($client, $client->setCurlOptions([CURLOPT_VERBOSE => false]));
    }

    // -------------------------------------------------------------------------
    // prepareData — payload normalization for CURLOPT_POSTFIELDS
    // -------------------------------------------------------------------------

    /**
     * Regression: a nested array (e.g. reply_markup) passed straight to
     * CURLOPT_POSTFIELDS triggered "Array to string conversion". prepareData
     * must JSON-encode array values so cURL receives only strings/CURLFiles.
     */
    public function test_prepare_data_json_encodes_nested_arrays(): void
    {
        $prepared = $this->prepareData([
            'chat_id'      => 42,
            'text'         => 'Hello',
            'reply_markup' => ['inline_keyboard' => [[['text' => 'Ok', 'callback_data' => 'ok']]]],
        ]);

        $this->assertSame(42, $prepared['chat_id']);
        $this->assertSame('Hello', $prepared['text']);
        $this->assertIsString($prepared['reply_markup']);
        $this->assertSame(
            ['inline_keyboard' => [[['text' => 'Ok', 'callback_data' => 'ok']]]],
            json_decode($prepared['reply_markup'], true)
        );
    }

    public function test_prepare_data_keeps_unicode_unescaped(): void
    {
        $prepared = $this->prepareData([
            'reply_markup' => ['inline_keyboard' => [[['text' => 'Привет', 'callback_data' => 'x']]]],
        ]);

        $this->assertStringContainsString('Привет', $prepared['reply_markup']);
    }

    public function test_prepare_data_removes_null_values(): void
    {
        $prepared = $this->prepareData([
            'chat_id'      => 7,
            'parse_mode'   => null,
            'reply_markup' => null,
        ]);

        $this->assertSame(['chat_id' => 7], $prepared);
    }

    public function test_prepare_data_preserves_curlfile_objects(): void
    {
        $file     = new \CURLFile(__FILE__);
        $prepared = $this->prepareData(['chat_id' => 1, 'photo' => $file]);

        $this->assertSame($file, $prepared['photo']);
    }

    private function prepareData(array $data): array
    {
        $method = new \ReflectionMethod(BotClient::class, 'prepareData');

        return $method->invoke(new BotClient(self::VALID_TOKEN), $data);
    }

    // -------------------------------------------------------------------------
    // buildCurlOptions — TLS hardening
    // -------------------------------------------------------------------------

    /**
     * Security: user-supplied curlOptions must never be able to weaken TLS
     * verification or open unbounded redirects, even though setCurlOptions()
     * takes precedence in the union merge.
     */
    public function test_build_curl_options_forces_tls_verification_over_user_overrides(): void
    {
        $client = (new BotClient(self::VALID_TOKEN))->setCurlOptions([
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_MAXREDIRS      => 99,
        ]);

        $options = $this->buildCurlOptions($client, 'https://api.telegram.org/botX/getMe', ['a' => 1]);

        $this->assertTrue($options[CURLOPT_SSL_VERIFYPEER]);
        $this->assertSame(2, $options[CURLOPT_SSL_VERIFYHOST]);
        $this->assertSame(3, $options[CURLOPT_MAXREDIRS]);
    }

    // -------------------------------------------------------------------------
    // Transport tuning setters
    // -------------------------------------------------------------------------

    public function test_set_retries_returns_same_instance(): void
    {
        $client = new BotClient(self::VALID_TOKEN);

        $this->assertSame($client, $client->setRetries(3));
    }

    public function test_set_retries_throws_when_negative(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new BotClient(self::VALID_TOKEN))->setRetries(-1);
    }

    public function test_set_retries_throws_when_above_max(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new BotClient(self::VALID_TOKEN))->setRetries(6);
    }

    public function test_set_retry_delay_throws_when_negative(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new BotClient(self::VALID_TOKEN))->setRetryDelay(-1);
    }

    public function test_set_retry_delay_throws_when_above_max(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new BotClient(self::VALID_TOKEN))->setRetryDelay(10001);
    }

    public function test_set_ip_version_throws_on_unsupported_value(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new BotClient(self::VALID_TOKEN))->setIpVersion(5);
    }

    public function test_set_ip_version_accepts_null(): void
    {
        $client = new BotClient(self::VALID_TOKEN);

        $this->assertSame($client, $client->setIpVersion(null));
    }

    // -------------------------------------------------------------------------
    // buildCurlOptions — IP version & proxy
    // -------------------------------------------------------------------------

    public function test_build_curl_options_omits_ip_resolve_by_default(): void
    {
        $options = $this->buildCurlOptions(new BotClient(self::VALID_TOKEN), 'https://api.telegram.org/botX/getMe', []);

        $this->assertArrayNotHasKey(CURLOPT_IPRESOLVE, $options);
        $this->assertArrayNotHasKey(CURLOPT_PROXY, $options);
    }

    public function test_build_curl_options_pins_ipv4_when_configured(): void
    {
        $client = (new BotClient(self::VALID_TOKEN))->setIpVersion(4);

        $options = $this->buildCurlOptions($client, 'https://api.telegram.org/botX/getMe', []);

        $this->assertSame(CURL_IPRESOLVE_V4, $options[CURLOPT_IPRESOLVE]);
    }

    public function test_build_curl_options_sets_proxy_when_configured(): void
    {
        $client = (new BotClient(self::VALID_TOKEN))->setProxy('http://proxy.local:3128');

        $options = $this->buildCurlOptions($client, 'https://api.telegram.org/botX/getMe', []);

        $this->assertSame('http://proxy.local:3128', $options[CURLOPT_PROXY]);
    }

    public function test_build_curl_options_treats_empty_proxy_as_disabled(): void
    {
        $client = (new BotClient(self::VALID_TOKEN))->setProxy('');

        $options = $this->buildCurlOptions($client, 'https://api.telegram.org/botX/getMe', []);

        $this->assertArrayNotHasKey(CURLOPT_PROXY, $options);
    }

    // -------------------------------------------------------------------------
    // Transport retries
    // -------------------------------------------------------------------------

    public function test_retries_a_connect_failure_and_returns_the_successful_body(): void
    {
        $client = (new RetryingClientDouble(self::VALID_TOKEN))->setRetries(2)->setRetryDelay(0);
        $client->outcomes = [
            RetryingClientDouble::failure(7, 'Failed to connect'),
            RetryingClientDouble::success('{"ok":true}'),
        ];

        $this->assertSame('{"ok":true}', $this->makeCurlRequest($client));
        $this->assertSame(2, $client->attempts);
    }

    public function test_gives_up_after_the_configured_number_of_retries(): void
    {
        $client = (new RetryingClientDouble(self::VALID_TOKEN))->setRetries(2)->setRetryDelay(0);
        $client->outcomes = [
            RetryingClientDouble::failure(35, 'SSL connect error'),
            RetryingClientDouble::failure(35, 'SSL connect error'),
            RetryingClientDouble::failure(35, 'SSL connect error'),
        ];

        try {
            $this->makeCurlRequest($client);
            $this->fail('Expected a TransportException.');
        } catch (TransportException $exception) {
            $this->assertSame(35, $exception->getCode());
            $this->assertSame(3, $exception->attempts);
        }

        $this->assertSame(3, $client->attempts);
    }

    /**
     * The reported production failure: cURL 28 raised during the TLS handshake.
     * Nothing was sent, so repeating it cannot duplicate a message.
     */
    public function test_retries_a_timeout_that_happened_before_anything_was_sent(): void
    {
        $client = (new RetryingClientDouble(self::VALID_TOKEN))->setRetries(1)->setRetryDelay(0);
        $client->outcomes = [
            RetryingClientDouble::failure(28, 'SSL connection timeout', pretransferTime: 0.0),
            RetryingClientDouble::success('{"ok":true}'),
        ];

        $this->assertSame('{"ok":true}', $this->makeCurlRequest($client));
        $this->assertSame(2, $client->attempts);
    }

    /**
     * Idempotency guard: the same errno raised AFTER the request body went out
     * must not be repeated — Telegram may well have delivered the message, and
     * a retry would send it twice.
     */
    public function test_does_not_retry_a_timeout_that_happened_after_the_request_was_sent(): void
    {
        $client = (new RetryingClientDouble(self::VALID_TOKEN))->setRetries(3)->setRetryDelay(0);
        $client->outcomes = [
            RetryingClientDouble::failure(28, 'Operation timed out', pretransferTime: 0.42),
        ];

        $this->expectException(TransportException::class);

        try {
            $this->makeCurlRequest($client);
        } finally {
            $this->assertSame(1, $client->attempts);
        }
    }

    public function test_does_not_retry_a_non_transport_curl_error(): void
    {
        $client = (new RetryingClientDouble(self::VALID_TOKEN))->setRetries(3)->setRetryDelay(0);
        $client->outcomes = [
            RetryingClientDouble::failure(43, 'A libcurl function was given a bad argument'),
        ];

        $this->expectException(TransportException::class);

        try {
            $this->makeCurlRequest($client);
        } finally {
            $this->assertSame(1, $client->attempts);
        }
    }

    public function test_does_not_retry_when_retries_are_disabled(): void
    {
        $client = (new RetryingClientDouble(self::VALID_TOKEN))->setRetries(0);
        $client->outcomes = [
            RetryingClientDouble::failure(7, 'Failed to connect'),
        ];

        $this->expectException(TransportException::class);

        try {
            $this->makeCurlRequest($client);
        } finally {
            $this->assertSame(1, $client->attempts);
        }
    }

    /**
     * A connection dropped mid-flight (52/55/56) is deliberately NOT retried:
     * the request body is already on the wire by then, so Telegram may have
     * acted on it and a retry would deliver the same message twice.
     */
    public function test_does_not_retry_a_connection_dropped_mid_flight(): void
    {
        foreach ([52, 55, 56] as $errno) {
            $client = (new RetryingClientDouble(self::VALID_TOKEN))->setRetries(3)->setRetryDelay(0);
            $client->outcomes = [
                RetryingClientDouble::failure($errno, 'Connection lost', pretransferTime: 0.2),
            ];

            try {
                $this->makeCurlRequest($client);
                $this->fail("Expected a TransportException for cURL errno {$errno}.");
            } catch (TransportException) {
                $this->assertSame(1, $client->attempts, "cURL errno {$errno} must not be retried.");
            }
        }
    }

    /**
     * The documented default has to hold without any config: mergeConfigFrom()
     * merges at the top level only, so an app that published config/laragram.php
     * before these keys existed passes none of them through.
     */
    public function test_retries_are_enabled_by_default(): void
    {
        $client = (new RetryingClientDouble(self::VALID_TOKEN))->setRetryDelay(0);
        $client->outcomes = [
            RetryingClientDouble::failure(7, 'Failed to connect'),
            RetryingClientDouble::failure(7, 'Failed to connect'),
            RetryingClientDouble::success('{"ok":true}'),
        ];

        $this->assertSame('{"ok":true}', $this->makeCurlRequest($client));
        $this->assertSame(3, $client->attempts);
    }

    /**
     * A connect budget above the total budget would let CURLOPT_TIMEOUT fire
     * first, so every handshake failure would cost a full timeout and the retry
     * chain would run (retries + 1) x timeout instead of x connect_timeout.
     */
    public function test_build_curl_options_clamps_connect_timeout_to_the_total_timeout(): void
    {
        $client = (new BotClient(self::VALID_TOKEN))->setConnectTimeout(60)->setTimeout(30);

        $options = $this->buildCurlOptions($client, 'https://api.telegram.org/botX/getMe', []);

        $this->assertSame(30, $options[CURLOPT_TIMEOUT]);
        $this->assertSame(30, $options[CURLOPT_CONNECTTIMEOUT]);
    }

    public function test_build_curl_options_keeps_a_connect_timeout_below_the_total_timeout(): void
    {
        $client = (new BotClient(self::VALID_TOKEN))->setConnectTimeout(5)->setTimeout(30);

        $options = $this->buildCurlOptions($client, 'https://api.telegram.org/botX/getMe', []);

        $this->assertSame(5, $options[CURLOPT_CONNECTTIMEOUT]);
    }

    private function makeCurlRequest(BotClient $client): string
    {
        $method = new \ReflectionMethod(BotClient::class, 'makeCurlRequest');

        return $method->invoke($client, 'https://api.telegram.org/botX/sendMessage', ['text' => 'hi']);
    }

    private function buildCurlOptions(BotClient $client, string $url, array $data): array
    {
        $method = new \ReflectionMethod(BotClient::class, 'buildCurlOptions');

        return $method->invoke($client, $url, $data);
    }
}

/**
 * Replaces the network with a scripted list of cURL outcomes so the retry loop
 * can be driven deterministically. Running out of scripted outcomes throws, so
 * a runaway loop fails the test instead of hanging the suite.
 */
class RetryingClientDouble extends BotClient
{
    /** @var array<int, array<string, mixed>> */
    public array $outcomes = [];

    public int $attempts = 0;

    public static function success(string $body, int $httpCode = 200): array
    {
        return [
            'result'          => $body,
            'httpCode'        => $httpCode,
            'error'           => '',
            'errorCode'       => 0,
            'pretransferTime' => 0.1,
        ];
    }

    public static function failure(int $errorCode, string $error, float $pretransferTime = 0.0): array
    {
        return [
            'result'          => false,
            'httpCode'        => 0,
            'error'           => $error,
            'errorCode'       => $errorCode,
            'pretransferTime' => $pretransferTime,
        ];
    }

    protected function executeCurl(string $url, array $data): array
    {
        $this->attempts++;

        // Running past the script means the retry loop attempted more calls than
        // the test expected. Fail loudly: falling back to the last outcome would
        // let a runaway loop pass as a success.
        if (!isset($this->outcomes[$this->attempts - 1])) {
            throw new \LogicException("No scripted cURL outcome for attempt {$this->attempts}.");
        }

        return $this->outcomes[$this->attempts - 1];
    }
}
