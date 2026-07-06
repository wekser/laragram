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

namespace Wekser\Laragram;

use Wekser\Laragram\Exceptions\NotExistsViewException;
use Wekser\Laragram\Exceptions\ViewInvalidException;
use Wekser\Laragram\Facades\BotAuth;
use Wekser\Laragram\Models\User;
use Wekser\Laragram\Support\Reaction;
use Wekser\Laragram\Telegram\Inline\InlineResults;
use Wekser\Laragram\Telegram\Payments\Invoice;
use Wekser\Laragram\View\ComponentContext;
use Wekser\Laragram\View\InlineKeyboardState;
use Wekser\Laragram\View\MediaGroupState;
use Wekser\Laragram\View\ReplyKeyboardState;

class BotResponse
{
    /** Next station (state) to assign the user after this response. */
    public ?string $station = null;

    /** Telegram API payload built by text() / view() / answer() / edit() / delete(). */
    public array $contents = [];

    /** Data array passed to the view. */
    protected array $data = [];

    /** Base directory under resources/ where views are stored. */
    protected string $viewsPath;

    /** Authenticated user associated with this response. */
    protected ?User $user = null;

    /**
     * Callback Constructor
     *
     * @param string $path
     */
    public function __construct(string $path)
    {
        $this->viewsPath = $path;

        // Resolving the authenticated user is best-effort: outside a webhook
        // request (broadcasts, the laragram:broadcast command, queue workers)
        // there is no Telegram sender, and the laragram.auth factory throws an
        // AuthenticationException on resolution. Such callers set the recipient
        // explicitly via setUser(), so a missing sender must not be fatal here.
        try {
            $this->user = BotAuth::user();
        } catch (\Throwable) {
            $this->user = null;
        }
    }

    /**
     * Override the authenticated user associated with this response.
     *
     * @param mixed $user
     * @return $this
     */
    public function setUser(mixed $user): self
    {
        $model = config('laragram.auth.user.model');

        if ($user instanceof $model) {
            $this->user = $user;
        }

        return $this;
    }

    /**
     * Set the station to next user request.
     *
     * @param string $station
     * @return $this
     */
    public function redirect(string $station): self
    {
        $this->station = $station;

        return $this;
    }

    /**
     * Begin a new response payload on a fresh instance.
     *
     * Content-entry methods (text(), view(), photo(), answer(), …) each return a
     * NEW BotResponse rather than mutating $this. This lets several responses be
     * collected into an array for a multi-message reply —
     *
     *   return [BotResponse::text('a'), BotResponse::text('b')->redirect('next')];
     *
     * — even though the BotResponse facade resolves a shared singleton instance.
     * Modifier methods (keyboard(), redirect(), setUser()) still mutate and return
     * the same instance, so fluent chaining after an entry method works as before.
     *
     * A redirect() set BEFORE the content method (e.g. redirect('next')->text('hi'))
     * is preserved: the clone adopts the source's pending station, and the source
     * is then cleared so the redirect cannot leak into a later, independent
     * response built from the shared singleton.
     */
    private function begin(array $contents): self
    {
        $response = clone $this; // adopts $this->station (any pending redirect())
        $response->contents = $contents;

        $this->station = null; // consume it on the source so it can't leak forward

        return $response;
    }

    /**
     * Send a text message (sendMessage).
     *
     * Pass raw, unescaped text — Laragram escapes it automatically for the active parse mode.
     * Do NOT pre-escape (e.g. with htmlspecialchars() or addslashes()) — it will double-escape.
     * To send already-escaped or pre-formatted text, pass $format = null.
     *
     * @param string      $text   Raw, unescaped message text.
     * @param string|null $format Parse mode: 'HTML', 'MarkdownV2', 'Markdown', or null (no escaping).
     * @return $this
     */
    public function text(string $text, ?string $format = 'HTML'): self
    {
        return $this->begin([
            'method'     => 'sendMessage',
            'text'       => $this->escapeText($text, $format),
            'parse_mode' => $format,
            '_escaped'   => true,
        ]);
    }

    /**
     * Validate that the parse mode is one of the values accepted by Telegram.
     *
     * @throws \InvalidArgumentException
     */
    protected function validateFormat(?string $format): void
    {
        if ($format !== null && !in_array($format, ['HTML', 'MarkdownV2', 'Markdown'], true)) {
            throw new \InvalidArgumentException(
                "Invalid parse_mode '{$format}'. Allowed values: HTML, MarkdownV2, Markdown."
            );
        }
    }

