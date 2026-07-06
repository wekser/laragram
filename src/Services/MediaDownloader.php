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

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Wekser\Laragram\BotAPI;

/**
 * Downloads a file a user sent to the bot and returns its bytes or stores it.
 *
 * The mirror image of MediaUploader: where MediaUploader turns a local file/URL
 * into a Telegram file_id, MediaDownloader turns an incoming file_id back into
 * the raw bytes (getFile → fetch from api.telegram.org/file/...). Bound as a
 * singleton under the "laragram.downloader" alias.
 *
 * Usage in a controller (a user just sent a document):
 *
 *   public function __construct(private readonly MediaDownloader $downloader) {}
 *
 *   public function receive(BotRequest $request): BotResponse
 *   {
 *       $path = $this->downloader->save($request->fileId(), 'local', 'inbox/report.pdf');
 *       // ...process storage_path($path)...
 *       return BotResponse::text('Got it!');
 *   }
 */
class MediaDownloader
{
    /** Base URL for the Telegram file download endpoint. */
    private const API_FILE_BASE_URL = 'https://api.telegram.org/file/bot';

    /** Host the download URL must resolve to — guards against SSRF via file_path. */
    private const API_HOST = 'api.telegram.org';

    public function __construct(private readonly BotAPI $api) {}

    /**
     * Resolve a file_id to its Telegram File object (getFile).
     *
     * @return array{file_id: string, file_unique_id: string, file_size?: int, file_path?: string}
     */
    public function getFile(string $fileId): array
    {
        return (array) $this->api->getFile(['file_id' => $fileId]);
    }

    /**
     * Download the file for a file_id and return its raw bytes.
     */
    public function download(string $fileId): string
    {
        return $this->fetch($this->getFile($fileId));
    }

    /**
     * Download the file for a file_id and store it on a Laravel filesystem disk.
     *
     * @param  string      $fileId Telegram file_id from an incoming update.
     * @param  string|null $disk   Target disk (defaults to config laragram.downloads.disk).
     * @param  string|null $path   Target path (defaults to the basename Telegram assigned).
     * @return string              The stored path (relative to the disk root).
     */
    public function save(string $fileId, ?string $disk = null, ?string $path = null): string
    {
        $file  = $this->getFile($fileId);
        $bytes = $this->fetch($file);

        $disk ??= (string) config('laragram.downloads.disk', 'local');
        $path ??= basename((string) $file['file_path']);

        Storage::disk($disk)->put($path, $bytes);

        return $path;
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * Fetch the bytes for a resolved File object, enforcing the size cap and the
     * host guard.
     *
     * @param array<string, mixed> $file
     * @throws \RuntimeException
     */
    private function fetch(array $file): string
    {
        $filePath = (string) ($file['file_path'] ?? '');

        if ($filePath === '') {
            throw new \RuntimeException('Telegram getFile returned no file_path (file too big or expired).');
        }

        $this->assertSafeFilePath($filePath);
        $this->assertWithinSizeLimit((int) ($file['file_size'] ?? 0));

        $url = self::API_FILE_BASE_URL . $this->token() . '/' . $filePath;

        if (strcasecmp((string) parse_url($url, PHP_URL_HOST), self::API_HOST) !== 0) {
            throw new \RuntimeException('Refusing to download from a non-Telegram host.');
        }

        // Bound the request so a stalled file server cannot hang the worker
        // (mirrors BotClient's timeout hardening).
        $response = Http::timeout($this->timeout())->connectTimeout(10)->get($url);

        if ($response->failed()) {
            throw new \RuntimeException("Failed to download file (HTTP {$response->status()}).");
        }

        $body = $response->body();

        // Re-check against the actual byte length: getFile does not always report
        // file_size, so the pre-fetch guard can be bypassed by a sizeless File.
        $this->assertWithinSizeLimit(strlen($body));

        return $body;
    }

    /**
     * @throws \RuntimeException when the file_path could redirect the request off Telegram.
     */
    private function assertSafeFilePath(string $filePath): void
    {
        if (str_contains($filePath, '..') || preg_match('#^[a-z][a-z0-9+.\-]*://#i', $filePath)) {
            throw new \RuntimeException("Unsafe Telegram file_path: '{$filePath}'.");
        }
    }

    /**
     * @throws \RuntimeException when $size exceeds config laragram.downloads.max_size.
     */
    private function assertWithinSizeLimit(int $size): void
    {
        $max = (int) config('laragram.downloads.max_size', 20 * 1024 * 1024);

        if ($max > 0 && $size > $max) {
            throw new \RuntimeException("File is {$size} bytes, exceeding the {$max}-byte download limit.");
        }
    }

    /**
     * Request timeout in seconds (bounded so a hung download cannot stall a worker).
     */
    private function timeout(): int
    {
        return max(1, (int) config('laragram.downloads.timeout', 30));
    }

    private function token(): string
    {
        $token = (string) config('laragram.telegram.token');

        if ($token === '') {
            throw new \RuntimeException('Laragram: LARAGRAM_BOT_TOKEN is not configured.');
        }

        return $token;
    }
}
