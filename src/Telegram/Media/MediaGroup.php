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

namespace Wekser\Laragram\Telegram\Media;

/**
 * Fluent builder for the Telegram sendMediaGroup payload.
 *
 * Usage:
 *
 *   MediaGroup::make()
 *       ->photo('AgACAgIAA...', caption: 'First photo')
 *       ->photo('AgACAgIAB...')
 *       ->video('BAACAgIAA...', caption: 'A video')
 *       ->toArray();
 *
 * Pass the result to BotAPI::sendMediaGroup(['chat_id' => $id, 'media' => $media]).
 */
class MediaGroup
{
    /** @var array<int, array<string, mixed>> */
    private array $items = [];

    /** Maximum items per Telegram media group. */
    private const MAX_ITEMS = 10;

    private function __construct() {}

    /** Create a new builder instance. */
    public static function make(): static
    {
        return new static();
    }

    // -------------------------------------------------------------------------
    // Media types
    // -------------------------------------------------------------------------

    /**
     * Add a photo to the group.
     *
     * @param string      $media    file_id, file URL, or 'attach://name'.
     * @param string|null $caption  Optional caption (only on the first media item).
     * @param string|null $parseMode  Parse mode for the caption.
     */
    public function photo(string $media, ?string $caption = null, ?string $parseMode = null): static
    {
        return $this->add('photo', $media, $caption, $parseMode);
    }

    /**
     * Add a video to the group.
     *
     * @param string      $media    file_id, file URL, or 'attach://name'.
     * @param string|null $caption  Optional caption (only on the first media item).
     * @param string|null $parseMode  Parse mode for the caption.
     * @param int|null    $width    Video width.
     * @param int|null    $height   Video height.
     * @param int|null    $duration Video duration in seconds.
     */
    public function video(
        string  $media,
        ?string $caption   = null,
        ?string $parseMode = null,
        ?int    $width     = null,
        ?int    $height    = null,
        ?int    $duration  = null,
    ): static {
        $extra = array_filter(
            compact('width', 'height', 'duration'),
            static fn ($v) => $v !== null,
        );

        return $this->add('video', $media, $caption, $parseMode, $extra);
    }

    /**
     * Add an audio file to the group.
     */
    public function audio(string $media, ?string $caption = null, ?string $parseMode = null): static
    {
        return $this->add('audio', $media, $caption, $parseMode);
    }

    /**
     * Add a document to the group.
     */
    public function document(string $media, ?string $caption = null, ?string $parseMode = null): static
    {
        return $this->add('document', $media, $caption, $parseMode);
    }

    // -------------------------------------------------------------------------
    // Output
    // -------------------------------------------------------------------------

    /**
     * Return the media array ready to be passed as the 'media' parameter to
     * BotAPI::sendMediaGroup().
     *
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array
    {
        return $this->items;
    }

    /**
     * Return how many items are in the group.
     */
    public function count(): int
    {
        return count($this->items);
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private function add(
        string  $type,
        string  $media,
        ?string $caption,
        ?string $parseMode,
        array   $extra = [],
    ): static {
        if (count($this->items) >= self::MAX_ITEMS) {
            throw new \OverflowException(
                'A Telegram media group may contain at most ' . self::MAX_ITEMS . ' items.'
            );
        }

        $item = array_filter(
            array_merge(
                ['type' => $type, 'media' => $media],
                $caption   !== null ? ['caption'    => $caption]   : [],
                $parseMode !== null ? ['parse_mode' => $parseMode] : [],
                $extra,
            ),
            static fn ($v) => $v !== null,
        );

        $this->items[] = $item;

        return $this;
    }
}