    /**
     * Escape user-provided text according to Telegram parse mode.
     *
     * @param string $text
     * @param string|null $format
     * @return string
     */
    protected function escapeText(string $text, ?string $format): string
    {
        $this->validateFormat($format);

        if ($format === null) {
            return $text;
        }

        $mode = strtoupper($format);

        if ($mode === 'HTML') {
            return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        if ($mode === 'MARKDOWNV2') {
            // Escape backslash first to avoid double-escaping.
            $text = str_replace('\\', '\\\\', $text);
            return str_replace(array_keys(self::MARKDOWNV2_ESCAPES), array_values(self::MARKDOWNV2_ESCAPES), $text);
        }

        // Legacy Markdown: _ * [ ] ( ) `
        $text = str_replace('\\', '\\\\', $text);
        return str_replace(array_keys(self::MARKDOWN_ESCAPES), array_values(self::MARKDOWN_ESCAPES), $text);
    }

    /**
     * Answer a callback query (answerCallbackQuery).
     *
     * @param string $text    Notification text shown to the user (max 200 chars).
     * @param bool   $showAlert Show as an alert box instead of a toast.
     * @return $this
     */
    public function answer(string $text = '', bool $showAlert = false): self
    {
        return $this->begin([
            'method'     => 'answerCallbackQuery',
            'text'       => $text,
            'show_alert' => $showAlert,
            '_escaped'   => true,
        ]);
    }

    /**
     * Edit the text of the current inline message (editMessageText).
     *
     * Pass raw, unescaped text — Laragram escapes it automatically.
     * Pass $format = null to disable escaping for already-formatted text.
     *
     * @param string      $text   Raw, unescaped new message text.
     * @param string|null $format Parse mode: 'HTML', 'MarkdownV2', 'Markdown', or null (no escaping).
     * @return $this
     */
    public function edit(string $text, ?string $format = 'HTML'): self
    {
        return $this->begin([
            'method'     => 'editMessageText',
            'text'       => $this->escapeText($text, $format),
            'parse_mode' => $format,
            '_escaped'   => true,
        ]);
    }

    /**
     * Delete the current message (deleteMessage).
     *
     * @return $this
     */
    public function delete(): self
    {
        return $this->begin([
            'method'   => 'deleteMessage',
            '_escaped' => true,
        ]);
    }

    /**
     * React to the current message (setMessageReaction).
     *
     * Accepts an emoji string, a list of emoji strings, or raw ReactionType
     * arrays (e.g. ['type' => 'custom_emoji', 'custom_emoji_id' => ...]) for
     * the non-emoji reaction types. An empty array clears the bot's reaction.
     * The chat_id and message_id are injected automatically by
     * ResponseTransformer from the triggering message or message_reaction update.
     *
     *   return BotResponse::react('👍');
     *   return BotResponse::react(['❤️', '🔥'], big: true);
     *   return BotResponse::react([]); // remove reaction
     *
     * @param string|array<int, string|array<string, mixed>> $reaction
     * @param bool $big Show the reaction with a big animation.
     * @return $this
     */
    public function react(string|array $reaction, bool $big = false): self
    {
        return $this->begin([
            'method'   => 'setMessageReaction',
            'reaction' => Reaction::normalize($reaction),
            'is_big'   => $big,
            '_escaped' => true,
        ]);
    }

    /**
     * Answer an inline query (answerInlineQuery).
     *
     * Accepts an InlineResults builder or a raw parameter array. The
     * inline_query_id is injected automatically by ResponseTransformer.
     *
     *   return BotResponse::inlineResults(
     *       InlineResults::make()->article('1', 'Hi', 'Hello!')->cache(300)
     *   );
     *
     * @param InlineResults|array<string, mixed> $results
     * @return $this
     */
    public function inlineResults(InlineResults|array $results): self
    {
        $params = $results instanceof InlineResults ? $results->toArray() : $results;

        return $this->begin(array_merge(
            ['method' => 'answerInlineQuery'],
            $params,
            ['_escaped' => true],
        ));
    }

    /**
     * Send an invoice (sendInvoice).
     *
     * Accepts an Invoice builder or a raw parameter array. The recipient chat_id
     * is injected automatically by ResponseTransformer, like any other send call.
     *
     *   return BotResponse::invoice(
     *       Invoice::make()->title('Pro')->description('1 month')
     *           ->payload('sub_42')->stars(500, 'Pro access')
     *   );
     *
     * @param Invoice|array<string, mixed> $invoice
     * @return $this
     */
    public function invoice(Invoice|array $invoice): self
    {
        $params = $invoice instanceof Invoice ? $invoice->toArray() : $invoice;

        return $this->begin(array_merge(
            ['method' => 'sendInvoice'],
            $params,
            ['_escaped' => true],
        ));
    }

    /**
     * Approve a pending payment (answerPreCheckoutQuery with ok = true).
     *
     * Answer a pre_checkout_query within 10 seconds to let the payment proceed.
     * The pre_checkout_query_id is injected automatically by ResponseTransformer.
     *
     * @return $this
     */
    public function approveCheckout(): self
    {
        return $this->begin([
            'method'   => 'answerPreCheckoutQuery',
            'ok'       => true,
            '_escaped' => true,
        ]);
    }

    /**
     * Reject a pending payment (answerPreCheckoutQuery with ok = false).
     *
     * @param string $reason Human-readable message shown to the user explaining
     *                       why the checkout could not be completed.
     * @return $this
     */
    public function declineCheckout(string $reason): self
    {
        return $this->begin([
            'method'        => 'answerPreCheckoutQuery',
            'ok'            => false,
            'error_message' => $reason,
            '_escaped'      => true,
        ]);
    }

    /**
     * Accept a shipping query and offer shipping options (answerShippingQuery ok = true).
     *
     * @param array<int, array<string, mixed>> $options ShippingOption objects.
     * @return $this
     */
    public function approveShipping(array $options): self
    {
        return $this->begin([
            'method'           => 'answerShippingQuery',
            'ok'               => true,
            'shipping_options' => $options,
            '_escaped'         => true,
        ]);
    }

    /**
     * Reject a shipping query (answerShippingQuery ok = false).
     *
     * @param string $reason Human-readable message explaining why delivery to the
     *                       requested address is not possible.
     * @return $this
     */
    public function declineShipping(string $reason): self
    {
        return $this->begin([
            'method'        => 'answerShippingQuery',
            'ok'            => false,
            'error_message' => $reason,
            '_escaped'      => true,
        ]);
    }

    /**
     * Send a photo (sendPhoto).
     *
     * $fileId accepts a Telegram file_id, a public URL, or 'attach://name' for multipart uploads.
     * For local file uploads use Services\MediaUploader::upload() to get a file_id first.
     * Caption is auto-escaped — do NOT pre-escape it.
     *
     * @param string      $fileId   Telegram file_id, public URL, or 'attach://name'.
     * @param string|null $caption  Raw, unescaped caption text.
     * @param string|null $format   Parse mode for the caption (default: HTML).
     * @return $this
     */
    public function photo(string $fileId, ?string $caption = null, ?string $format = 'HTML'): self
    {
        return $this->begin($this->buildMediaContents('sendPhoto', 'photo', $fileId, $caption, $format));
    }

    /**
     * Send a document (sendDocument).
     *
     * For local file uploads use Services\MediaUploader::upload() to get a file_id first.
     * Caption is auto-escaped — do NOT pre-escape it.
     *
     * @param string      $fileId   Telegram file_id, public URL, or 'attach://name'.
     * @param string|null $caption  Raw, unescaped caption text.
     * @param string|null $format   Parse mode for the caption (default: HTML).
     * @return $this
     */
    public function document(string $fileId, ?string $caption = null, ?string $format = 'HTML'): self
    {
        return $this->begin($this->buildMediaContents('sendDocument', 'document', $fileId, $caption, $format));
    }

    /**
     * Send an audio file (sendAudio).
     *
     * @param string      $fileId   Telegram file_id, public URL, or 'attach://name'.
     * @param string|null $caption  Raw, unescaped caption text.
     * @param string|null $format   Parse mode for the caption (default: HTML).
     * @return $this
     */
    public function audio(string $fileId, ?string $caption = null, ?string $format = 'HTML'): self
    {
        return $this->begin($this->buildMediaContents('sendAudio', 'audio', $fileId, $caption, $format));
    }

    /**
     * Send a video (sendVideo).
     *
     * @param string      $fileId   Telegram file_id, public URL, or 'attach://name'.
     * @param string|null $caption  Raw, unescaped caption text.
     * @param string|null $format   Parse mode for the caption (default: HTML).
     * @return $this
     */
    public function video(string $fileId, ?string $caption = null, ?string $format = 'HTML'): self
    {
        return $this->begin($this->buildMediaContents('sendVideo', 'video', $fileId, $caption, $format));
    }

    /**
     * Send a voice message (sendVoice).
     *
     * @param string      $fileId   Telegram file_id, public URL, or 'attach://name'.
     * @param string|null $caption  Raw, unescaped caption text.
     * @param string|null $format   Parse mode for the caption (default: HTML).
     * @return $this
     */
    public function voice(string $fileId, ?string $caption = null, ?string $format = 'HTML'): self
    {
        return $this->begin($this->buildMediaContents('sendVoice', 'voice', $fileId, $caption, $format));
    }

    /**
     * Send an animation / GIF (sendAnimation).
     *
     * @param string      $fileId   Telegram file_id, public URL, or 'attach://name'.
     * @param string|null $caption  Raw, unescaped caption text.
     * @param string|null $format   Parse mode for the caption (default: HTML).
     * @return $this
     */
    public function animation(string $fileId, ?string $caption = null, ?string $format = 'HTML'): self
    {
        return $this->begin($this->buildMediaContents('sendAnimation', 'animation', $fileId, $caption, $format));
    }

    /**
     * Build the contents array for a single-media API call.
     *
     * Shared by photo(), document(), audio(), video(), voice(), animation() —
     * any change to the common structure (e.g. adding protect_content) only
     * needs to be made here.
     */
    private function buildMediaContents(
        string $method,
        string $field,
        string $fileId,
        ?string $caption,
        ?string $format,
    ): array {
        return array_filter([
            'method'     => $method,
            $field       => $fileId,
            'caption'    => $caption !== null ? $this->escapeText($caption, $format) : null,
            'parse_mode' => $caption !== null ? $format : null,
            '_escaped'   => true,
        ], fn ($v) => $v !== null);
    }

    /**
     * Send a video note / round video (sendVideoNote).
     *
     * @return $this
     */
    public function videoNote(string $fileId): self
    {
        return $this->begin([
            'method'     => 'sendVideoNote',
            'video_note' => $fileId,
            '_escaped'   => true,
        ]);
    }

    /**
     * Send a sticker (sendSticker).
     *
     * @return $this
     */
    public function sticker(string $fileId): self
    {
        return $this->begin([
            'method'   => 'sendSticker',
            'sticker'  => $fileId,
            '_escaped' => true,
        ]);
    }

    /**
     * Attach a reply keyboard or inline keyboard to the current response.
     * Must be chained after text(), photo(), document(), etc.
     *
     * @param array $markup ReplyKeyboard, InlineKeyboard, ForceReply, or ReplyKeyboardRemove array.
     * @return $this
     * @throws \LogicException when called before a response method has been set.
     */
    public function keyboard(array $markup): self
    {
        if (empty($this->contents)) {
            throw new \LogicException(
                'BotResponse::keyboard() must be called after text(), photo(), edit(), etc.'
            );
        }

        $this->contents['reply_markup'] = $markup;
        return $this;
    }

    /**
     * Send a chat action (sendChatAction).
     * Use to indicate activity while processing a long task.
     *
     * @param string $action One of: typing, upload_photo, record_video, upload_video,
     *                       record_voice, upload_voice, upload_document, choose_sticker,
     *                       find_location, record_video_note, upload_video_note
     * @return $this
     */
    public function action(string $action = 'typing'): self
    {
        return $this->begin([
            'method'   => 'sendChatAction',
            'action'   => $action,
            '_escaped' => true,
        ]);
    }

    /**
     * Render a component view from a directory.
     *
     * Each component of a Telegram message lives in its own file inside the view directory.
     * The directory name uses dot-notation (dots map to subdirectory separators):
     *
     *   text.php            — message text or media caption (template syntax)
     *   photo.php           — photo file_id / URL               → sendPhoto
     *   video.php           — video file_id / URL               → sendVideo
     *   document.php        — document file_id / URL            → sendDocument
     *   audio.php           — audio file_id / URL               → sendAudio
     *   voice.php           — voice file_id / URL               → sendVoice
     *   animation.php       — animation file_id / URL           → sendAnimation
     *   sticker.php         — sticker file_id                   → sendSticker
     *   video_note.php      — video note file_id                → sendVideoNote
     *   media.php           — album via photo()/video() helpers → sendMediaGroup
     *   inline_keyboard.php — InlineKeyboard via button()/href()/row() helpers
     *   reply_keyboard.php  — ReplyKeyboard via reply()/row()/resize()/one_time()
     *
     * Template syntax — write plain text, use {{ }} for dynamic values:
     *   {{ $name }}              — variable from $data (extracted into scope)
     *   {{ $user->first_name }}  — any PHP expression
     *
     * @param string      $view   Dot-notation view name (e.g. 'welcome', 'menu.main').
     * @param array       $data   Variables made available in the view scope.
     * @param string|null $format Parse mode: 'HTML', 'MarkdownV2', 'Markdown', or null.
     * @return $this
     */
    public function view(string $view, array $data = [], ?string $format = 'HTML'): self
    {
        return $this->begin($this->render($view, $data, $format));
    }

    /**
     * Resolve the view directory and assemble a payload from its components.
     */
    protected function render(string $view, ?array $data, ?string $format = 'HTML'): array
    {
        $this->setData($data);

        if (str_contains($view, '..') || str_contains($view, '/') || str_contains($view, '\\')) {
            throw new NotExistsViewException($view);
        }

        $dirPath = resource_path($this->viewsPath . '/' . str_replace('.', '/', $view));

        if (!is_dir($dirPath)) {
            throw new NotExistsViewException($dirPath);
        }

        return $this->renderDirectory($dirPath, $format);
    }

    /**
     * MarkdownV2 special characters mapped to their escaped form. Using a single
     * char => replacement map (instead of two positional arrays) makes it
     * impossible to add a character without its matching replacement.
     * Note: the backslash itself is escaped separately, BEFORE this map is
     * applied, to avoid double-escaping the backslashes this map introduces.
     */
    private const MARKDOWNV2_ESCAPES = [
        '_' => '\\_', '*' => '\\*', '[' => '\\[', ']' => '\\]', '(' => '\\(',
        ')' => '\\)', '~' => '\\~', '`' => '\\`', '>' => '\\>', '#' => '\\#',
        '+' => '\\+', '-' => '\\-', '=' => '\\=', '|' => '\\|', '{' => '\\{',
        '}' => '\\}', '.' => '\\.', '!' => '\\!',
    ];

    /** Legacy Markdown special characters mapped to their escaped form. */
    private const MARKDOWN_ESCAPES = [
        '_' => '\\_', '*' => '\\*', '[' => '\\[', ']' => '\\]',
        '(' => '\\(', ')' => '\\)', '`' => '\\`',
    ];

    /**
     * Maps component filename (without .php) to the Telegram API method it triggers.
     * Used by both detectMethod() and renderDirectory() — single source of truth.
     */
    private const MEDIA_COMPONENT_MAP = [
        'photo'      => 'sendPhoto',
        'video'      => 'sendVideo',
        'document'   => 'sendDocument',
        'audio'      => 'sendAudio',
        'voice'      => 'sendVoice',
        'animation'  => 'sendAnimation',
        'sticker'    => 'sendSticker',
        'video_note' => 'sendVideoNote',
        'media'      => 'sendMediaGroup',
    ];

    /**
     * Assemble a payload from the component files inside a view directory.
     */
    protected function renderDirectory(string $dirPath, ?string $format): array
    {
        $method  = $this->detectMethod($dirPath);
        $payload = [];

        // ── Text / caption component ─────────────────────────────────────────
        $textFile = $dirPath . '/text.php';

        if (file_exists($textFile)) {
            // Interpolated {{ }} values are escaped during rendering; the static
            // template text (the author's own *bold* / _italic_ markup) is left
            // intact. Do NOT re-escape the assembled string here.
            $raw     = trim($this->renderTemplate($textFile, $format));
            $isMedia = $method !== 'sendMessage' && $method !== 'sendMediaGroup';

            $payload[$isMedia ? 'caption' : 'text'] = $raw;

            if ($format !== null) {
                $payload['parse_mode'] = $format;
            }
        }

        // ── Single media component ───────────────────────────────────────────
        $singleTypes = array_keys(array_diff(self::MEDIA_COMPONENT_MAP, ['sendMediaGroup']));

        foreach ($singleTypes as $type) {
            $mediaFile = $dirPath . '/' . $type . '.php';

            if (file_exists($mediaFile)) {
                // file_id / URL — never escape (null disables interpolation escaping).
                $payload[$type] = trim($this->renderTemplate($mediaFile, null));
                break;
            }
        }

        // ── Media group component ────────────────────────────────────────────
        $mediaFile = $dirPath . '/media.php';

        if (file_exists($mediaFile)) {
            $items = $this->renderMediaGroupComponent($mediaFile);

            if (empty($items)) {
                throw new \LogicException("View [{$dirPath}]: media.php produced no items. Call photo() or video() at least once.");
            }

            // Escape captions inside each media item — sanitizeContents() only
            // handles top-level fields and does not walk nested media arrays.
            $payload['media'] = array_map(function (array $item) use ($format): array {
                if (isset($item['caption'])) {
                    $item['caption'] = $this->escapeText($item['caption'], $format);
                }
                return $item;
            }, $items);
        }

        // ── Keyboard components ──────────────────────────────────────────────
        $inlineFile      = $dirPath . '/inline_keyboard.php';
        $replyFile       = $dirPath . '/reply_keyboard.php';
        $hasInlineFile   = file_exists($inlineFile);
        $hasReplyFile    = file_exists($replyFile);

        if ($hasInlineFile && $hasReplyFile) {
            throw new \LogicException(
                "View [{$dirPath}] cannot have both inline_keyboard.php and reply_keyboard.php."
            );
        }

        if ($hasInlineFile) {
            $markup = $this->renderInlineKeyboardComponent($inlineFile);

            if (empty($markup['inline_keyboard'])) {
                throw new \LogicException("View [{$dirPath}]: inline_keyboard.php produced no buttons. Call button() or href() at least once.");
            }

            $payload['reply_markup'] = $markup;
        } elseif ($hasReplyFile) {
            $markup = $this->renderReplyKeyboardComponent($replyFile);

            if (empty($markup['keyboard'])) {
                throw new \LogicException("View [{$dirPath}]: reply_keyboard.php produced no keys. Call reply() at least once.");
            }

            $payload['reply_markup'] = $markup;
        }

        $payload['method'] = $method;

        return $payload;
    }

    /**
     * Detect the Telegram API method from which component files are present.
     * Throws when multiple conflicting media components exist.
     */
    protected function detectMethod(string $dirPath): string
    {
        $found = [];

        foreach (self::MEDIA_COMPONENT_MAP as $file => $method) {
            if (file_exists($dirPath . '/' . $file . '.php')) {
                $found[] = $method;
            }
        }

        if (count($found) > 1) {
            throw new \LogicException(
                "View [{$dirPath}] has multiple media components: " . implode(', ', $found) . '. Only one is allowed.'
            );
        }

        return $found[0] ?? 'sendMessage';
    }

    /**
     * Evaluate a template file and capture the output via output buffering.
     *
     * Two interpolation forms are supported:
     *   {{ expr }}   — value is escaped for the active parse mode (use for user data)
     *   {!! expr !!} — value is emitted raw, unescaped (use for trusted, already
     *                  formatted content such as translation strings containing
     *                  *bold* / _italic_ / <b> markup)
     *
     * Static template text (the author's own markup) is always emitted verbatim,
     * so formatting written directly in the view renders as intended.
     * Pass $format = null to disable escaping of {{ }} values entirely
     * (used for file_id / URL components).
     *
     * Variables available in template scope:
     *   - each key from $data as its own variable (via extract)
     *   - $user — the authenticated User instance
     */
    protected function renderTemplate(string $path, ?string $format = null): string
    {
        // The compiled PHP depends only on the file contents, so it is cached
        // per path (invalidated on mtime change so dev edits are picked up).
        // Runtime values flow in via $data / $__esc, never through the cache.
        $compiled = $this->compileTemplate($path);

        // Escaper for {{ }} values; static markup and {!! !!} are never passed through it.
        $escaper = fn (mixed $value): string => $this->escapeText((string) $value, $format);

        ob_start();

        try {
            (static function (string $__template, array $data, ?User $user, callable $__esc): void {
                // EXTR_SKIP: never overwrite $__template, $data, $user or $__esc
                // even if $data contains keys with those names.
                extract($data, EXTR_SKIP);
                eval('?>' . $__template);
            })($compiled, $this->data, $this->user, $escaper);
        } catch (\Throwable $e) {
            ob_end_clean();
            throw new ViewInvalidException($path, 0, $e);
        }

        return (string) ob_get_clean();
    }

    /**
     * Compiled-template cache, keyed by file path. Each entry stores the file's
     * mtime so an edited template is recompiled rather than served stale.
     *
     * @var array<string, array{mtime: int, compiled: string}>
     */
    private static array $templateCache = [];

    /**
     * Read a template file and compile its {{ }} / {!! !!} interpolations into
     * executable PHP, caching the result per path until the file's mtime changes.
     */
    private function compileTemplate(string $path): string
    {
        $mtime  = (int) @filemtime($path);
        $cached = self::$templateCache[$path] ?? null;

        if ($cached !== null && $cached['mtime'] === $mtime) {
            return $cached['compiled'];
        }

        $source = (string) file_get_contents($path);

        // Raw output {!! expr !!} first, so its braces aren't caught by the {{ }} pass.
        $compiled = preg_replace('/\{!!\s*(.+?)\s*!!\}/s', '<?php echo $1; ?>', $source);

        // Escaped output {{ expr }}.
        $compiled = preg_replace('/\{\{\s*(.+?)\s*\}\}/s', '<?php echo $__esc($1); ?>', $compiled);

        self::$templateCache[$path] = ['mtime' => $mtime, 'compiled' => $compiled];

        return $compiled;
    }

    /**
     * Clear the compiled-template cache. Intended for tests that render the same
     * view path across cases with differing on-disk contents.
     */
    public static function flushTemplateCache(): void
    {
        self::$templateCache = [];
    }

    /**
     * Execute an inline_keyboard.php component and collect buttons via global helpers.
     */
    protected function renderInlineKeyboardComponent(string $path): array
    {
        $state = new InlineKeyboardState();
        ComponentContext::push($state);

        try {
            (static function (string $__path, array $data, ?User $user): void {
                extract($data, EXTR_SKIP);
                include $__path;
            })($path, $this->data, $this->user);
        } finally {
            ComponentContext::pop();
        }

        return $state->toArray();
    }

    /**
     * Execute a reply_keyboard.php component and collect keys via global helpers.
     */
    protected function renderReplyKeyboardComponent(string $path): array
    {
        $state = new ReplyKeyboardState();
        ComponentContext::push($state);

        try {
            (static function (string $__path, array $data, ?User $user): void {
                extract($data, EXTR_SKIP);
                include $__path;
            })($path, $this->data, $this->user);
        } finally {
            ComponentContext::pop();
        }

        return $state->toArray();
    }

    /**
     * Execute a media.php component and collect items via global helpers.
     */
    protected function renderMediaGroupComponent(string $path): array
    {
        $state = new MediaGroupState();
        ComponentContext::push($state);

        try {
            (static function (string $__path, array $data, ?User $user): void {
                extract($data, EXTR_SKIP);
                include $__path;
            })($path, $this->data, $this->user);
        } finally {
            ComponentContext::pop();
        }

        return $state->toArray();
    }

    /**
     * Set the data passed to the view.
     */
    protected function setData(?array $data): void
    {
        $this->data = empty($data) ? [] : $data;
    }

    /**
     * Sanitize resulting contents from views by escaping user-facing fields.
     * Skips escaping when content is already marked with '_escaped'.
     */
    protected function sanitizeContents(array $contents): array
    {
        if (!empty($contents['_escaped'])) {
            unset($contents['_escaped']);
            return $contents;
        }

        $mode = $contents['parse_mode'] ?? 'HTML';

        if (isset($contents['text']) && is_string($contents['text'])) {
            $contents['text'] = $this->escapeText($contents['text'], $mode);
        }

        if (isset($contents['caption']) && is_string($contents['caption'])) {
            $contents['caption'] = $this->escapeText($contents['caption'], $mode);
        }

        return $contents;
    }
}