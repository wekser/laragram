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

namespace Wekser\Laragram\Telegram\Inline;

/**
 * Fluent builder for the Telegram answerInlineQuery payload.
 *
 * Collects InlineQueryResult objects plus the answer-level options (cache time,
 * personal results, pagination offset, results button) into the parameter array
 * consumed by BotResponse::inlineResults() / BotAPI::answerInlineQuery().
 *
 * Usage:
 *
 *   InlineResults::make()
 *       ->article('1', 'Say hello', 'Hello there!')
 *       ->photo('2', 'https://ex.com/p.jpg', title: 'A photo')
 *       ->cache(300)
 *       ->toArray();
 *
 * Text and captions are passed through verbatim (like the keyboard builders) —
 * write HTML markup directly or pre-escape untrusted values yourself.
 */
class InlineResults
{
    /** @var array<int, array<string, mixed>> */
    private array $results = [];

    /** Answer-level options merged into the payload by toArray(). */
    private ?int    $cacheTime  = null;
    private bool    $isPersonal = false;
    private ?string $nextOffset = null;
    /** @var array<string, mixed>|null */
    private ?array $button = null;

    /** Telegram caps a single answer at 50 results. */
    private const MAX_RESULTS = 50;

    private function __construct() {}

    /** Create a new builder instance. */
    public static function make(): static
    {
        return new static();
    }

    // -------------------------------------------------------------------------
    // Result types (by URL / text)
    // -------------------------------------------------------------------------

    /**
     * Article result — sends a text message when picked.
     *
     * @param array<string, mixed>|null $replyMarkup InlineKeyboard::make()->...->toArray()
     */
    public function article(
        string  $id,
        string  $title,
        string  $text,
        ?string $description  = null,
        ?string $parseMode    = 'HTML',
        ?array  $replyMarkup  = null,
        ?string $thumbnailUrl = null,
    ): static {
        return $this->add(array_filter([
            'type'                  => 'article',
            'id'                    => $id,
            'title'                 => $title,
            'input_message_content' => array_filter([
                'message_text' => $text,
                'parse_mode'   => $parseMode,
            ], static fn ($v) => $v !== null),
            'description'   => $description,
            'reply_markup'  => $replyMarkup,
            'thumbnail_url' => $thumbnailUrl,
        ], static fn ($v) => $v !== null));
    }

    /**
     * Photo result (by URL). Thumbnail defaults to the photo URL.
     *
     * @param array<string, mixed>|null $replyMarkup
     */
    public function photo(
        string  $id,
        string  $photoUrl,
        ?string $thumbnailUrl = null,
        ?string $title        = null,
        ?string $caption      = null,
        ?string $parseMode    = 'HTML',
        ?array  $replyMarkup  = null,
    ): static {
        return $this->add(array_filter([
            'type'          => 'photo',
            'id'            => $id,
            'photo_url'     => $photoUrl,
            'thumbnail_url' => $thumbnailUrl ?? $photoUrl,
            'title'         => $title,
            'caption'       => $caption,
            'parse_mode'    => $caption !== null ? $parseMode : null,
            'reply_markup'  => $replyMarkup,
        ], static fn ($v) => $v !== null));
    }

    /**
     * Animated GIF result (by URL). Thumbnail defaults to the GIF URL.
     *
     * @param array<string, mixed>|null $replyMarkup
     */
    public function gif(
        string  $id,
        string  $gifUrl,
        ?string $thumbnailUrl = null,
        ?string $title        = null,
        ?string $caption      = null,
        ?string $parseMode    = 'HTML',
        ?array  $replyMarkup  = null,
    ): static {
        return $this->add(array_filter([
            'type'          => 'gif',
            'id'            => $id,
            'gif_url'       => $gifUrl,
            'thumbnail_url' => $thumbnailUrl ?? $gifUrl,
            'title'         => $title,
            'caption'       => $caption,
            'parse_mode'    => $caption !== null ? $parseMode : null,
            'reply_markup'  => $replyMarkup,
        ], static fn ($v) => $v !== null));
    }

    /**
     * Video result (by URL). $mimeType is 'text/html' or 'video/mp4'.
     *
     * @param array<string, mixed>|null $replyMarkup
     * @param array<string, mixed>|null $inputMessageContent Required by Telegram when $videoUrl is an embedded (text/html) player.
     */
    public function video(
        string  $id,
        string  $videoUrl,
        string  $mimeType,
        string  $thumbnailUrl,
        string  $title,
        ?string $caption             = null,
        ?string $parseMode           = 'HTML',
        ?array  $replyMarkup         = null,
        ?array  $inputMessageContent = null,
    ): static {
        return $this->add(array_filter([
            'type'                  => 'video',
            'id'                    => $id,
            'video_url'             => $videoUrl,
            'mime_type'             => $mimeType,
            'thumbnail_url'         => $thumbnailUrl,
            'title'                 => $title,
            'caption'               => $caption,
            'parse_mode'            => $caption !== null ? $parseMode : null,
            'reply_markup'          => $replyMarkup,
            'input_message_content' => $inputMessageContent,
        ], static fn ($v) => $v !== null));
    }

