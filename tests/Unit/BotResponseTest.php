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
use Wekser\Laragram\BotResponse;
use Wekser\Laragram\Exceptions\NotExistsViewException;
use Wekser\Laragram\Exceptions\ViewInvalidException;
use Wekser\Laragram\Tests\TestCase;
use Wekser\Laragram\View\ComponentContext;

#[CoversClass(BotResponse::class)]
class BotResponseTest extends TestCase
{
    protected function tearDown(): void
    {
        ComponentContext::reset();
        BotResponse::flushTemplateCache();

        parent::tearDown();
    }

    private function make(): BotResponse
    {
        // BotAuth::user() is stubbed to null in TestCase::setUp()
        return new BotResponse(config('laragram.paths.views'));
    }

    // -------------------------------------------------------------------------
    // text() — structure
    // -------------------------------------------------------------------------

    public function test_text_sets_send_message_method(): void
    {
        $this->assertSame('sendMessage', $this->make()->text('Hello')->contents['method']);
    }

    public function test_text_marks_content_as_escaped(): void
    {
        $this->assertTrue($this->make()->text('Hello')->contents['_escaped']);
    }

    public function test_text_defaults_to_html_parse_mode(): void
    {
        $this->assertSame('HTML', $this->make()->text('Hello')->contents['parse_mode']);
    }

    public function test_text_default_escapes_html_special_chars(): void
    {
        // HTML is the default parse mode: text() treats its whole argument as raw
        // user data and escapes it with htmlspecialchars().
        $this->assertSame(
            '&lt;b&gt;Bold&lt;/b&gt;',
            $this->make()->text('<b>Bold</b>')->contents['text'],
        );
    }

    public function test_text_sets_requested_parse_mode(): void
    {
        $this->assertSame('HTML', $this->make()->text('Hello', 'HTML')->contents['parse_mode']);
    }

    public function test_text_returns_chainable_response_instance(): void
    {
        // Content-entry methods return a FRESH BotResponse (clone-on-entry) so
        // several can be collected into an array for a multi-message reply.
        // The returned instance is still fully chainable.
        $response = $this->make();
        $result   = $response->text('Hello');

        $this->assertInstanceOf(BotResponse::class, $result);
        $this->assertNotSame($response, $result);
        $this->assertSame('sendMessage', $result->contents['method']);
        $this->assertSame('next', $result->redirect('next')->station);
    }

    public function test_redirect_before_content_is_preserved(): void
    {
        // redirect() set BEFORE the content method must survive the clone-on-entry
        // (the natural ordering redirect('home')->text('hi')).
        $result = $this->make()->redirect('home')->text('Welcome back');

        $this->assertSame('home', $result->station);
        $this->assertSame('sendMessage', $result->contents['method']);
    }

    public function test_pending_redirect_does_not_leak_to_a_later_response(): void
    {
        // A redirect() consumed by one response must not bleed into the next
        // response built from the same (shared singleton) instance.
        $source = $this->make();

        $first  = $source->redirect('home')->text('first');
        $second = $source->text('second');

        $this->assertSame('home', $first->station);
        $this->assertNull($second->station);
    }

    // -------------------------------------------------------------------------
    // MarkdownV2 escaping
    // -------------------------------------------------------------------------

    public function test_markdownv2_escapes_asterisk(): void
    {
        $this->assertSame('Hello \*World\*', $this->make()->text('Hello *World*', 'MarkdownV2')->contents['text']);
    }

    public function test_markdownv2_escapes_underscore(): void
    {
        $this->assertSame('\_italic\_', $this->make()->text('_italic_', 'MarkdownV2')->contents['text']);
    }

    public function test_markdownv2_escapes_square_brackets(): void
    {
        $this->assertSame('\[link\]', $this->make()->text('[link]', 'MarkdownV2')->contents['text']);
    }

    public function test_markdownv2_escapes_dot_and_exclamation(): void
    {
        $this->assertSame('End\. Really\!', $this->make()->text('End. Really!', 'MarkdownV2')->contents['text']);
    }

    public function test_markdownv2_escapes_all_reserved_characters(): void
    {
        $reserved  = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
        $input     = implode('', $reserved);
        $text      = $this->make()->text($input, 'MarkdownV2')->contents['text'];

        foreach ($reserved as $char) {
            $this->assertStringContainsString('\\' . $char, $text, "Expected '{$char}' to be escaped");
        }
    }

    public function test_markdownv2_escapes_backslash_before_other_chars_to_avoid_double_escaping(): void
    {
        $this->assertSame('path\\\\value', $this->make()->text('path\\value', 'MarkdownV2')->contents['text']);
    }

    // -------------------------------------------------------------------------
    // Legacy Markdown escaping (opt-in via explicit 'Markdown')
    // -------------------------------------------------------------------------

