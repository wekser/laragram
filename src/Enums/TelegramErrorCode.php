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

namespace Wekser\Laragram\Enums;

/**
 * Telegram Bot API Error Codes
 * 
 * @package Wekser\Laragram\Enums
 */
enum TelegramErrorCode: int
{
    // 4xx Client Errors
    case BAD_REQUEST = 400;
    case UNAUTHORIZED = 401;
    case FORBIDDEN = 403;
    case NOT_FOUND = 404;
    case CONFLICT = 409;
    case TOO_MANY_REQUESTS = 429;

    // 5xx Server Errors
    case INTERNAL_SERVER_ERROR = 500;
    case BAD_GATEWAY = 502;
    case SERVICE_UNAVAILABLE = 503;
    case GATEWAY_TIMEOUT = 504;

    /**
     * Get a short human-readable error description.
     */
    public function getDescription(): string
    {
        return match($this) {
            self::BAD_REQUEST          => 'Bad request',
            self::UNAUTHORIZED         => 'Invalid bot token',
            self::FORBIDDEN            => 'Access forbidden',
            self::NOT_FOUND            => 'Resource not found',
            self::CONFLICT             => 'Request conflict',
            self::TOO_MANY_REQUESTS    => 'Too many requests',
            self::INTERNAL_SERVER_ERROR => 'Internal server error',
            self::BAD_GATEWAY          => 'Bad gateway',
            self::SERVICE_UNAVAILABLE  => 'Service unavailable',
            self::GATEWAY_TIMEOUT      => 'Gateway timeout',
        };
    }

    /**
     * Get detailed error description based on Telegram API response
     */
    public function getDetailedDescription(string $telegramDescription = ''): string
    {
        $baseDescription = $this->getDescription();
        
        if (!empty($telegramDescription)) {
            return "{$baseDescription}: {$telegramDescription}";
        }
        
        return $baseDescription;
    }

    /**
     * Check if error requires user deactivation
     */
    public function requiresUserDeactivation(): bool
    {
        return match($this) {
            self::FORBIDDEN => true,
            default => false,
        };
    }

    /**
     * Check if error requires special handling for 400 errors
     */
    public function requiresSpecialHandling(): bool
    {
        return match($this) {
            self::BAD_REQUEST => true,
            self::FORBIDDEN => true,
            default => false,
        };
    }

    /**
     * Get a recommended recovery action for the error.
     */
    public function getRecommendedAction(): string
    {
        return match($this) {
            self::UNAUTHORIZED      => 'Verify your bot token is correct and the bot is active',
            self::FORBIDDEN         => 'Check bot permissions and user status',
            self::BAD_REQUEST       => 'Verify request parameters are correct',
            self::TOO_MANY_REQUESTS => 'Reduce request frequency',
            self::CONFLICT          => 'Ensure getUpdates and webhook are not used simultaneously',
            default                 => 'Refer to the Telegram Bot API documentation',
        };
    }
}

