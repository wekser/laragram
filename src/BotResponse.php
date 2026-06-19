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
        $this->user = BotAuth::user();
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
        $this->contents = [
            'method'     => 'sendMessage',
            'text'       => $this->escapeText($text, $format),
            'parse_mode' => $format,
            '_escaped'   => true,
        ];

        return $this;
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
            return str_replace(self::MARKDOWNV2_CHARS, self::MARKDOWNV2_REPLACEMENTS, $text);
        }

        // Legacy Markdown: _ * [ ] ( ) `
        $text = str_replace('\\', '\\\\', $text);
        return str_replace(self::MARKDOWN_CHARS, self::MARKDOWN_REPLACEMENTS, $text);
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
        $this->contents = [
            'method'     => 'answerCallbackQuery',
            'text'       => $text,
            'show_alert' => $showAlert,
            '_escaped'   => true,
        ];

        return $this;
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
        $this->contents = [
            'method'     => 'editMessageText',
            'text'       => $this->escapeText($text, $format),
            'parse_mode' => $format,
            '_escaped'   => true,
        ];

        return $this;
    }

    /**
     * Delete the current message (deleteMessage).
     *
     * @return $this
     */
    public function delete(): self
    {
        $this->contents = [
            'method'   => 'deleteMessage',
            '_escaped' => true,
        ];

        return $this;
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
        $this->contents = $this->buildMediaContents('sendPhoto', 'photo', $fileId, $caption, $format);
        return $this;
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
        $this->contents = $this->buildMediaContents('sendDocument', 'document', $fileId, $caption, $format);
        return $this;
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
        $this->contents = $this->buildMediaContents('sendAudio', 'audio', $fileId, $caption, $format);
        return $this;
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
        $this->contents = $this->buildMediaContents('sendVideo', 'video', $fileId, $caption, $format);
        return $this;
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
        $this->contents = $this->buildMediaContents('sendVoice', 'voice', $fileId, $caption, $format);
        return $this;
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
        $this->contents = $this->buildMediaContents('sendAnimation', 'animation', $fileId, $caption, $format);
        return $this;
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
        $this->contents = [
            'method'     => 'sendVideoNote',
            'video_note' => $fileId,
            '_escaped'   => true,
        ];

        return $this;
    }

    /**
     * Send a sticker (sendSticker).
     *
     * @return $this
     */
    public function sticker(string $fileId): self
    {
        $this->contents = [
            'method'   => 'sendSticker',
            'sticker'  => $fileId,
            '_escaped' => true,
        ];

        return $this;
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
        $this->contents = [
            'method'   => 'sendChatAction',
            'action'   => $action,
            '_escaped' => true,
        ];

        return $this;
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
        $this->contents = $this->render($view, $data, $format);

        return $this;
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

    private const MARKDOWNV2_CHARS = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
    private const MARKDOWNV2_REPLACEMENTS = ['\\_', '\\*', '\\[', '\\]', '\\(', '\\)', '\\~', '\\`', '\\>', '\\#', '\\+', '\\-', '\\=', '\\|', '\\{', '\\}', '\\.', '\\!'];

    private const MARKDOWN_CHARS = ['_', '*', '[', ']', '(', ')', '`'];
    private const MARKDOWN_REPLACEMENTS = ['\\_', '\\*', '\\[', '\\]', '\\(', '\\)', '\\`'];

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
        $source = file_get_contents($path);

        // Escaper for {{ }} values; static markup and {!! !!} are never passed through it.
        $escaper = fn (mixed $value): string => $this->escapeText((string) $value, $format);

        // Raw output {!! expr !!} first, so its braces aren't caught by the {{ }} pass.
        $compiled = preg_replace('/\{!!\s*(.+?)\s*!!\}/s', '<?php echo $1; ?>', $source);

        // Escaped output {{ expr }}.
        $compiled = preg_replace('/\{\{\s*(.+?)\s*\}\}/s', '<?php echo $__esc($1); ?>', $compiled);

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