    public function test_markdown_escapes_only_legacy_reserved_characters(): void
    {
        // Legacy Markdown escapes _ * [ ] ( ) ` — but NOT . ! , - which would
        // otherwise break MarkdownV2. text() escapes the whole argument because
        // it is treated as raw user data.
        $this->assertSame('\*bold\*', $this->make()->text('*bold*', 'Markdown')->contents['text']);
    }

    public function test_markdown_does_not_escape_prose_punctuation(): void
    {
        $this->assertSame('End. Really!', $this->make()->text('End. Really!', 'Markdown')->contents['text']);
    }

    // -------------------------------------------------------------------------
    // HTML escaping
    // -------------------------------------------------------------------------

    public function test_html_escapes_angle_brackets(): void
    {
        $this->assertSame(
            '&lt;b&gt;Bold&lt;/b&gt;',
            $this->make()->text('<b>Bold</b>', 'HTML')->contents['text'],
        );
    }

    public function test_html_escapes_ampersand(): void
    {
        $this->assertSame('A &amp; B', $this->make()->text('A & B', 'HTML')->contents['text']);
    }

    public function test_html_escapes_quotes(): void
    {
        $this->assertSame(
            '&quot;hello&quot;',
            $this->make()->text('"hello"', 'HTML')->contents['text'],
        );
    }

    // -------------------------------------------------------------------------
    // text() — format validation
    // -------------------------------------------------------------------------

    public function test_text_throws_on_invalid_format(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/parse_mode/i');

        $this->make()->text('Hello', 'XML');
    }

    public function test_text_accepts_html_format(): void
    {
        $response = $this->make()->text('Hello', 'HTML');

        $this->assertSame('HTML', $response->contents['parse_mode']);
    }

    public function test_text_accepts_null_format(): void
    {
        $response = $this->make()->text('Hello *world*', null);

        $this->assertNull($response->contents['parse_mode']);
        $this->assertSame('Hello *world*', $response->contents['text']);
    }

    // -------------------------------------------------------------------------
    // redirect()
    // -------------------------------------------------------------------------

    public function test_redirect_sets_station(): void
    {
        $this->assertSame('next_station', $this->make()->redirect('next_station')->station);
    }

    public function test_redirect_returns_self_for_chaining(): void
    {
        $response = $this->make();

        $this->assertSame($response, $response->redirect('station'));
    }

    // -------------------------------------------------------------------------
    // noPreview()
    // -------------------------------------------------------------------------

    public function test_no_preview_disables_link_preview_on_text(): void
    {
        $response = $this->make()->text('See https://example.com')->noPreview();

        $this->assertSame('sendMessage', $response->contents['method']);
        $this->assertSame(['is_disabled' => true], $response->contents['link_preview_options']);
    }

    public function test_no_preview_disables_link_preview_on_edit(): void
    {
        $response = $this->make()->edit('See https://example.com')->noPreview();

        $this->assertSame('editMessageText', $response->contents['method']);
        $this->assertSame(['is_disabled' => true], $response->contents['link_preview_options']);
    }

    public function test_no_preview_returns_self_for_chaining(): void
    {
        $response = $this->make()->text('Hello');

        $this->assertSame($response, $response->noPreview());
    }

    public function test_no_preview_throws_when_called_before_a_content_method(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/must be called after/i');

        $this->make()->noPreview();
    }

    public function test_no_preview_throws_on_a_method_without_link_previews(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/sendPhoto/');

        $this->make()->photo('file_id_1')->noPreview();
    }

    // -------------------------------------------------------------------------
    // Chaining
    // -------------------------------------------------------------------------

    public function test_text_and_redirect_are_chainable(): void
    {
        $response = $this->make()->text('Hello')->redirect('next');

        $this->assertSame('sendMessage', $response->contents['method']);
        $this->assertSame('next', $response->station);
    }

    // -------------------------------------------------------------------------
    // view() — path traversal protection
    // -------------------------------------------------------------------------

    public function test_view_rejects_dotdot_in_view_name(): void
    {
        $this->expectException(NotExistsViewException::class);

        $this->make()->view('../../etc/passwd');
    }

    public function test_view_rejects_forward_slash_in_view_name(): void
    {
        $this->expectException(NotExistsViewException::class);

        $this->make()->view('subdir/evil');
    }

    public function test_view_rejects_backslash_in_view_name(): void
    {
        $this->expectException(NotExistsViewException::class);

        $this->make()->view('evil\\path');
    }

    public function test_view_accepts_dot_notation_as_namespace_separator(): void
    {
        // 'subdir.nonexistent' → resources/laragram/subdir/nonexistent/
        // Directory doesn't exist — NotExistsViewException, NOT a traversal error.
        $this->expectException(NotExistsViewException::class);

        try {
            $this->make()->view('subdir.nonexistent');
        } catch (NotExistsViewException $e) {
            $this->assertStringNotContainsString('..', $e->getMessage());
            throw $e;
        }
    }

