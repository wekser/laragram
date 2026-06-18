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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wekser\Laragram\Services\MediaUploader;

#[CoversClass(MediaUploader::class)]
class MediaUploaderTest extends TestCase
{
    private const CHAT_ID = 42;

    /** Absolute path to a real temp file created for each test. */
    private string $tempFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempFile = tempnam(sys_get_temp_dir(), 'laragram_upload_test_');
        file_put_contents($this->tempFile, 'fake binary content');
    }

    protected function tearDown(): void
    {
        if (is_file($this->tempFile)) {
            unlink($this->tempFile);
        }

        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeUploader(FakeBotAPI $api): MediaUploader
    {
        return new MediaUploader($api);
    }

    // -------------------------------------------------------------------------
    // TYPE_MAP — every supported type calls the right API method
    // -------------------------------------------------------------------------

    public static function supportedTypeProvider(): array
    {
        return [
            'photo'      => ['photo',      'sendPhoto',     'photo',      self::photoResponse()],
            'document'   => ['document',   'sendDocument',  'document',   self::mediaResponse('document')],
            'audio'      => ['audio',      'sendAudio',     'audio',      self::mediaResponse('audio')],
            'video'      => ['video',      'sendVideo',     'video',      self::mediaResponse('video')],
            'voice'      => ['voice',      'sendVoice',     'voice',      self::mediaResponse('voice')],
            'animation'  => ['animation',  'sendAnimation', 'animation',  self::mediaResponse('animation')],
            'video_note' => ['video_note', 'sendVideoNote', 'video_note', self::mediaResponse('video_note')],
            'sticker'    => ['sticker',    'sendSticker',   'sticker',    self::mediaResponse('sticker')],
        ];
    }

    #[DataProvider('supportedTypeProvider')]
    public function test_upload_calls_correct_api_method_for_each_type(
        string $type,
        string $expectedMethod,
        string $expectedField,
        array  $apiResponse,
    ): void {
        $api = new FakeBotAPI([$expectedMethod => $apiResponse]);

        $fileId = $this->makeUploader($api)->upload($type, $this->tempFile, self::CHAT_ID);

        $this->assertSame($expectedMethod, $api->calledMethod, "Expected API method '{$expectedMethod}'");
        $this->assertSame('file_abc123', $fileId);
    }

    // -------------------------------------------------------------------------
    // Source resolution
    // -------------------------------------------------------------------------

    public function test_local_file_is_passed_as_curl_file_object(): void
    {
        $api = new FakeBotAPI(['sendDocument' => self::mediaResponse('document')]);

        $this->makeUploader($api)->upload('document', $this->tempFile, self::CHAT_ID);

        $this->assertArrayHasKey('document', $api->calledParams);
        $this->assertInstanceOf(\CURLFile::class, $api->calledParams['document']);
    }

    public function test_url_is_passed_as_plain_string_not_curl_file(): void
    {
        $url = 'https://example.com/photo.jpg';
        $api = new FakeBotAPI(['sendPhoto' => self::photoResponse()]);

        $this->makeUploader($api)->upload('photo', $url, self::CHAT_ID);

        $this->assertSame($url, $api->calledParams['photo']);
    }

    public function test_curl_file_has_correct_path(): void
    {
        $api = new FakeBotAPI(['sendPhoto' => self::photoResponse()]);

        $this->makeUploader($api)->upload('photo', $this->tempFile, self::CHAT_ID);

        /** @var \CURLFile $curlFile */
        $curlFile = $api->calledParams['photo'];

        $this->assertInstanceOf(\CURLFile::class, $curlFile);
        $this->assertSame($this->tempFile, $curlFile->getFilename());
    }

    public function test_chat_id_is_included_in_api_params(): void
    {
        $chatId = 99999;
        $api    = new FakeBotAPI(['sendPhoto' => self::photoResponse()]);

        $this->makeUploader($api)->upload('photo', $this->tempFile, $chatId);

        $this->assertSame($chatId, $api->calledParams['chat_id']);
    }

    // -------------------------------------------------------------------------
    // photo file_id extraction (array of PhotoSize objects)
    // -------------------------------------------------------------------------

    public function test_photo_extracts_file_id_from_last_photo_size(): void
    {
        $api = new FakeBotAPI(['sendPhoto' => [
            'photo' => [
                ['file_id' => 'small_id',  'width' => 90,  'height' => 90],
                ['file_id' => 'medium_id', 'width' => 320, 'height' => 320],
                ['file_id' => 'large_id',  'width' => 800, 'height' => 600],
            ],
        ]]);

        $fileId = $this->makeUploader($api)->upload('photo', $this->tempFile, self::CHAT_ID);

        $this->assertSame('large_id', $fileId);
    }

    public function test_photo_works_with_single_size_array(): void
    {
        $api = new FakeBotAPI(['sendPhoto' => [
            'photo' => [
                ['file_id' => 'only_id', 'width' => 90, 'height' => 90],
            ],
        ]]);

        $this->assertSame(
            'only_id',
            $this->makeUploader($api)->upload('photo', $this->tempFile, self::CHAT_ID)
        );
    }

    // -------------------------------------------------------------------------
    // Return value
    // -------------------------------------------------------------------------

    public function test_upload_returns_string_file_id(): void
    {
        $api    = new FakeBotAPI(['sendDocument' => self::mediaResponse('document')]);
        $result = $this->makeUploader($api)->upload('document', $this->tempFile, self::CHAT_ID);

        $this->assertIsString($result);
        $this->assertSame('file_abc123', $result);
    }

    // -------------------------------------------------------------------------
    // Error cases — invalid type
    // -------------------------------------------------------------------------

    public function test_throws_on_unsupported_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/Unsupported media type 'gif'/");

        $this->makeUploader(new FakeBotAPI())->upload('gif', $this->tempFile, self::CHAT_ID);
    }

    public function test_exception_message_lists_all_supported_types(): void
    {
        try {
            $this->makeUploader(new FakeBotAPI())->upload('unknown', $this->tempFile, self::CHAT_ID);
            $this->fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            foreach (['photo', 'document', 'audio', 'video', 'voice', 'animation', 'video_note', 'sticker'] as $type) {
                $this->assertStringContainsString($type, $e->getMessage());
            }
        }
    }

    public function test_api_is_not_called_when_type_is_invalid(): void
    {
        $api = new FakeBotAPI();

        try {
            $this->makeUploader($api)->upload('gif', $this->tempFile, self::CHAT_ID);
        } catch (\InvalidArgumentException) {}

        $this->assertTrue($api->wasNeverCalled());
    }

    // -------------------------------------------------------------------------
    // Error cases — invalid source
    // -------------------------------------------------------------------------

    public function test_throws_when_source_is_nonexistent_path_and_not_url(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/local file path or a public URL/');

        $this->makeUploader(new FakeBotAPI())
            ->upload('photo', '/nonexistent/file.jpg', self::CHAT_ID);
    }

    public function test_throws_when_source_is_arbitrary_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->makeUploader(new FakeBotAPI())
            ->upload('photo', 'just_some_string', self::CHAT_ID);
    }

    public function test_api_is_not_called_when_source_is_invalid(): void
    {
        $api = new FakeBotAPI();

        try {
            $this->makeUploader($api)->upload('photo', '/nonexistent/path.jpg', self::CHAT_ID);
        } catch (\InvalidArgumentException) {}

        $this->assertTrue($api->wasNeverCalled());
    }

    // -------------------------------------------------------------------------
    // Error cases — API response missing file_id
    // -------------------------------------------------------------------------

    public function test_throws_when_response_has_no_file_id(): void
    {
        $api = new FakeBotAPI(['sendDocument' => ['document' => ['file_size' => 1234]]]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches("/Could not extract file_id.*'document'/");

        $this->makeUploader($api)->upload('document', $this->tempFile, self::CHAT_ID);
    }

    public function test_throws_when_photo_response_array_is_empty(): void
    {
        $api = new FakeBotAPI(['sendPhoto' => ['photo' => []]]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches("/Could not extract file_id.*'photo'/");

        $this->makeUploader($api)->upload('photo', $this->tempFile, self::CHAT_ID);
    }

    public function test_throws_when_response_field_is_missing_entirely(): void
    {
        $api = new FakeBotAPI(['sendVideo' => ['message_id' => 1]]);

        $this->expectException(\RuntimeException::class);

        $this->makeUploader($api)->upload('video', $this->tempFile, self::CHAT_ID);
    }

    // -------------------------------------------------------------------------
    // Fixture builders
    // -------------------------------------------------------------------------

    private static function photoResponse(): array
    {
        return [
            'photo' => [
                ['file_id' => 'small_thumb', 'width' => 90,  'height' => 90],
                ['file_id' => 'file_abc123', 'width' => 800, 'height' => 600],
            ],
        ];
    }

    private static function mediaResponse(string $field): array
    {
        return [
            $field => ['file_id' => 'file_abc123', 'file_size' => 2048],
        ];
    }
}

/**
 * Hand-written BotAPI spy — avoids PHPUnit's deprecated addMethods() API.
 *
 * Intercepts every magic method call via __call, records the last invocation,
 * and returns the pre-configured response for that method.
 */
class FakeBotAPI extends \Wekser\Laragram\BotAPI
{
    public string $calledMethod = '';
    public array  $calledParams = [];
    private int   $callCount    = 0;

    /** @param array<string, array> $responses  method → fake Telegram response */
    public function __construct(private readonly array $responses = [])
    {
        // Skip parent constructor (token validation is irrelevant in tests).
    }

    public function __call(string $method, array $arguments): mixed
    {
        $this->calledMethod = $method;
        $this->calledParams = $arguments[0] ?? [];
        $this->callCount++;

        return $this->responses[$method] ?? [];
    }

    public function wasCalledOnce(): bool  { return $this->callCount === 1; }
    public function wasNeverCalled(): bool { return $this->callCount === 0; }
}
