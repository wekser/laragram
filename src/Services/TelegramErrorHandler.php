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

namespace Wekser\Laragram\Services;

use Wekser\Laragram\Enums\TelegramErrorCode;
use Wekser\Laragram\Exceptions\BotBlockedException;
use Wekser\Laragram\Exceptions\ChatNotFoundException;
use Wekser\Laragram\Exceptions\TelegramApiException;
use Wekser\Laragram\Exceptions\UserDeactivatedException;
use Wekser\Laragram\Models\User;

/**
 * Maps Telegram API error responses to typed exceptions and provides
 * user-status checks before sending messages.
 *
 * @package Wekser\Laragram\Services
 */
class TelegramErrorHandler
{
    /**
     * Telegram API error description substrings mapped to internal types.
     * Matched case-insensitively against the `description` field.
     */
    private const DESCRIPTION_PATTERNS = [
        'bot was blocked by the user' => 'bot_blocked',
        'bot was kicked from'         => 'bot_blocked',
        'user is deactivated'         => 'user_deactivated',
        'chat not found'              => 'chat_not_found',
    ];

    /**
     * Map a Telegram API error response to a typed exception.
     *
     * @param array{error_code: int, description: string, parameters: array} $errorResponse
     * @param array $context  Optional context (e.g. ['user_id' => 123, 'chat_id' => '-100...'])
     */
    public function handleError(array $errorResponse, array $context): \Exception
    {
        $code        = (int) ($errorResponse['error_code'] ?? 0);
        $description = (string) ($errorResponse['description'] ?? '');
        $parameters  = (array) ($errorResponse['parameters'] ?? []);
        $normalized  = strtolower($description);

        foreach (self::DESCRIPTION_PATTERNS as $pattern => $type) {
            if (str_contains($normalized, $pattern)) {
                return $this->buildDescriptionException($type, $description, $context);
            }
        }

        $errorCode = TelegramErrorCode::tryFrom($code);

        if ($errorCode === null) {
            return new TelegramApiException(
                TelegramErrorCode::BAD_REQUEST,
                $description,
                $parameters,
            );
        }

        return new TelegramApiException($errorCode, $description, $parameters);
    }

    /**
     * Check whether a user can receive messages.
     *
     * Returns true when the user record is not found (unknown — allow send,
     * Telegram will notify if the bot was blocked) or when the user is active.
     * Returns false only when the user is explicitly deactivated in the database.
     *
     * Catches all Throwables so the method is safe when no DB connection is
     * available (e.g. when using the 'array' auth driver).
     *
     * @param int $userId Telegram user ID
     */
    public function validateUserBeforeSend(int $userId): bool
    {
        try {
            $user = $this->findUser($userId);

            return $user === null || $user->isActive();
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * Return status information for a given user.
     *
     * @param  int $userId Telegram user ID
     * @return array{user_id: int, exists: bool, is_active: bool, deactivated_at: string|null}
     */
    public function getUserStatus(int $userId): array
    {
        try {
            $user = $this->findUser($userId);

            if ($user === null) {
                return $this->buildStatus($userId, false, false, null);
            }

            return $this->buildStatus(
                $userId,
                true,
                $user->isActive(),
                $user->deactivated_at?->toIso8601String(),
            );
        } catch (\Throwable) {
            return $this->buildStatus($userId, false, false, null);
        }
    }

    private function findUser(int $userId): ?User
    {
        return User::where('uid', $userId)->first();
    }

    private function buildDescriptionException(string $type, string $description, array $context): \Exception
    {
        $userId = isset($context['user_id']) ? (int) $context['user_id'] : 0;
        $chatId = isset($context['chat_id']) ? (string) $context['chat_id'] : (string) $userId;

        return match ($type) {
            'bot_blocked'      => new BotBlockedException($userId, $description),
            'user_deactivated' => new UserDeactivatedException($userId, $description),
            'chat_not_found'   => new ChatNotFoundException($chatId, $description),
            default            => new TelegramApiException(TelegramErrorCode::FORBIDDEN, $description),
        };
    }

    /**
     * @return array{user_id: int, exists: bool, is_active: bool, deactivated_at: string|null}
     */
    private function buildStatus(int $userId, bool $exists, bool $isActive, ?string $deactivatedAt): array
    {
        return [
            'user_id'        => $userId,
            'exists'         => $exists,
            'is_active'      => $isActive,
            'deactivated_at' => $deactivatedAt,
        ];
    }
}