    /**
     * Document result (by URL). $mimeType e.g. 'application/pdf', 'application/zip'.
     *
     * @param array<string, mixed>|null $replyMarkup
     */
    public function document(
        string  $id,
        string  $title,
        string  $documentUrl,
        string  $mimeType,
        ?string $caption     = null,
        ?string $description = null,
        ?string $parseMode   = 'HTML',
        ?array  $replyMarkup = null,
    ): static {
        return $this->add(array_filter([
            'type'         => 'document',
            'id'           => $id,
            'title'        => $title,
            'document_url' => $documentUrl,
            'mime_type'    => $mimeType,
            'caption'      => $caption,
            'parse_mode'   => $caption !== null ? $parseMode : null,
            'description'  => $description,
            'reply_markup' => $replyMarkup,
        ], static fn ($v) => $v !== null));
    }

    // -------------------------------------------------------------------------
    // Result types (by cached file_id)
    // -------------------------------------------------------------------------

    /**
     * Cached photo result referenced by an existing file_id.
     *
     * @param array<string, mixed>|null $replyMarkup
     */
    public function cachedPhoto(
        string  $id,
        string  $fileId,
        ?string $title       = null,
        ?string $caption     = null,
        ?string $parseMode   = 'HTML',
        ?array  $replyMarkup = null,
    ): static {
        return $this->add(array_filter([
            'type'           => 'photo',
            'id'             => $id,
            'photo_file_id'  => $fileId,
            'title'          => $title,
            'caption'        => $caption,
            'parse_mode'     => $caption !== null ? $parseMode : null,
            'reply_markup'   => $replyMarkup,
        ], static fn ($v) => $v !== null));
    }

    /**
     * Sticker result referenced by an existing file_id (cached stickers only).
     *
     * @param array<string, mixed>|null $replyMarkup
     */
    public function sticker(string $id, string $fileId, ?array $replyMarkup = null): static
    {
        return $this->add(array_filter([
            'type'            => 'sticker',
            'id'              => $id,
            'sticker_file_id' => $fileId,
            'reply_markup'    => $replyMarkup,
        ], static fn ($v) => $v !== null));
    }

    /**
     * Append a raw InlineQueryResult array for any type not covered above.
     *
     * @param array<string, mixed> $result Must include 'type' and 'id'.
     */
    public function raw(array $result): static
    {
        return $this->add($result);
    }

    // -------------------------------------------------------------------------
    // Answer-level options
    // -------------------------------------------------------------------------

    /** Seconds the result may be cached on Telegram's servers (cache_time). */
    public function cache(int $seconds): static
    {
        $this->cacheTime = $seconds;
        return $this;
    }

    /** Cache results on the querying user's side only (is_personal). */
    public function personal(bool $isPersonal = true): static
    {
        $this->isPersonal = $isPersonal;
        return $this;
    }

    /** Offset a client sends in the next query to page through results. */
    public function nextOffset(string $offset): static
    {
        $this->nextOffset = $offset;
        return $this;
    }

    /**
     * Button shown above the results (InlineQueryResultsButton, Bot API 6.7).
     * Provide a start_parameter (deep-link into a private chat) OR a web_app.
     *
     * @param array<string, mixed>|null $webApp e.g. ['url' => 'https://...']
     */
    public function button(string $text, ?string $startParameter = null, ?array $webApp = null): static
    {
        $this->button = array_filter([
            'text'            => $text,
            'start_parameter' => $startParameter,
            'web_app'         => $webApp,
        ], static fn ($v) => $v !== null);

        return $this;
    }

    // -------------------------------------------------------------------------
    // Output
    // -------------------------------------------------------------------------

    /**
     * Build the parameter array for answerInlineQuery (minus inline_query_id,
     * which ResponseTransformer injects from the update).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $this->assertUniqueIds();

        return array_filter([
            'results'     => $this->results,
            'cache_time'  => $this->cacheTime,
            'is_personal' => $this->isPersonal ?: null,
            'next_offset' => $this->nextOffset,
            'button'      => $this->button,
        ], static fn ($v) => $v !== null);
    }

    /** Number of results collected so far. */
    public function count(): int
    {
        return count($this->results);
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $result
     * @throws \OverflowException|\InvalidArgumentException
     */
    private function add(array $result): static
    {
        if (count($this->results) >= self::MAX_RESULTS) {
            throw new \OverflowException(
                'A single inline answer may contain at most ' . self::MAX_RESULTS . ' results.'
            );
        }

        if (empty($result['type']) || (($result['id'] ?? '') === '')) {
            throw new \InvalidArgumentException('An inline result requires a non-empty type and id.');
        }

        $this->results[] = $result;

        return $this;
    }

    /**
     * @throws \InvalidArgumentException when two results share an id.
     */
    private function assertUniqueIds(): void
    {
        $ids = array_column($this->results, 'id');

        if (count($ids) !== count(array_unique($ids))) {
            throw new \InvalidArgumentException('Inline result ids must be unique within one answer.');
        }
    }
}
