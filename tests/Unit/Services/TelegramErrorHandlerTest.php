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

namespace Wekser\Laragram\Tests\Unit\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Wekser\Laragram\Enums\TelegramErrorCode;
use Wekser\Laragram\Exceptions\BotBlockedException;
use Wekser\Laragram\Exceptions\ChatNotFoundException;
use Wekser\Laragram\Exceptions\TelegramApiException;
use Wekser\Laragram\Exceptions\UserDeactivatedException;
use Wekser\Laragram\Services\TelegramErrorHandler;

#[CoversClass(TelegramErrorHandler::class)]
class TelegramErrorHandlerTest extends TestCase
{
    private TelegramErrorHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new TelegramErrorHandler();
    }

    // -------------------------------------------------------------------------
    // handleError — description pattern matching
    // -------------------------------------------------------------------------

    public function test_maps_bot_blocked_description_to_bot_blocked_exception(): void
    {
        $exception = $this->handler->handleError(
            $this->error(403, 'Forbidden: bot was blocked by the user'),
            ['user_id' => 42],
        );

        $this->assertInstanceOf(BotBlockedException::class, $exception);
        $this->assertSame(42, $exception->getUserId());
    }

    public function test_maps_bot_kicked_from_group_to_bot_blocked_exception(): void
    {
        $exception = $this->handler->handleError(
            $this->error(403, 'Forbidden: bot was kicked from the group chat'),
            ['user_id' => 7],
        );

        $this->assertInstanceOf(BotBlockedException::class, $exception);
        $this->assertSame(7, $exception->getUserId());
    }

    public function test_maps_user_deactivated_description_to_user_deactivated_exception(): void
    {
        $exception = $this->handler->handleError(
            $this->error(403, 'Forbidden: user is deactivated'),
            ['user_id' => 100],
        );

        $this->assertInstanceOf(UserDeactivatedException::class, $exception);
        $this->assertSame(100, $exception->getUserId());
    }

    public function test_maps_chat_not_found_to_chat_not_found_exception_with_context_chat_id(): void
    {
        $exception = $this->handler->handleError(
            $this->error(400, 'Bad Request: chat not found'),
            ['chat_id' => '-100123456789'],
        );

        $this->assertInstanceOf(ChatNotFoundException::class, $exception);
        $this->assertSame('-100123456789', $exception->getChatId());
    }

    public function test_chat_not_found_falls_back_to_user_id_when_chat_id_absent(): void
    {
        $exception = $this->handler->handleError(
            $this->error(400, 'Bad Request: chat not found'),
            ['user_id' => 55],
        );

        $this->assertInstanceOf(ChatNotFoundException::class, $exception);
        $this->assertSame('55', $exception->getChatId());
    }

    public function test_description_matching_is_case_insensitive(): void
    {
        $exception = $this->handler->handleError(
            $this->error(403, 'FORBIDDEN: BOT WAS BLOCKED BY THE USER'),
            ['user_id' => 1],
        );

        $this->assertInstanceOf(BotBlockedException::class, $exception);
    }

    public function test_original_description_is_preserved_in_exception_message(): void
    {
        $description = 'Forbidden: bot was blocked by the user';

        $exception = $this->handler->handleError(
            $this->error(403, $description),
            ['user_id' => 1],
        );

        $this->assertStringContainsString($description, $exception->getMessage());
    }

    // -------------------------------------------------------------------------
    // handleError — HTTP status code fallback
    // -------------------------------------------------------------------------

    public function test_maps_401_to_unauthorized(): void
    {
        $exception = $this->handler->handleError($this->error(401, 'Unauthorized'), []);

        $this->assertInstanceOf(TelegramApiException::class, $exception);
        $this->assertSame(TelegramErrorCode::UNAUTHORIZED, $exception->getErrorCode());
    }

    public function test_maps_400_without_known_description_to_bad_request(): void
    {
        $exception = $this->handler->handleError($this->error(400, 'Bad Request: wrong type'), []);

        $this->assertInstanceOf(TelegramApiException::class, $exception);
        $this->assertSame(TelegramErrorCode::BAD_REQUEST, $exception->getErrorCode());
    }

    public function test_maps_429_to_too_many_requests(): void
    {
        $exception = $this->handler->handleError(
            $this->error(429, 'Too Many Requests: retry after 30', ['retry_after' => 30]),
            [],
        );

        $this->assertInstanceOf(TelegramApiException::class, $exception);
        $this->assertSame(TelegramErrorCode::TOO_MANY_REQUESTS, $exception->getErrorCode());
    }

    public function test_maps_500_to_internal_server_error(): void
    {
        $exception = $this->handler->handleError($this->error(500, 'Internal Server Error'), []);

        $this->assertInstanceOf(TelegramApiException::class, $exception);
        $this->assertSame(TelegramErrorCode::INTERNAL_SERVER_ERROR, $exception->getErrorCode());
    }

    public function test_unknown_error_code_falls_back_to_bad_request(): void
    {
        $exception = $this->handler->handleError($this->error(999, 'Some unknown error'), []);

        $this->assertInstanceOf(TelegramApiException::class, $exception);
        $this->assertSame(TelegramErrorCode::BAD_REQUEST, $exception->getErrorCode());
    }

    public function test_missing_error_code_falls_back_to_bad_request(): void
    {
        $exception = $this->handler->handleError(['description' => 'oops', 'parameters' => []], []);

        $this->assertInstanceOf(TelegramApiException::class, $exception);
        $this->assertSame(TelegramErrorCode::BAD_REQUEST, $exception->getErrorCode());
    }

    // -------------------------------------------------------------------------
    // validateUserBeforeSend — safe fallback without a DB connection
    // -------------------------------------------------------------------------

    public function test_validate_user_returns_true_when_no_db_connection(): void
    {
        // Without a bootstrapped Laravel application, User::where() throws a
        // Throwable. The handler must catch it and return true (allow send).
        $result = $this->handler->validateUserBeforeSend(12345);

        $this->assertTrue($result);
    }

    // -------------------------------------------------------------------------
    // getUserStatus — safe fallback without a DB connection
    // -------------------------------------------------------------------------

    public function test_get_user_status_returns_required_keys(): void
    {
        $status = $this->handler->getUserStatus(1);

        $this->assertArrayHasKey('user_id', $status);
        $this->assertArrayHasKey('exists', $status);
        $this->assertArrayHasKey('is_active', $status);
        $this->assertArrayHasKey('deactivated_at', $status);
    }

    public function test_get_user_status_returns_safe_defaults_when_no_db_connection(): void
    {
        $status = $this->handler->getUserStatus(12345);

        $this->assertSame(12345, $status['user_id']);
        $this->assertFalse($status['exists']);
        $this->assertFalse($status['is_active']);
        $this->assertNull($status['deactivated_at']);
    }

    // -------------------------------------------------------------------------
    // helpers
    // -------------------------------------------------------------------------

    private function error(int $code, string $description, array $parameters = []): array
    {
        return ['error_code' => $code, 'description' => $description, 'parameters' => $parameters];
    }
}
