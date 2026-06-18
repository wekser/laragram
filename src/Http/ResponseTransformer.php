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

namespace Wekser\Laragram\Http;

use Illuminate\Support\Arr;
use Wekser\Laragram\BotRequest;
use Wekser\Laragram\BotResponse;
use Wekser\Laragram\Exceptions\ResponseInvalidException;
use Wekser\Laragram\Facades\BotAuth;

/**
 * Transforms a controller/closure response into the output array consumed by
 * Laragram::back() and the CallbackFormed event.
 */
class ResponseTransformer
{
    /**
     * Build the full output array from the controller response.
     *
     * @param  BotRequest                  $request
     * @param  BotResponse|string|null     $response
     * @return array<string, mixed>|null
     *
     * @throws ResponseInvalidException
     */
    public function getResponse(BotRequest $request, mixed $response): ?array
    {
        $output = $request->getRequest();

        if ($response instanceof BotResponse) {
            $output['response']['view']     = $response->contents ?? [];
            $output['response']['redirect'] = $response->station ?? $output['route']['form'];
        } elseif (is_string($response)) {
            // Plain string shortcut: literal text with no parse_mode.
            $output['response']['view']     = ['method' => 'sendMessage', 'text' => $response];
            $output['response']['redirect'] = $output['route']['form'];
        } elseif (empty($response)) {
            return null;
        } else {
            throw new ResponseInvalidException($output['route']['uses']);
        }

        $output['response']['view'] = $this->injectTelegramIds($output);

        return $output;
    }

    /**
     * Inject required Telegram IDs (chat_id, callback_query_id, message_id) into
     * the view payload based on the update object and the API method being called.
     *
     * @param  array<string, mixed> $output
     * @return array<string, mixed>
     */
    private function injectTelegramIds(array $output): array
    {
        $view   = $output['response']['view'] ?? [];
        $object = $output['update']['object'] ?? [];
        $method = $view['method'] ?? null;

        // answerCallbackQuery requires callback_query_id (the id of the callback_query object)
        if ($method === 'answerCallbackQuery' && !isset($view['callback_query_id'])) {
            $view['callback_query_id'] = Arr::get($object, 'id');
        }

        // deleteMessage / editMessageText require message_id from the triggering message
        if (in_array($method, ['deleteMessage', 'editMessageText'], true) && !isset($view['message_id'])) {
            $view['message_id'] =
                Arr::get($object, 'message.message_id') ??
                Arr::get($object, 'message_id');
        }

        if (!empty($view['chat_id'])) {
            return $view;
        }

        // $object is already the type-specific payload (e.g. the message object,
        // the callback_query object, etc.) extracted by RequestTransformer.
        // For most types chat.id is at the top level; for callback_query it's
        // nested inside message.chat.id. User UID is the ultimate fallback.
        $view['chat_id'] =
            Arr::get($object, 'chat.id') ??
            Arr::get($object, 'message.chat.id') ??
            BotAuth::user()?->uid;

        if ($view['chat_id'] === null) {
            app('log')->warning('laragram: could not determine chat_id for Telegram API call', [
                'method'    => $method,
                'update_id' => $output['update']['id'] ?? null,
            ]);
        }

        return $view;
    }
}
