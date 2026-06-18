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
 * Exception thrown when chat is not found
 * 
 * @package Wekser\Laragram\Exceptions
 */
class ChatNotFoundException extends TelegramApiException
{
    protected string $chatId;

    public function __construct(string $chatId, string $telegramDescription = '')
    {
        $this->chatId = $chatId;
        
        parent::__construct(
            \Wekser\Laragram\Enums\TelegramErrorCode::BAD_REQUEST,
            $telegramDescription ?: "Chat {$chatId} not found",
            ['chat_id' => $chatId]
        );
    }

    public function getChatId(): string
    {
        return $this->chatId;
    }
}

