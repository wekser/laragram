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

namespace Wekser\Laragram\Tests\Unit\Telegram\Inline;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Wekser\Laragram\Telegram\Inline\InlineResults;

#[CoversClass(InlineResults::class)]
class InlineResultsTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Result types
    // -------------------------------------------------------------------------

    public function test_article_builds_input_message_content(): void
    {
        $result = InlineResults::make()
            ->article('1', 'Say hello', 'Hello there!', description: 'a greeting')
            ->toArray()['results'][0];

        $this->assertSame('article', $result['type']);
        $this->assertSame('1', $result['id']);
        $this->assertSame('Say hello', $result['title']);
        $this->assertSame('Hello there!', $result['input_message_content']['message_text']);
        $this->assertSame('HTML', $result['input_message_content']['parse_mode']);
        $this->assertSame('a greeting', $result['description']);
    }

    public function test_article_reply_markup_is_passed_through(): void
    {
        $markup = ['inline_keyboard' => [[['text' => 'Go', 'callback_data' => 'go']]]];

        $result = InlineResults::make()
            ->article('1', 'T', 'body', replyMarkup: $markup)
            ->toArray()['results'][0];

        $this->assertSame($markup, $result['reply_markup']);
    }

    public function test_photo_defaults_thumbnail_to_photo_url(): void
    {
        $result = InlineResults::make()
            ->photo('1', 'https://ex.com/p.jpg')
            ->toArray()['results'][0];

        $this->assertSame('photo', $result['type']);
        $this->assertSame('https://ex.com/p.jpg', $result['photo_url']);
        $this->assertSame('https://ex.com/p.jpg', $result['thumbnail_url']);
    }

    public function test_photo_parse_mode_only_present_with_caption(): void
    {
        $withCaption = InlineResults::make()
            ->photo('1', 'https://ex.com/p.jpg', caption: 'nice')
            ->toArray()['results'][0];

        $this->assertSame('nice', $withCaption['caption']);
        $this->assertSame('HTML', $withCaption['parse_mode']);

        $noCaption = InlineResults::make()
            ->photo('2', 'https://ex.com/p.jpg')
            ->toArray()['results'][0];

        $this->assertArrayNotHasKey('parse_mode', $noCaption);
        $this->assertArrayNotHasKey('caption', $noCaption);
    }

    public function test_cached_photo_uses_file_id(): void
    {
        $result = InlineResults::make()
            ->cachedPhoto('1', 'FILEID123')
            ->toArray()['results'][0];

        $this->assertSame('photo', $result['type']);
        $this->assertSame('FILEID123', $result['photo_file_id']);
        $this->assertArrayNotHasKey('photo_url', $result);
    }

    public function test_sticker_uses_file_id_and_has_no_parse_mode(): void
    {
        $result = InlineResults::make()
            ->sticker('1', 'STICKER_FILE_ID')
            ->toArray()['results'][0];

        $this->assertSame('sticker', $result['type']);
        $this->assertSame('STICKER_FILE_ID', $result['sticker_file_id']);
        $this->assertArrayNotHasKey('parse_mode', $result);
    }

    public function test_raw_appends_arbitrary_result(): void
    {
        $raw = ['type' => 'voice', 'id' => '9', 'voice_url' => 'https://ex.com/v.ogg', 'title' => 'Clip'];

        $result = InlineResults::make()->raw($raw)->toArray()['results'][0];

        $this->assertSame($raw, $result);
    }

    public function test_results_are_collected_in_order(): void
    {
        $results = InlineResults::make()
            ->article('a', 'A', 'one')
            ->photo('b', 'https://ex.com/p.jpg')
            ->sticker('c', 'FID')
            ->toArray()['results'];

        $this->assertSame(['a', 'b', 'c'], array_column($results, 'id'));
    }

    // -------------------------------------------------------------------------
    // Answer-level options
    // -------------------------------------------------------------------------

    public function test_options_map_to_api_names(): void
    {
        $params = InlineResults::make()
            ->article('1', 'T', 'b')
            ->cache(300)
            ->personal()
            ->nextOffset('20')
            ->button('More in chat', startParameter: 'go')
            ->toArray();

        $this->assertSame(300, $params['cache_time']);
        $this->assertTrue($params['is_personal']);
        $this->assertSame('20', $params['next_offset']);
        $this->assertSame(['text' => 'More in chat', 'start_parameter' => 'go'], $params['button']);
    }

    public function test_is_personal_omitted_when_false(): void
    {
        $params = InlineResults::make()->article('1', 'T', 'b')->toArray();

        $this->assertArrayNotHasKey('is_personal', $params);
        $this->assertArrayNotHasKey('cache_time', $params);
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    public function test_duplicate_ids_throw(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/ids must be unique/');

        InlineResults::make()
            ->article('dup', 'A', 'one')
            ->article('dup', 'B', 'two')
            ->toArray();
    }

    public function test_empty_id_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/non-empty type and id/');

        InlineResults::make()->article('', 'A', 'one');
    }

    public function test_raw_without_type_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        InlineResults::make()->raw(['id' => '1']);
    }

    public function test_more_than_fifty_results_throws(): void
    {
        $builder = InlineResults::make();

        for ($i = 0; $i < 50; $i++) {
            $builder->article((string) $i, 'T', 'b');
        }

        $this->expectException(\OverflowException::class);

        $builder->article('overflow', 'T', 'b');
    }

    public function test_count_reports_number_of_results(): void
    {
        $builder = InlineResults::make()->article('1', 'T', 'b')->photo('2', 'https://ex.com/p.jpg');

        $this->assertSame(2, $builder->count());
    }
}
