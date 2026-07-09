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

namespace Wekser\Laragram\Listeners;

use Wekser\Laragram\Events\CallbackFormed;

class LogSession
{
    /**
     * Session model.
     */
    protected $model;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        $this->model = config('laragram.auth.session.model');
    }

    /**
     * Handle the event.
     *
     * @param CallbackFormed $event
     * @return void
     */
    public function handle(CallbackFormed $event): void
    {
        if ($event->user?->id === null) {
            return; // Array driver — user is in-memory only, no DB persistence.
        }

        // One session row per (user, chat, topic). In a private chat the chat id
        // equals the user's uid and the topic is 0, so behaviour is unchanged; in
        // a group each member keeps independent state per chat, and in a forum per
        // topic within it. Derived from the update payload upstream
        // (RequestTransformer), so it is always this update's chat and topic.
        $chatId   = $event->output['update']['chat_id'] ?? $event->user->uid;
        $threadId = $event->output['update']['thread_id'] ?? 0;

        try {
            $this->model::updateOrCreate(
                ['user_id' => $event->user->id, 'chat_id' => $chatId, 'thread_id' => $threadId],
                [
                    'update_id'     => $event->output['update']['id'],
                    'station'       => $event->output['response']['redirect'],
                    'payload'       => [
                        'route' => $event->output['route'],
                        // Scene (wizard) state: current step + collected answers.
                        // Null on the normal routing path, which clears any prior scene.
                        'scene' => $event->output['scene'] ?? null,
                    ],
                    'last_activity' => now(),
                ]
            );
        } catch (\Throwable $e) {
            app('log')->error('laragram: failed to persist session', [
                'user_id'   => $event->user->id,
                'update_id' => $event->output['update']['id'] ?? null,
                'exception' => $e,
            ]);
        }
    }
}