    public function test_view_throws_when_directory_does_not_exist(): void
    {
        $this->expectException(NotExistsViewException::class);

        $this->make()->view('nonexistent_view');
    }

    // -------------------------------------------------------------------------
    // view() — component directory: text only
    // -------------------------------------------------------------------------

    public function test_component_view_text_sets_send_message(): void
    {
        $response = $this->make()->view('text_view', ['name' => 'World']);

        $this->assertSame('sendMessage', $response->contents['method']);
    }

    public function test_component_view_text_contains_text_field(): void
    {
        $response = $this->make()->view('text_view', ['name' => 'Alice']);

        $this->assertArrayHasKey('text', $response->contents);
    }

    public function test_component_view_template_interpolates_variable(): void
    {
        $response = $this->make()->view('text_view', ['name' => 'Alice'], null);

        $this->assertStringContainsString('Alice', $response->contents['text']);
    }

    public function test_component_view_text_sets_parse_mode(): void
    {
        $response = $this->make()->view('text_view', ['name' => 'Alice']);

        $this->assertSame('HTML', $response->contents['parse_mode']);
    }

    public function test_component_view_preserves_static_markup_in_template(): void
    {
        // Static HTML markup the view author wrote must survive — only {{ }} values
        // are escaped. The formatting_view fixture contains literal <b>Laragram</b>.
        $response = $this->make()->view('formatting_view', ['name' => 'Alice']);

        $this->assertStringContainsString('<b>Laragram</b>', $response->contents['text']);
        $this->assertStringContainsString('Alice', $response->contents['text']);
    }

    public function test_component_view_escapes_interpolated_values(): void
    {
        // A dynamic value containing HTML characters is escaped so it cannot
        // break the author's formatting or inject markup.
        $response = $this->make()->view('formatting_view', ['name' => '<b>evil</b>']);

        $this->assertStringContainsString('&lt;b&gt;evil&lt;/b&gt;', $response->contents['text']);
        // The author's own static <b>Laragram</b> stays intact and unescaped.
        $this->assertStringContainsString('<b>Laragram</b>', $response->contents['text']);
    }

    public function test_component_view_raw_interpolation_is_not_escaped(): void
    {
        // {!! !!} emits trusted content (e.g. a translation string with <b> tags)
        // verbatim, while {{ }} on the same view still escapes user data.
        $response = $this->make()->view('raw_view', [
            'body' => 'Thank you for using <b>Laragram</b>!',
            'name' => '<b>evil</b>',
        ]);

        $this->assertStringContainsString('<b>Laragram</b>', $response->contents['text']);
        $this->assertStringContainsString('&lt;b&gt;evil&lt;/b&gt;', $response->contents['text']);
    }

    public function test_component_view_text_omits_parse_mode_when_format_is_null(): void
    {
        $response = $this->make()->view('text_view', ['name' => 'Alice'], null);

        $this->assertArrayNotHasKey('parse_mode', $response->contents);
    }

    // -------------------------------------------------------------------------
    // view() — component directory: photo + caption
    // -------------------------------------------------------------------------

    public function test_component_view_photo_sets_send_photo(): void
    {
        $response = $this->make()->view('photo_view', ['photo_id' => 'abc123']);

        $this->assertSame('sendPhoto', $response->contents['method']);
    }

    public function test_component_view_photo_sets_photo_field(): void
    {
        $response = $this->make()->view('photo_view', ['photo_id' => 'abc123']);

        $this->assertSame('abc123', $response->contents['photo']);
    }

    public function test_component_view_photo_uses_text_as_caption(): void
    {
        $response = $this->make()->view('photo_view', ['photo_id' => 'abc123']);

        $this->assertArrayHasKey('caption', $response->contents);
        $this->assertArrayNotHasKey('text', $response->contents);
    }

    // -------------------------------------------------------------------------
    // view() — component directory: inline keyboard
    // -------------------------------------------------------------------------

    public function test_component_view_inline_keyboard_builds_reply_markup(): void
    {
        $response = $this->make()->view('keyboard_view');

        $this->assertArrayHasKey('reply_markup', $response->contents);
        $this->assertArrayHasKey('inline_keyboard', $response->contents['reply_markup']);
    }

    public function test_component_view_inline_keyboard_rows_structure(): void
    {
        $response = $this->make()->view('keyboard_view');
        $keyboard = $response->contents['reply_markup']['inline_keyboard'];

        // First row: Profile + Help buttons
        $this->assertCount(2, $keyboard[0]);
        $this->assertSame('Profile', $keyboard[0][0]['text']);
        $this->assertSame('/profile', $keyboard[0][0]['callback_data']);

        // Second row: href() URL link
        $this->assertArrayHasKey('url', $keyboard[1][0]);
    }

