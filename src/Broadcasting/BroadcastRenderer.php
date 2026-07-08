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
use Wekser\Laragram\Models\User;

/**
 * Turns a serializable broadcast content spec into a ready-to-send Telegram
 * payload for a specific recipient.
 *
 * Shared by the synchronous send loop (PendingBroadcast) and the queued
 * SendBroadcastMessage job, so a broadcast renders identically in either mode.
 * Rendering happens per recipient — not once up front — so each user's view is
 * built in their own language and with $user available in the template scope.
 *
 * A broadcast has no incoming update, so chat_id is never injected by
 * ResponseTransformer; this renderer sets it explicitly to the recipient's uid
 * (which is the chat id for a private chat).
 */
class BroadcastRenderer
{
    /**
     * Render a content spec to a Telegram payload addressed to $user.
     *
     * @param array{type: string, view?: string, data?: array, text?: string, payload?: array, format?: ?string} $content
     * @return array<string, mixed>
     */
    public function render(array $content, User $user): array
    {
        // A 'payload' spec is a pre-built BotResponse::$contents array — rendered
        // once when the broadcast was composed, not per recipient. Return it as
        // is (only chat_id is injected below); no locale/re-render applies.
        if (($content['type'] ?? null) === 'payload') {
            $payload = $content['payload'];
            $payload['chat_id'] = $user->uid;

            return $payload;
        }

        // An explicit format key (including null, meaning "already formatted —
        // do not escape") must be honored; only its absence defaults to HTML.
        $format = array_key_exists('format', $content) ? $content['format'] : 'HTML';

        $previousLocale = app('translator')->getLocale();
        $this->applyLocale($user);

        try {
            $response = (new BotResponse((string) config('laragram.paths.views')))->setUser($user);

            $payload = match ($content['type'] ?? null) {
                'view' => $response->view(
                    (string) $content['view'],
                    $content['data'] ?? [],
                    $format,
                )->contents,
                'text' => $response->text(
                    (string) $content['text'],
                    $format,
                )->contents,
                default => throw new \InvalidArgumentException(
                    "Unknown broadcast content type [" . ($content['type'] ?? 'null') . "]."
                ),
            };
        } finally {
            // Restore the locale so a long-lived queue worker does not bleed the
            // last recipient's language into the next job/operation.
            app('translator')->setLocale($previousLocale);
        }

        $payload['chat_id'] = $user->uid;

        return $payload;
    }

    /**
     * Set the translator locale to the recipient's language, mirroring
     * Laragram::bootstrap() so broadcast views localize like normal replies.
     */
    protected function applyLocale(User $user): void
    {
        $locale = $user->settings?->get('language') ?? config('app.locale');

        app('translator')->setLocale($locale ?? 'en');
    }
}
