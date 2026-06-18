<?php

/*
 * This file is part of Laragram.
 *
 * (c) Sergey Lapin <me@wekser.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Wekser\Laragram\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for BotResponse — builds Telegram API payloads in controllers.
 *
 * Text / editing
 * @method static \Wekser\Laragram\BotResponse text(string $text, ?string $format = 'MarkdownV2')
 * @method static \Wekser\Laragram\BotResponse edit(string $text, ?string $format = 'MarkdownV2')
 * @method static \Wekser\Laragram\BotResponse answer(string $text = '', bool $showAlert = false)
 * @method static \Wekser\Laragram\BotResponse delete()
 *
 * Media
 * @method static \Wekser\Laragram\BotResponse photo(string $fileId, ?string $caption = null, ?string $format = 'MarkdownV2')
 * @method static \Wekser\Laragram\BotResponse document(string $fileId, ?string $caption = null, ?string $format = 'MarkdownV2')
 * @method static \Wekser\Laragram\BotResponse audio(string $fileId, ?string $caption = null, ?string $format = 'MarkdownV2')
 * @method static \Wekser\Laragram\BotResponse video(string $fileId, ?string $caption = null, ?string $format = 'MarkdownV2')
 * @method static \Wekser\Laragram\BotResponse voice(string $fileId, ?string $caption = null, ?string $format = 'MarkdownV2')
 * @method static \Wekser\Laragram\BotResponse animation(string $fileId, ?string $caption = null, ?string $format = 'MarkdownV2')
 * @method static \Wekser\Laragram\BotResponse videoNote(string $fileId)
 * @method static \Wekser\Laragram\BotResponse sticker(string $fileId)
 *
 * Attachments & actions
 * @method static \Wekser\Laragram\BotResponse keyboard(array $markup)
 * @method static \Wekser\Laragram\BotResponse action(string $action = 'typing')
 *
 * Views & navigation
 * @method static \Wekser\Laragram\BotResponse view(string $method, string $view, array $data = [])
 * @method static \Wekser\Laragram\BotResponse redirect(string $station)
 * @method static \Wekser\Laragram\BotResponse setUser(mixed $user)
 *
 * @see \Wekser\Laragram\BotResponse
 */
class BotResponse extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'laragram.response';
    }
}