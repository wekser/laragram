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

namespace Wekser\Laragram\Exceptions;

/**
 * Exception thrown when user is deactivated
 * 
 * @package Wekser\Laragram\Exceptions
 */
class UserDeactivatedException extends TelegramApiException
{
    protected int $userId;

    public function __construct(int $userId, string $telegramDescription = '')
    {
        $this->userId = $userId;
        
        parent::__construct(
            \Wekser\Laragram\Enums\TelegramErrorCode::FORBIDDEN,
            $telegramDescription ?: "User {$userId} is deactivated",
            ['user_id' => $userId]
        );
    }

    public function getUserId(): int
    {
        return $this->userId;
    }
}

