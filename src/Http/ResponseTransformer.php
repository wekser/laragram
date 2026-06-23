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
     * A controller may return a single response (BotResponse|string) or a list
     * of them (array / iterable) for a multi-message reply. Every item becomes a
     * Telegram payload under response.views, sent in order by ResponseDispatcher.
     * The next station (response.redirect) is the last one a response sets
     * (last-write-wins), falling back to the current route's form.
     *
     * @param  BotRequest                                       $request
     * @param  BotResponse|string|iterable<BotResponse|string>|null $response
     * @return array<string, mixed>|null
     *
     * @throws ResponseInvalidException
     */
    public function getResponse(BotRequest $request, mixed $response): ?array
    {
        $output = $request->getRequest();

        // A controller may return a single response or a LIST of them. Treat the
        // value as a batch only when it is a non-BotResponse iterable that is not
        // an associative array — a bare ['method' => ...] payload is a single
        // (unsupported) response, not a list, and must reach normalizeItem() to be
        // rejected with ResponseInvalidException rather than be iterated key-by-key.
        $isBatch = is_iterable($response)
            && !$response instanceof BotResponse
            && !(is_array($response) && !array_is_list($response));

        $items = $isBatch ? $response : [$response];

        $views    = [];
        $redirect = null;

        foreach ($items as $item) {
            $normalized = $this->normalizeItem($item, $output);

            if ($normalized === null) {
                continue;
            }

            [$view, $station] = $normalized;

            $views[] = $this->injectTelegramIds($output, $view);

            if ($station !== null) {
                $redirect = $station; // last-write-wins across the batch
            }
        }

        if (empty($views)) {
            return null;
        }

        $output['response']['views']    = $views;
        $output['response']['redirect'] = $redirect ?? $output['route']['form'];

        return $output;
    }

    /**
     * Normalize a single controller-response item into [viewPayload, station|null].
     *
     * Returns null for empty items (skipped). Throws for unsupported types.
     *
     * @param  BotResponse|string|null  $response
     * @param  array<string, mixed>     $output
     * @return array{0: array<string, mixed>, 1: string|null}|null
     *
     * @throws ResponseInvalidException
     */
    private function normalizeItem(mixed $response, array $output): ?array
    {
        if ($response instanceof BotResponse) {
            return [$response->contents ?? [], $response->station];
        }

        if (is_string($response)) {
            // Plain string shortcut: literal text with no parse_mode.
            return [['method' => 'sendMessage', 'text' => $response], null];
        }

        if (empty($response)) {
            return null;
        }

        throw new ResponseInvalidException($output['route']['uses']);
    }

    /**
     * Inject required Telegram IDs (chat_id, callback_query_id, message_id) into
     * a view payload based on the update object and the API method being called.
     *
     * @param  array<string, mixed> $output
     * @param  array<string, mixed> $view
     * @return array<string, mixed>
     */
    private function injectTelegramIds(array $output, array $view): array
    {
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
