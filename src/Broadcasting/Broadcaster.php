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

namespace Wekser\Laragram\Broadcasting;

use Wekser\Laragram\BotResponse;

/**
 * Entry point for mass messaging.
 *
 * Resolved via the BotBroadcast facade (container alias laragram.broadcast).
 * Each entry method returns a fresh PendingBroadcast so the shared singleton
 * never leaks state between broadcasts:
 *
 *   use Wekser\Laragram\Facades\BotBroadcast;
 *
 *   BotBroadcast::text('We are back online!')->send();
 *   BotBroadcast::view('news.release', ['version' => '2.0'])->role('admin')->send();
 *   BotBroadcast::message(BotResponse::photo($id, 'Hi')->keyboard($kb))->send();
 *
 * The view() / text() paths render per recipient (honoring each user's
 * language); message() sends a pre-built BotResponse rendered once. Delivery is
 * queued when queue.enabled is true and synchronous otherwise.
 */
class Broadcaster
{
    public function __construct(private readonly BroadcastRenderer $renderer) {}

    /**
     * Broadcast a rendered view (rendered per recipient).
     *
     * @param array<string, mixed> $data
     */
    public function view(string $view, array $data = [], ?string $format = 'HTML'): PendingBroadcast
    {
        return new PendingBroadcast(
            ['type' => 'view', 'view' => $view, 'data' => $data, 'format' => $format],
            $this->renderer,
        );
    }

    /**
     * Broadcast a raw text message.
     */
    public function text(string $text, ?string $format = 'HTML'): PendingBroadcast
    {
        return new PendingBroadcast(
            ['type' => 'text', 'text' => $text, 'format' => $format],
            $this->renderer,
        );
    }

    /**
     * Broadcast a fully-composed BotResponse — formatting, an inline/reply
     * keyboard, and media (anything a normal reply can carry).
     *
     * Unlike view() / text(), the message is rendered ONCE here (not per
     * recipient), so it is not re-localized for each user's language; use view()
     * when you need per-recipient localization. The already-built payload
     * (BotResponse::$contents — a plain, queue-safe array) is stored verbatim and
     * only the recipient's chat_id is injected at delivery.
     */
    public function message(BotResponse $response): PendingBroadcast
    {
        if ($response->contents === []) {
            throw new \InvalidArgumentException(
                'Cannot broadcast an empty BotResponse — call a content method (text/view/photo/…) first.'
            );
        }

        return new PendingBroadcast(
            ['type' => 'payload', 'payload' => $response->contents],
            $this->renderer,
        );
    }
}
