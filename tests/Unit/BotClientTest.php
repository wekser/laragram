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
        $this->expectExceptionMessageMatches('/positive/i');

        (new BotClient(self::VALID_TOKEN))->setTimeout(0);
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
        $this->expectExceptionMessageMatches('/positive/i');

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
}
