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

namespace Wekser\Laragram\Support;

use Wekser\Laragram\Services\MediaDownloader;

/**
 * Lightweight handle to a file attached to an incoming update, returned by
 * BotRequest::file(). Wraps the file_id and defers to the MediaDownloader
 * service (alias laragram.downloader) for the actual bytes / storage.
 *
 *   $request->file()?->save('local', 'inbox/receipt.jpg');
 *   $bytes = $request->file()?->bytes();
 */
final class IncomingFile
{
    public function __construct(private readonly string $fileId) {}

    /** The Telegram file_id. */
    public function id(): string
    {
        return $this->fileId;
    }

    /** Download and return the raw file bytes. */
    public function bytes(): string
    {
        return $this->downloader()->download($this->fileId);
    }

    /**
     * Download and store the file on a Laravel filesystem disk.
     *
     * @param  string|null $disk Target disk (defaults to config laragram.downloads.disk).
     * @param  string|null $path Target path (defaults to Telegram's basename).
     * @return string            The stored path.
     */
    public function save(?string $disk = null, ?string $path = null): string
    {
        return $this->downloader()->save($this->fileId, $disk, $path);
    }

    private function downloader(): MediaDownloader
    {
        return app('laragram.downloader');
    }
}
