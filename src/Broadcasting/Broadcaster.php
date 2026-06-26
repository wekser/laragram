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
 *
 * Content is rendered per recipient (honoring each user's language); delivery
 * is queued when queue.enabled is true and synchronous otherwise.
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
}
