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

namespace Wekser\Laragram;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Wekser\Laragram\Support\IncomingFile;
use Wekser\Laragram\Support\Media;

/**
 * BotRequest handles Telegram bot request data and provides convenient access methods.
 * 
 * @package Wekser\Laragram
 */
class BotRequest
{
    /**
     * The current request data.
     */
    private array $request;

    /**
     * BotRequest Constructor
     *
     * @param array $request The request data from Telegram
     */
    public function __construct(array $request = [])
    {
        $this->request = $request;
    }

    /**
     * Get route information.
     *
     * @param string|null $key Specific route key to retrieve
     * @return array|string|null Route data or specific key value
     */
    public function route(?string $key = null): array|string|null
    {
        if ($key === null) {
            return $this->getNestedData('route');
        }

        return $this->getNestedData("route.{$key}");
    }

    /**
     * Get data from the update object.
     *
     * @param string $key The key to retrieve
     * @param mixed $default Default value if key not found
     * @return mixed The requested data
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->getNestedData("update.object.{$key}", $default);
    }

    /**
     * Get all callback data.
     *
     * @return array All callback data
     */
    public function all(): array
    {
        return $this->getNestedData('data.all', []);
    }

    /**
     * Get specific input value from callback data.
     *
     * @param string $key The input key
     * @param mixed $default Default value if key not found
     * @return mixed The input value
     */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->getNestedData("data.all.{$key}", $default);
    }

    /**
     * Get the file_id of the file attached to the incoming update, if any.
     *
     * Scans the common media fields in precedence order; for a photo (an array
     * of sizes) the largest — the last — size's file_id is returned.
     *
     * @return string|null The file_id or null when the update carries no file
     */
    public function fileId(): ?string
    {
        $photoFileId = Media::largestPhotoFileId($this->get('photo'));

        if ($photoFileId !== null) {
            return $photoFileId;
        }

        foreach (['document', 'video', 'audio', 'voice', 'animation', 'video_note', 'sticker'] as $type) {
            $fileId = $this->get("{$type}.file_id");

            if ($fileId !== null) {
                return (string) $fileId;
            }
        }

        return null;
    }

    /**
     * Get a handle to the file attached to the incoming update, if any.
     *
     * The handle downloads / stores the file via the MediaDownloader service:
     *
     *   $request->file()?->save('local', 'inbox/doc.pdf');
     *
     * @return IncomingFile|null Null when the update carries no file
     */
    public function file(): ?IncomingFile
    {
        $fileId = $this->fileId();

        return $fileId !== null ? new IncomingFile($fileId) : null;
    }

    /**
     * Get the complete update object.
     *
     * @return array The update data
     */
    public function update(): array
    {
        return $this->getNestedData('update', []);
    }

    /**
     * Get the query from callback.
     *
     * @return string|null The query string
     */
    public function query(): ?string
    {
        return $this->getNestedData('data.query');
    }

    /**
     * Get all parameter keys.
     *
     * @return array Array of parameter keys
     */
    public function keys(): array
    {
        return array_keys($this->all());
    }

    /**
     * Check if a parameter exists in the callback data.
     *
     * @param string $key The parameter key to check
     * @return bool True if parameter exists
     */
    public function has(string $key): bool
    {
        return Arr::has($this->all(), $key);
    }

    /**
     * Check if multiple parameters exist.
     *
     * @param array $keys Array of keys to check
     * @return bool True if all keys exist
     */
    public function hasAll(array $keys): bool
    {
        return Arr::has($this->all(), $keys);
    }

    /**
     * Check if any of the parameters exist.
     *
     * @param array $keys Array of keys to check
     * @return bool True if any key exists
     */
    public function hasAny(array $keys): bool
    {
        foreach ($keys as $key) {
            if ($this->has($key)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get only specific keys from the data.
     *
     * @param array $keys Keys to retrieve
     * @return array Filtered data
     */
    public function only(array $keys): array
    {
        return Arr::only($this->all(), $keys);
    }

    /**
     * Get all data except specified keys.
     *
     * @param array $keys Keys to exclude
     * @return array Filtered data
     */
    public function except(array $keys): array
    {
        return Arr::except($this->all(), $keys);
    }

    /**
     * Validate the callback data against provided rules.
     *
     * @param array $rules Validation rules
     * @param array $messages Custom error messages
     * @param array $customAttributes Custom attribute names
     * @return array Validated data
     * @throws ValidationException
     */
    public function validate(array $rules, array $messages = [], array $customAttributes = []): array
    {
        $validator = Validator::make($this->all(), $rules, $messages, $customAttributes);
        
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * Get the raw request data.
     *
     * @return array The complete request data
     */
    public function getRequest(): array
    {
        return $this->request;
    }

    /**
     * Check if the request is empty.
     *
     * @return bool True if request has no data
     */
    public function isEmpty(): bool
    {
        return empty($this->request);
    }

    /**
     * Get the number of named input parameters.
     *
     * @return int Number of parameters in data.all
     */
    public function size(): int
    {
        return count($this->all());
    }

    /**
     * Get user information from the request.
     *
     * @return array|null User data or null if not available
     */
    public function user(): ?array
    {
        return $this->get('from');
    }

    /**
     * Get chat information from the request.
     *
     * Most update types carry `chat` at the top level of their object; for
     * callback_query it is nested inside the attached message.
     *
     * @return array|null Chat data or null if not available
     */
    public function chat(): ?array
    {
        return $this->get('chat') ?? $this->get('message.chat');
    }

    /**
     * Get the type of the originating chat.
     *
     * @return string|null private | group | supergroup | channel, or null
     */
    public function chatType(): ?string
    {
        return $this->chat()['type'] ?? null;
    }

    /**
     * Whether the update came from a private (1-on-1) chat.
     */
    public function isPrivate(): bool
    {
        return $this->chatType() === 'private';
    }

    /**
     * Whether the update came from a group or supergroup chat.
     */
    public function isGroup(): bool
    {
        return in_array($this->chatType(), ['group', 'supergroup'], true);
    }

    /**
     * Whether the update came from a supergroup chat specifically.
     */
    public function isSupergroup(): bool
    {
        return $this->chatType() === 'supergroup';
    }

    /**
     * Whether the update came from a channel.
     */
    public function isChannel(): bool
    {
        return $this->chatType() === 'channel';
    }

    /**
     * Get message information from the request.
     *
     * For message/edited_message/channel_post events the update object IS the
     * message, so we return it directly. For callback_query the message is a
     * nested field inside the callback object.
     *
     * @return array|null Message data or null if not available
     */
    public function message(): ?array
    {
        return match($this->route('event')) {
            'message', 'edited_message', 'channel_post', 'edited_channel_post'
                => $this->getNestedData('update.object'),
            default => $this->get('message'),
        };
    }

    /**
     * Get callback query information.
     *
     * @return array|null Callback query data or null if not available
     */
    public function callbackQuery(): ?array
    {
        return $this->route('event') === 'callback_query'
            ? $this->getNestedData('update.object')
            : null;
    }

    /**
     * Get inline query information.
     *
     * @return array|null Inline query data or null if not available
     */
    public function inlineQuery(): ?array
    {
        return $this->route('event') === 'inline_query'
            ? $this->getNestedData('update.object')
            : null;
    }

    /**
     * Get the chosen inline result object (a user picked one of your inline
     * results). Requires inline feedback to be enabled with @BotFather.
     *
     * @return array|null Chosen inline result data or null if not this update type
     */
    public function chosenInlineResult(): ?array
    {
        return $this->route('event') === 'chosen_inline_result'
            ? $this->getNestedData('update.object')
            : null;
    }

    /**
     * Get the pre-checkout query object (payment confirmation step).
     *
     * @return array|null Pre-checkout query data or null if not this update type
     */
    public function preCheckoutQuery(): ?array
    {
        return $this->route('event') === 'pre_checkout_query'
            ? $this->getNestedData('update.object')
            : null;
    }

    /**
     * Get the shipping query object (flexible-price delivery step).
     *
     * @return array|null Shipping query data or null if not this update type
     */
    public function shippingQuery(): ?array
    {
        return $this->route('event') === 'shipping_query'
            ? $this->getNestedData('update.object')
            : null;
    }

    /**
     * Get the message reaction object (a user changed their reaction on a message).
     *
     * Carries chat, message_id, the acting user (or actor_chat for anonymous
     * reactions), old_reaction and new_reaction lists.
     *
     * @return array|null Message reaction data or null if not this update type
     */
    public function messageReaction(): ?array
    {
        return $this->route('event') === 'message_reaction'
            ? $this->getNestedData('update.object')
            : null;
    }

    /**
     * Get the successful payment object carried by a completed-payment message.
     *
     * Arrives as a field on a "message" update — route it with
     * BotRoute::get('message', 'successful_payment').
     *
     * @return array|null Successful payment data or null if absent
     */
    public function successfulPayment(): ?array
    {
        return $this->get('successful_payment');
    }

    /**
     * Get nested data using dot notation.
     *
     * @param string $key Dot notation key
     * @param mixed $default Default value
     * @return mixed The requested data
     */
    private function getNestedData(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->request, $key, $default);
    }

    /**
     * Check if the current update matches the given type.
     *
     * @param string $type Update type (message, callback_query, etc.)
     * @return bool True if update type matches
     */
    public function isUpdateType(string $type): bool
    {
        return $this->getUpdateType() === $type;
    }

    /**
     * Get the update type from the matched route.
     *
     * @return string|null The update type or null if not available
     */
    public function getUpdateType(): ?string
    {
        return $this->route('event');
    }

    /**
     * Convert request to array representation.
     *
     * @return array Array representation of the request
     */
    public function toArray(): array
    {
        return $this->request;
    }

    /**
     * Convert request to JSON string.
     *
     * @param int $options JSON encoding options
     * @return string JSON representation
     * @throws \RuntimeException
     */
    public function toJson(int $options = 0): string
    {
        $json = json_encode($this->request, $options);

        if ($json === false) {
            throw new \RuntimeException('Failed to encode request as JSON: ' . json_last_error_msg());
        }

        return $json;
    }
}