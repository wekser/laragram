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

/**
 * Transforms a controller/closure response into the output array consumed by
 * Laragram::back() and the CallbackFormed event.
 */
class ResponseTransformer
{
    /** answer* methods → the payload key their query object's id is injected into. */
    private const QUERY_ID_PARAM = [
        'answerCallbackQuery'    => 'callback_query_id',
        'answerInlineQuery'      => 'inline_query_id',
        'answerPreCheckoutQuery' => 'pre_checkout_query_id',
        'answerShippingQuery'    => 'shipping_query_id',
    ];

    /** answer* methods that carry no chat_id (answerCallbackQuery does, so it is absent). */
    private const QUERY_ANSWER_WITHOUT_CHAT = [
        'answerInlineQuery',
        'answerPreCheckoutQuery',
        'answerShippingQuery',
    ];

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

        // The answer* methods each take the id of their query object (the update
        // object here IS that query). answerCallbackQuery is the exception that
        // also carries a chat_id, so it falls through to the chat_id injection
        // below; the others answer the query only and return early.
        if (isset(self::QUERY_ID_PARAM[$method])) {
            $view[self::QUERY_ID_PARAM[$method]] ??= Arr::get($object, 'id');

            if (in_array($method, self::QUERY_ANSWER_WITHOUT_CHAT, true)) {
                return $view;
            }
        }

        // deleteMessage / editMessageText / setMessageReaction require message_id
        // from the triggering message (or the message_reaction update object,
        // which carries message_id at its top level)
        if (in_array($method, ['deleteMessage', 'editMessageText', 'setMessageReaction'], true) && !isset($view['message_id'])) {
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
        // nested inside message.chat.id. When absent (poll_answer, some
        // my_chat_member, …) fall back to the chat the update originated in,
        // resolved from the payload upstream — in a group this keeps the reply in
        // the group instead of DMing the member.
        $view['chat_id'] =
            Arr::get($object, 'chat.id') ??
            Arr::get($object, 'message.chat.id') ??
            ($output['update']['chat_id'] ?? null);

        if ($view['chat_id'] === null) {
            app('log')->warning('laragram: could not determine chat_id for Telegram API call', [
                'method'    => $method,
                'update_id' => $output['update']['id'] ?? null,
            ]);
        }

        return $view;
    }
}
