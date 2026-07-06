<?php

namespace Wekser\Laragram\Support;

/**
 * Helpers for extracting data from Telegram media payloads.
 */
class Media
{
    /**
     * The file_id of the largest size of a Telegram photo.
     *
     * Telegram represents a photo as an array of PhotoSize objects ordered by
     * increasing dimensions, so the last element is the largest (canonical)
     * copy with the most broadly accepted file_id. Returns null when $sizes is
     * not a non-empty array or the largest size carries no file_id.
     */
    public static function largestPhotoFileId(mixed $sizes): ?string
    {
        if (!is_array($sizes) || $sizes === []) {
            return null;
        }

        $fileId = end($sizes)['file_id'] ?? null;

        return $fileId !== null ? (string) $fileId : null;
    }
}
