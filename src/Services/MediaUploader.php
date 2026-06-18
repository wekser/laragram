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

namespace Wekser\Laragram\Services;

use Wekser\Laragram\BotAPI;

/**
 * Uploads media files to Telegram and returns the permanent file_id.
 *
 * Accepts a local file path (uploaded as multipart/form-data via CURLFile)
 * or a public URL (Telegram downloads it server-side). Returns the file_id
 * from Telegram's response so it can be reused in subsequent BotResponse calls
 * without re-uploading.
 *
 * Usage in a controller:
 *
 *   public function __construct(private readonly MediaUploader $uploader) {}
 *
 *   public function send(BotRequest $request): BotResponse
 *   {
 *       $fileId = $this->uploader->upload(
 *           type:   'document',
 *           source: storage_path('reports/monthly.pdf'),
 *           chatId: $request->get('chat')['id'],
 *       );
 *
 *       return BotResponse::document($fileId, 'Your report is ready');
 *   }
 */
class MediaUploader
{
    /**
     * Maps a media type name to the Telegram API send method and the response
     * field that carries the uploaded file's metadata (including file_id).
     */
    private const TYPE_MAP = [
        'photo'      => ['method' => 'sendPhoto',     'field' => 'photo'],
        'document'   => ['method' => 'sendDocument',  'field' => 'document'],
        'audio'      => ['method' => 'sendAudio',     'field' => 'audio'],
        'video'      => ['method' => 'sendVideo',     'field' => 'video'],
        'voice'      => ['method' => 'sendVoice',     'field' => 'voice'],
        'animation'  => ['method' => 'sendAnimation', 'field' => 'animation'],
        'video_note' => ['method' => 'sendVideoNote', 'field' => 'video_note'],
        'sticker'    => ['method' => 'sendSticker',   'field' => 'sticker'],
    ];

    public function __construct(private readonly BotAPI $api) {}

    /**
     * Upload a file to Telegram and return its permanent file_id.
     *
     * The returned file_id can be passed to BotResponse::photo(), document(),
     * etc. on any subsequent request — Telegram caches the file on its servers.
     *
     * @param  string $type    Media type: photo, document, audio, video,
     *                         voice, animation, video_note, sticker.
     * @param  string $source  Absolute local file path or public URL.
     * @param  int    $chatId  Target chat_id. Telegram requires a destination
     *                         even for "upload-only" operations — use any chat
     *                         the bot has access to (e.g. your own user id).
     * @return string          Permanent file_id assigned by Telegram.
     *
     * @throws \InvalidArgumentException  Unsupported type or unresolvable source.
     * @throws \RuntimeException          Telegram response contained no file_id.
     */
    public function upload(string $type, string $source, int $chatId): string
    {
        $config = $this->resolveType($type);
        $file   = $this->resolveSource($source);

        $result = $this->api->{$config['method']}([
            'chat_id'        => $chatId,
            $config['field'] => $file,
        ]);

        return $this->extractFileId($config['field'], $result);
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * Validate and return the TYPE_MAP entry for the given type.
     *
     * @return array{method: string, field: string}
     * @throws \InvalidArgumentException
     */
    private function resolveType(string $type): array
    {
        if (!array_key_exists($type, self::TYPE_MAP)) {
            throw new \InvalidArgumentException(sprintf(
                "Unsupported media type '%s'. Supported types: %s.",
                $type,
                implode(', ', array_keys(self::TYPE_MAP))
            ));
        }

        return self::TYPE_MAP[$type];
    }

    /**
     * Resolve the upload source to a CURLFile (local path) or a string (URL).
     *
     * - Local path  → \CURLFile  (cURL sends multipart/form-data automatically)
     * - Public URL  → string     (Telegram downloads the file server-side)
     *
     * @throws \InvalidArgumentException When source is neither a file nor a valid URL.
     */
    private function resolveSource(string $source): string|\CURLFile
    {
        if (is_file($source)) {
            return new \CURLFile($source);
        }

        if (filter_var($source, FILTER_VALIDATE_URL) !== false) {
            return $source;
        }

        throw new \InvalidArgumentException(
            "Source must be a valid local file path or a public URL. Got: '{$source}'."
        );
    }

    /**
     * Pull the file_id out of the Telegram API response.
     *
     * photo is special: Telegram returns an array of PhotoSize objects ordered
     * by increasing dimensions. The last element is the largest (canonical) copy
     * and has the most broadly accepted file_id.
     *
     * All other types return a single object at $result[$field]['file_id'].
     *
     * @throws \RuntimeException When the response contains no file_id.
     */
    private function extractFileId(string $field, array $result): string
    {
        if ($field === 'photo') {
            $sizes  = $result['photo'] ?? [];
            $fileId = !empty($sizes)
                ? ($sizes[array_key_last($sizes)]['file_id'] ?? null)
                : null;
        } else {
            $fileId = $result[$field]['file_id'] ?? null;
        }

        if ($fileId === null) {
            throw new \RuntimeException(
                "Could not extract file_id from the Telegram response for field '{$field}'."
            );
        }

        return (string) $fileId;
    }
}