    // -------------------------------------------------------------------------
    // view() — component directory: reply keyboard
    // -------------------------------------------------------------------------

    public function test_component_view_reply_keyboard_builds_reply_markup(): void
    {
        $response = $this->make()->view('reply_view');

        $markup = $response->contents['reply_markup'];
        $this->assertArrayHasKey('keyboard', $markup);
        $this->assertTrue($markup['resize_keyboard']);
    }

    public function test_component_view_reply_keyboard_rows(): void
    {
        $response = $this->make()->view('reply_view');
        $keyboard = $response->contents['reply_markup']['keyboard'];

        // First row: Option A + Option B (before row() call)
        $this->assertCount(2, $keyboard[0]);
        // Second row: Back (after row() call)
        $this->assertSame('Back', $keyboard[1][0]['text']);
    }

    // -------------------------------------------------------------------------
    // view() — component directory: media group
    // -------------------------------------------------------------------------

    public function test_component_view_media_sets_send_media_group(): void
    {
        $response = $this->make()->view('media_view');

        $this->assertSame('sendMediaGroup', $response->contents['method']);
    }

    public function test_component_view_media_builds_items_array(): void
    {
        $response = $this->make()->view('media_view');
        $media    = $response->contents['media'];

        $this->assertCount(3, $media);
        $this->assertSame('photo', $media[0]['type']);
        $this->assertSame('photo_id_1', $media[0]['media']);
        $this->assertSame('First', $media[0]['caption']);
        $this->assertSame('video', $media[2]['type']);
    }

    // -------------------------------------------------------------------------
    // view() — conflict detection
    // -------------------------------------------------------------------------

    public function test_component_view_throws_on_multiple_media_components(): void
    {
        $this->expectException(\LogicException::class);

        $this->make()->view('conflict_view');
    }

    public function test_component_view_throws_on_both_keyboard_types(): void
    {
        $this->expectException(\LogicException::class);

        $this->make()->view('keyboard_conflict_view');
    }

    // -------------------------------------------------------------------------
    // view() — builder components need no opening <?php tag
    // -------------------------------------------------------------------------

    public function test_builder_component_with_a_legacy_opening_tag_still_renders(): void
    {
        $response = $this->make()->view('legacy_keyboard_view');
        $keyboard = $response->contents['reply_markup']['inline_keyboard'];

        $this->assertSame('Legacy', $keyboard[0][0]['text']);
    }

    public function test_builder_component_with_a_syntax_error_throws_view_invalid(): void
    {
        $this->expectException(ViewInvalidException::class);

        $this->make()->view('broken_keyboard_view');
    }

    public function test_builder_component_failure_unwinds_the_component_stack(): void
    {
        try {
            $this->make()->view('broken_keyboard_view');
        } catch (ViewInvalidException) {
            // expected
        }

        $this->assertNull(ComponentContext::current());
    }

    // -------------------------------------------------------------------------
    // view() — template comments
    // -------------------------------------------------------------------------

    public function test_template_comment_is_not_sent(): void
    {
        $response = $this->make()->view('comment_view', ['name' => 'Ann', 'body' => 'B']);

        $this->assertStringNotContainsString('comment', $response->contents['text']);
        $this->assertStringNotContainsString('never sent', $response->contents['text']);
    }

    public function test_template_comment_does_not_break_neighbouring_interpolation(): void
    {
        $response = $this->make()->view('comment_view', ['name' => 'Ann', 'body' => '<b>B</b>']);

        // The leading comment block and its newline are gone, so the text starts
        // at the greeting; {{ }} still escapes and {!! !!} still emits raw.
        $this->assertStringStartsWith('Hello, Ann! Bye.', $response->contents['text']);
        $this->assertStringContainsString('<b>B</b>', $response->contents['text']);
    }

    // -------------------------------------------------------------------------
    // setUser()
    // -------------------------------------------------------------------------

    public function test_user_sets_property_when_correct_model_type(): void
    {
        $model    = config('laragram.auth.user.model');
        $userObj  = new $model();
        $response = $this->make()->setUser($userObj);

        $prop = (new \ReflectionProperty($response, 'user'));
        $prop->setAccessible(true);

        $this->assertSame($userObj, $prop->getValue($response));
    }

    public function test_user_keeps_existing_value_when_wrong_type_passed(): void
    {
        $response = $this->make()->setUser(new \stdClass());

        $prop = (new \ReflectionProperty($response, 'user'));
        $prop->setAccessible(true);

        $this->assertNull($prop->getValue($response));
    }

    public function test_user_returns_self_for_chaining(): void
    {
        $response = $this->make();

        $this->assertSame($response, $response->setUser(new \stdClass()));
    }
}
