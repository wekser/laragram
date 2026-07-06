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

namespace Wekser\Laragram\Tests\Unit\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;
use Wekser\Laragram\Services\MediaDownloader;
use Wekser\Laragram\Tests\TestCase;

#[CoversClass(MediaDownloader::class)]
class MediaDownloaderTest extends TestCase
{
    private const BYTES = 'raw-file-bytes';

    protected function tearDown(): void
    {
        // Storage::fake writes into the fixtures tree (base path is tests/Fixtures);
        // remove the scratch disk so it is never committed or accumulated.
        File::deleteDirectory(storage_path('framework/testing'));

        parent::tearDown();
    }

    /** Stub the Telegram file endpoint. Each test starts with a clean app. */
    private function fakeDownload(string $body = self::BYTES, int $status = 200): void
    {
        Http::fake(['api.telegram.org/file/*' => Http::response($body, $status)]);
    }

    private function downloader(array $file): MediaDownloader
    {
        return new MediaDownloader(new DownloaderSpyBotAPI(['getFile' => $file]));
    }

    // -------------------------------------------------------------------------
    // getFile
    // -------------------------------------------------------------------------

    public function test_get_file_passes_file_id_to_api(): void
    {
        $api = new DownloaderSpyBotAPI(['getFile' => ['file_path' => 'x']]);

        (new MediaDownloader($api))->getFile('FID42');

        $this->assertSame('getFile', $api->calledMethod);
        $this->assertSame('FID42', $api->calledParams['file_id']);
    }

    // -------------------------------------------------------------------------
    // download
    // -------------------------------------------------------------------------

    public function test_download_returns_bytes(): void
    {
        $this->fakeDownload();

        $bytes = $this->downloader(['file_path' => 'documents/report.pdf', 'file_size' => 100])
            ->download('FID');

        $this->assertSame(self::BYTES, $bytes);
    }

    public function test_download_requests_the_telegram_file_url(): void
    {
        $this->fakeDownload();

        $this->downloader(['file_path' => 'photos/pic.jpg'])->download('FID');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/file/bot')
            && str_contains($request->url(), 'photos/pic.jpg'));
    }

    // -------------------------------------------------------------------------
    // save
    // -------------------------------------------------------------------------

    public function test_save_stores_file_on_disk(): void
    {
        $this->fakeDownload();
        Storage::fake('local');

        $path = $this->downloader(['file_path' => 'documents/report.pdf'])
            ->save('FID', 'local', 'inbox/report.pdf');

        $this->assertSame('inbox/report.pdf', $path);
        Storage::disk('local')->assertExists('inbox/report.pdf');
        $this->assertSame(self::BYTES, Storage::disk('local')->get('inbox/report.pdf'));
    }

    public function test_save_defaults_path_to_telegram_basename(): void
    {
        $this->fakeDownload();
        Storage::fake('local');

        $path = $this->downloader(['file_path' => 'documents/monthly.pdf'])
            ->save('FID', 'local');

        $this->assertSame('monthly.pdf', $path);
        Storage::disk('local')->assertExists('monthly.pdf');
    }

    // -------------------------------------------------------------------------
    // Guards
    // -------------------------------------------------------------------------

    public function test_throws_when_file_path_is_missing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/no file_path/');

        $this->downloader(['file_size' => 10])->download('FID');
    }

    public function test_throws_when_file_exceeds_size_limit(): void
    {
        config(['laragram.downloads.max_size' => 10]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/download limit/');

        $this->downloader(['file_path' => 'big.zip', 'file_size' => 1_000])->download('FID');
    }

    public function test_throws_when_downloaded_body_exceeds_limit(): void
    {
        // getFile reports no file_size, so the cap must be enforced post-fetch.
        config(['laragram.downloads.max_size' => 5]);
        $this->fakeDownload(str_repeat('x', 100));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/exceeding the .*download limit/');

        $this->downloader(['file_path' => 'big.bin'])->download('FID');
    }

    public function test_throws_on_unsafe_file_path(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Unsafe/');

        $this->downloader(['file_path' => '../../etc/passwd'])->download('FID');
    }

    public function test_throws_when_download_http_call_fails(): void
    {
        $this->fakeDownload('', 404);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Failed to download/');

        $this->downloader(['file_path' => 'gone.jpg'])->download('FID');
    }
}

/**
 * Hand-written BotAPI spy — avoids PHPUnit's deprecated addMethods() API.
 * Named distinctly to avoid clashing with other spies in this namespace.
 */
class DownloaderSpyBotAPI extends \Wekser\Laragram\BotAPI
{
    public string $calledMethod = '';
    public array  $calledParams = [];

    /** @param array<string, mixed> $responses  method → fake Telegram result */
    public function __construct(private readonly array $responses = [])
    {
        // Skip parent constructor (token validation is irrelevant in tests).
    }

    public function __call(string $method, array $arguments): mixed
    {
        $this->calledMethod = $method;
        $this->calledParams = $arguments[0] ?? [];

        return $this->responses[$method] ?? [];
    }
}
