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

namespace Wekser\Laragram\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Wekser\Laragram\Support\ReplyMarkup;

#[CoversClass(ReplyMarkup::class)]
class ReplyMarkupTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Button validity
    // -------------------------------------------------------------------------

    public function test_button_with_only_text_is_unusable(): void
    {
        $this->assertFalse(ReplyMarkup::isUsableInlineButton(['text' => 'Open']));
    }

    public function test_button_with_null_or_empty_url_is_unusable(): void
    {
        $this->assertFalse(ReplyMarkup::isUsableInlineButton(['text' => 'Open', 'url' => null]));
        $this->assertFalse(ReplyMarkup::isUsableInlineButton(['text' => 'Open', 'url' => '']));
        $this->assertFalse(ReplyMarkup::isUsableInlineButton(['text' => 'Open', 'url' => '   ']));
    }

    public function test_button_with_empty_callback_data_is_unusable(): void
    {
        $this->assertFalse(ReplyMarkup::isUsableInlineButton(['text' => 'Go', 'callback_data' => '']));
    }

    public function test_button_without_a_label_is_unusable(): void
    {
        $this->assertFalse(ReplyMarkup::isUsableInlineButton(['callback_data' => 'go']));
        $this->assertFalse(ReplyMarkup::isUsableInlineButton(['text' => '  ', 'callback_data' => 'go']));
    }

    public function test_nested_action_values_must_not_be_blank(): void
    {
        $this->assertFalse(ReplyMarkup::isUsableInlineButton(['text' => 'App', 'web_app' => ['url' => '']]));
        $this->assertFalse(ReplyMarkup::isUsableInlineButton(['text' => 'App', 'web_app' => []]));
        $this->assertFalse(ReplyMarkup::isUsableInlineButton(['text' => 'Copy', 'copy_text' => ['text' => '']]));
        $this->assertTrue(ReplyMarkup::isUsableInlineButton(['text' => 'App', 'web_app' => ['url' => 'https://a.dev']]));
    }

    public function test_pay_button_is_usable_only_when_switched_on(): void
    {
        $this->assertTrue(ReplyMarkup::isUsableInlineButton(['text' => 'Pay', 'pay' => true]));
        $this->assertFalse(ReplyMarkup::isUsableInlineButton(['text' => 'Pay', 'pay' => false]));
    }

    public function test_empty_switch_inline_query_stays_usable(): void
    {
        // An empty query inserts just the bot's username — a documented use.
        $this->assertTrue(ReplyMarkup::isUsableInlineButton([
            'text'                             => 'Search',
            'switch_inline_query_current_chat' => '',
        ]));
        $this->assertTrue(ReplyMarkup::isUsableInlineButton(['text' => 'Share', 'switch_inline_query' => '']));
    }

    public function test_callback_game_object_is_usable(): void
    {
        $this->assertTrue(ReplyMarkup::isUsableInlineButton(['text' => 'Play', 'callback_game' => (object) []]));
    }

    // -------------------------------------------------------------------------
    // assertUsableInlineButton
    // -------------------------------------------------------------------------

    public function test_assert_names_the_offending_label(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/"Open site".*carries no action/s');

        ReplyMarkup::assertUsableInlineButton(['text' => 'Open site', 'url' => '']);
    }

    public function test_assert_reports_a_missing_label_separately(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/non-empty text label/');

        ReplyMarkup::assertUsableInlineButton(['callback_data' => 'go']);
    }

    public function test_assert_passes_a_valid_button(): void
    {
        ReplyMarkup::assertUsableInlineButton(['text' => 'Go', 'callback_data' => 'go']);

        $this->expectNotToPerformAssertions();
    }

    // -------------------------------------------------------------------------
    // sanitize
    // -------------------------------------------------------------------------

    public function test_sanitize_drops_the_broken_button_and_keeps_the_rest(): void
    {
        $dropped = [];

        $markup = ReplyMarkup::sanitize([
            'inline_keyboard' => [
                [
                    ['text' => 'Open', 'url' => null],
                    ['text' => 'Details', 'callback_data' => 'details'],
                ],
            ],
        ], $dropped);

        $this->assertSame([
            'inline_keyboard' => [
                [['text' => 'Details', 'callback_data' => 'details']],
            ],
        ], $markup);
        $this->assertSame(['"Open"'], $dropped);
    }

    public function test_sanitize_removes_a_row_left_empty(): void
    {
        $dropped = [];

        $markup = ReplyMarkup::sanitize([
            'inline_keyboard' => [
                [['text' => 'Broken', 'url' => '']],
                [['text' => 'Fine', 'callback_data' => 'ok']],
            ],
        ], $dropped);

        $this->assertSame([
            'inline_keyboard' => [
                [['text' => 'Fine', 'callback_data' => 'ok']],
            ],
        ], $markup);
        $this->assertCount(1, $dropped);
    }

    public function test_sanitize_leaves_a_valid_keyboard_untouched(): void
    {
        $original = [
            'inline_keyboard' => [
                [['text' => 'A', 'callback_data' => 'a'], ['text' => 'B', 'url' => 'https://a.dev']],
                [['text' => 'C', 'callback_data' => 'c']],
            ],
        ];

        $dropped = [];

        $this->assertSame($original, ReplyMarkup::sanitize($original, $dropped));
        $this->assertSame([], $dropped);
    }

    public function test_sanitize_ignores_reply_keyboards_and_force_reply(): void
    {
        $dropped = [];

        // A reply-keyboard button carrying only text is perfectly valid.
        $reply = ['keyboard' => [[['text' => 'Share']]], 'resize_keyboard' => true];
        $this->assertSame($reply, ReplyMarkup::sanitize($reply, $dropped));

        $force = ['force_reply' => true];
        $this->assertSame($force, ReplyMarkup::sanitize($force, $dropped));

        $this->assertSame([], $dropped);
    }

    public function test_sanitize_may_empty_the_keyboard_entirely(): void
    {
        $dropped = [];

        $markup = ReplyMarkup::sanitize([
            'inline_keyboard' => [[['text' => 'Broken']]],
        ], $dropped);

        $this->assertSame(['inline_keyboard' => []], $markup);
        $this->assertSame(['"Broken"'], $dropped);
    }

    public function test_describe_falls_back_to_the_payload_when_there_is_no_label(): void
    {
        $this->assertSame('{"url":"https://a.dev"}', ReplyMarkup::describe(['url' => 'https://a.dev']));
    }
}
