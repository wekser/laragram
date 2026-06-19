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

namespace Wekser\Laragram\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use Wekser\Laragram\Enums\ButtonStyle;
use Wekser\Laragram\Telegram\Keyboards\InlineKeyboard;
use Wekser\Laragram\Tests\TestCase;
use Wekser\Laragram\View\ComponentContext;
use Wekser\Laragram\View\InlineKeyboardState;

#[CoversClass(InlineKeyboard::class)]
#[CoversClass(InlineKeyboardState::class)]
#[CoversClass(ButtonStyle::class)]
class InlineKeyboardTest extends TestCase
{
    protected function tearDown(): void
    {
        ComponentContext::reset();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Fluent builder — full InlineKeyboardButton API coverage
    // -------------------------------------------------------------------------

    public function test_builder_covers_all_button_types_in_one_row(): void
    {
        $row = InlineKeyboard::make()
            ->button('cb', 'data')
            ->href('url', 'https://example.com')
            ->webApp('app', 'https://example.com/app')
            ->switchInline('here', 'q1')
            ->switchInlineChosen('any', 'q2')
            ->switchInlineChosenChat('pick', 'q3', allowUserChats: true, allowChannelChats: true)
            ->copyText('copy', 'COUPON10')
            ->loginUrl('login', 'https://example.com/auth', forwardText: 'Sign in', writeAccess: true)
            ->pay('pay')
            ->callbackGame('play')
            ->toArray()['inline_keyboard'][0];

        $this->assertSame(['text' => 'cb', 'callback_data' => 'data'], $row[0]);
        $this->assertSame(['text' => 'url', 'url' => 'https://example.com'], $row[1]);
        $this->assertSame(['text' => 'app', 'web_app' => ['url' => 'https://example.com/app']], $row[2]);
        $this->assertSame(['text' => 'here', 'switch_inline_query_current_chat' => 'q1'], $row[3]);
        $this->assertSame(['text' => 'any', 'switch_inline_query' => 'q2'], $row[4]);
        $this->assertSame([
            'text' => 'pick',
            'switch_inline_query_chosen_chat' => [
                'query'               => 'q3',
                'allow_user_chats'    => true,
                'allow_channel_chats' => true,
            ],
        ], $row[5]);
        $this->assertSame(['text' => 'copy', 'copy_text' => ['text' => 'COUPON10']], $row[6]);
        $this->assertSame([
            'text'      => 'login',
            'login_url' => [
                'url'                  => 'https://example.com/auth',
                'forward_text'         => 'Sign in',
                'request_write_access' => true,
            ],
        ], $row[7]);
        $this->assertSame(['text' => 'pay', 'pay' => true], $row[8]);

        $this->assertSame('play', $row[9]['text']);
        $this->assertEquals((object) [], $row[9]['callback_game']);
    }

    public function test_callback_game_encodes_as_empty_json_object(): void
    {
        $markup = InlineKeyboard::make()->callbackGame('Play')->toArray();

        $this->assertStringContainsString('"callback_game":{}', json_encode($markup));
    }

    // -------------------------------------------------------------------------
    // View helpers — parity through ComponentContext / InlineKeyboardState
    // -------------------------------------------------------------------------

    public function test_view_helpers_cover_full_button_api(): void
    {
        $state = new InlineKeyboardState();
        ComponentContext::push($state);

        button('cb', 'data');
        href('url', 'https://example.com');
        web_app('app', 'https://example.com/app');
        switch_inline('here', 'q1');
        switch_inline_chosen('any', 'q2');
        switch_inline_chosen_chat('pick', 'q3', allowGroupChats: true);
        copy_text('copy', 'COUPON10');
        login_url('login', 'https://example.com/auth');
        pay('pay');
        callback_game('play');

        ComponentContext::pop();

        $row = $state->toArray()['inline_keyboard'][0];

        $this->assertCount(10, $row);
        $this->assertSame(['text' => 'cb', 'callback_data' => 'data'], $row[0]);
        $this->assertSame('q1', $row[3]['switch_inline_query_current_chat']);
        $this->assertSame('q2', $row[4]['switch_inline_query']);
        $this->assertSame(['query' => 'q3', 'allow_group_chats' => true], $row[5]['switch_inline_query_chosen_chat']);
        $this->assertSame(['text' => 'COUPON10'], $row[6]['copy_text']);
        $this->assertSame(['url' => 'https://example.com/auth'], $row[7]['login_url']);
        $this->assertTrue($row[8]['pay']);
        $this->assertEquals((object) [], $row[9]['callback_game']);
    }

    public function test_view_helpers_are_noop_without_active_inline_context(): void
    {
        // No state pushed — global helpers must silently do nothing, not error.
        button('cb', 'data');
        pay('pay');
        callback_game('play');

        $this->assertNull(ComponentContext::current());
    }

    // -------------------------------------------------------------------------
    // Optional style / icon attributes (Bot API 9.4)
    // -------------------------------------------------------------------------

    public function test_builder_style_and_icon_attributes_merge_into_button(): void
    {
        $row = InlineKeyboard::make()
            ->button('Confirm', 'ok', ButtonStyle::Success, '5368324170671202286')
            ->href('Cancel', 'https://example.com', style: 'danger')
            ->toArray()['inline_keyboard'][0];

        $this->assertSame([
            'text'                 => 'Confirm',
            'callback_data'        => 'ok',
            'style'                => 'success',
            'icon_custom_emoji_id' => '5368324170671202286',
        ], $row[0]);

        $this->assertSame('danger', $row[1]['style']);
    }

    public function test_builder_style_accepts_string_and_enum_equivalently(): void
    {
        $fromString = InlineKeyboard::make()->button('a', 'b', style: 'primary')->toArray();
        $fromEnum   = InlineKeyboard::make()->button('a', 'b', style: ButtonStyle::Primary)->toArray();

        $this->assertSame($fromString, $fromEnum);
    }

    public function test_builder_omits_style_and_icon_keys_when_not_set(): void
    {
        $button = InlineKeyboard::make()->button('a', 'b')->toArray()['inline_keyboard'][0][0];

        $this->assertArrayNotHasKey('style', $button);
        $this->assertArrayNotHasKey('icon_custom_emoji_id', $button);
    }

    public function test_builder_rejects_invalid_style(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        InlineKeyboard::make()->button('a', 'b', style: 'purple');
    }

    public function test_view_helper_style_and_icon_attributes_merge_into_button(): void
    {
        $state = new InlineKeyboardState();
        ComponentContext::push($state);

        button('Confirm', 'ok', style: 'success', icon: '123');

        ComponentContext::pop();

        $button = $state->toArray()['inline_keyboard'][0][0];

        $this->assertSame('success', $button['style']);
        $this->assertSame('123', $button['icon_custom_emoji_id']);
    }
}
