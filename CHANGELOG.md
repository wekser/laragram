# Release Notes

All notable changes to `Laragram` will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [v2.0.0] (2026-06-25)

This is a major release. It introduces a redesigned namespace structure, an auth driver system, a full test suite, and several breaking changes. Deprecated aliases are provided where possible to ease migration.

### Added

**Routing**
- `Routing\Route` — immutable value object representing a single route (readonly properties)
- `Routing\RouteCollection` — fluent DSL for building routes (replaces `BotRouteCollection`)
- `Routing\Router` — route dispatcher (replaces `BotRouter`)
- `->role(string|array $roles)` DSL method restricts a route to users with a specific role
- `->group(callable $callback, from: '...', roles: '...')` — applies shared station and role constraints to a group of routes
- `Router::flushCache()` — clears the static route file cache (useful in tests)

**Scenes (Wizards)**
- `Scene\SceneManager` — runtime for multi-step conversation flows, layered above the station router; produces the same output shape as `Routing\Router`. Bound as the `laragram.scene` singleton. While a user is in a scene their station is the sentinel `@scene:<name>`, and the current step + collected answers are persisted in `laragram_sessions.payload` (no new table). Requires the `database` auth driver
- `Scene\Scene` / `Scene\Step` — fluent definition DSL: `->step()`, `->ask(view|closure)`, `->rules()`, `->messages()`, `->transform()`, `->when()` (conditional steps), `->onInvalid()` (custom error prompt), `->using()` + `->expectText()/expectCallback()/expectContact()/expectLocation()/expectPhoto()` (typed extractors), `->cancelOn()` / `->onCancel()`, `->allowBack()` (back navigation), `->timeout()` / `->onTimeout()`, and `->onComplete()`
- `Scene\SceneContext` — passed to prompts and handlers: `get()`, `all()`, `has()`, `request()`, `user()`
- `Scene\SceneTransition` — marker returned by `BotScene::enter()`, triggering `SceneManager::start()` from a route handler
- `Scene\SceneRegistry` — lazily loads and caches scene definitions from `routes/laragram/scenes.php`; `flushCache()` for tests
- `Facades\BotScene` — `define()` scenes in the scenes file, `enter()` from a route handler
- Global escape commands (`scenes.global_commands`) leave any scene and are re-dispatched through the normal router
- Scenes run through the shared `Laragram::handle()` pipeline, so they work synchronously and under queue offload (the job's per-user `WithoutOverlapping` lock serializes steps)
- Scene runtime guarantees: an invalid answer re-asks the step **without** resetting the inactivity timer (so a stream of invalid replies can't keep a scene alive past `timeout()`); back navigation prunes answers belonging to steps a changed earlier answer made ineligible (so `onComplete` never sees data inconsistent with the new answers); and a global escape command whose handler returns `BotScene::enter()` starts that new scene instead of being dropped

**Broadcasting (mass messaging)**
- `Broadcasting\Broadcaster` — entry point for sending one message to the whole user base; `view()` / `text()` / `message()` return a fresh `Broadcasting\PendingBroadcast`. Bound as the `laragram.broadcast` singleton
- `Facades\BotBroadcast` — facade for the broadcaster: `BotBroadcast::text(...)->send()` / `BotBroadcast::view(...)->role(...)->send()` / `BotBroadcast::message(BotResponse::photo(...)->keyboard(...))->send()`
- `Broadcaster::message(BotResponse $response)` — broadcast a fully-composed `BotResponse` (formatting + inline/reply keyboard + media, anything a normal reply can carry). Captures the already-built `$response->contents` into a serializable `payload` content spec; rendered **once** at compose time (not re-localized per recipient — use `view()` for per-user language). Throws `\InvalidArgumentException` on an empty `BotResponse` (no content method called)
- `Broadcasting\PendingBroadcast` — fluent audience (`role()`, `includeInactive()`, `query()`), `count()`, and `send()`; iterates recipients with `chunkById` (clearing any caller-supplied ordering first so the primary-key cursor stays stable) and renders each message inside a per-recipient guard, so one failed render is counted and skipped rather than aborting the whole run; dispatches per-recipient jobs when the queue is enabled and sends synchronously (throttled) otherwise
- `Broadcasting\BroadcastRenderer` — renders a content spec (`view` / `text` / `payload`) to a Telegram payload. For `view` / `text` it renders **per recipient**, setting (and afterwards restoring) the translator locale from the user's language and honoring an explicit `null` format as already-formatted text; a `payload` spec (from `message()`) is returned verbatim. `chat_id = uid` is injected for every recipient
- `Broadcasting\ViewCatalog` — discovers the on-disk views available for broadcasting: scans `resources/{paths.views}` for directories containing a renderable component (`text.php` or a media component), returning dot-notated names sorted alphabetically; used to populate the admin panel's view picker
- `Broadcasting\BroadcastResult` — value object reporting `total` / `sent` / `failed` / `queued`
- `Jobs\SendBroadcastMessage` — queued per-recipient delivery (when `queue.enabled`); `ShouldBeEncrypted`, throttled by the `laragram` rate limiter
- `Listeners\DeactivateUnreachableUser` — bound to `BotExceptionHandled`, marks a user inactive on a terminal/unreachable send error so future broadcasts skip them (every send, not just broadcasts); keyed on the recipient id carried in the Bot API error context and ignores a non-positive id, so a failed group/channel send can't deactivate a coincidental user; gated by `broadcast.deactivate_unreachable`
- `laragram:broadcast {message?}` command — `--view`, `--data` (JSON template data for `--view`; invalid JSON fails the command), `--role=*`, `--include-inactive`, `--dry-run`, `--no-confirm`; requires the `database` auth driver
- `broadcast.chunk_size` / `broadcast.sync_delay_ms` / `broadcast.deactivate_unreachable` config keys

**Payments (Invoices + Telegram Stars)**
- `Telegram\Payments\Invoice` — fluent `sendInvoice`/`createInvoiceLink` params builder: `title()`, `description()`, `payload()`, `currency()`, `price(label, minorUnits)`, `providerToken()`, plus `photo()`, `needName()/needPhoneNumber()/needEmail()/needShippingAddress()`, `flexible()`, `maxTip()/suggestedTips()`, `startParameter()`, `providerData()`; `->stars(int $amount, string $label)` is the Telegram Stars shortcut (currency `XTR`, no provider token). `toArray()` validates required fields; fiat falls back to `payments.currency` / `payments.provider_token` config. Amounts are in the currency's smallest unit
- `BotResponse::invoice(Invoice|array)` → `sendInvoice`; `approveCheckout()` / `declineCheckout($reason)` → `answerPreCheckoutQuery`; `approveShipping($options)` / `declineShipping($reason)` → `answerShippingQuery` (all clone-on-entry). `ResponseTransformer` auto-injects `pre_checkout_query_id` / `shipping_query_id` from the update (these methods carry no `chat_id`)
- `BotRequest::preCheckoutQuery()`, `shippingQuery()`, `successfulPayment()` accessors; route a completed payment with the listener override `BotRoute::get('message', 'successful_payment')`
- `Services\Payments` (alias `laragram.payments`) — outbound payment actions: `invoiceLink()` (`createInvoiceLink`), `refund($userId, $chargeId)` (`refundStarPayment`), `starTransactions()` (`getStarTransactions`)
- `Events\PaymentReceived($user, $payment)` — fired for **every** completed payment (`message.successful_payment`), independently of routing, on both the sync and queued paths; accessors `invoicePayload()`, `chargeId()`, `totalAmount()`, `currency()`, `isStars()`. Dispatch is guarded so a listener error never breaks update processing
- `Listeners\RecordPayment` + `Models\Payment` — opt-in payment history: persists each payment to `laragram_payments` via `updateOrCreate` keyed on `telegram_payment_charge_id` (idempotent under redelivery); gated by `payments.store`, requires the `database` driver and the published `create_laragram_payments_table` migration
- `payments.provider_token` / `payments.currency` / `payments.store` / `payments.table` config keys (env: `LARAGRAM_PAYMENT_PROVIDER_TOKEN`, `LARAGRAM_PAYMENT_CURRENCY`, `LARAGRAM_PAYMENTS_STORE`)

**Inline Mode**
- `Telegram\Inline\InlineResults` — fluent `answerInlineQuery` results builder: `article()`, `photo()`, `gif()`, `video()`, `document()`, `cachedPhoto()`, `sticker()` (by file_id), and `raw(array)` for any other `InlineQueryResult` type; answer-level `cache()`, `personal()`, `nextOffset()` (pagination), `button()` (`InlineQueryResultsButton`). `toArray()` validates unique result ids and the 50-result cap
- `BotResponse::inlineResults(InlineResults|array)` → `answerInlineQuery` (clone-on-entry); `ResponseTransformer` injects `inline_query_id` from the update (no `chat_id`)
- `BotRequest::chosenInlineResult()` — the `chosen_inline_result` object for post-selection analytics (requires inline feedback enabled with @BotFather)

**Incoming Files (getFile + download)**
- `Services\MediaDownloader` (alias `laragram.downloader`) — the mirror image of `MediaUploader`: `getFile($fileId)` (Telegram File object), `download($fileId)` (raw bytes), `save($fileId, ?disk, ?path)` (streams to a Laravel Storage disk, returns the stored path)
- `BotRequest::fileId()` — extracts the file_id from the incoming update across the common media fields (photo → largest size; document, video, audio, voice, animation, video_note, sticker); `BotRequest::file()` returns a `Support\IncomingFile` handle (`id()` / `bytes()` / `save()`); both return `null` when the update carries no file
- Downloads are hardened: the URL host is pinned to `api.telegram.org`, a Telegram-supplied `file_path` containing `..` or a URL scheme is rejected (no SSRF), and `downloads.max_size` caps the byte size (default 20 MB, matching Telegram's getFile limit)
- `downloads.disk` / `downloads.max_size` / `downloads.timeout` config keys (env: `LARAGRAM_DOWNLOADS_DISK`, `LARAGRAM_DOWNLOADS_MAX_SIZE`, `LARAGRAM_DOWNLOADS_TIMEOUT`)

**Group Chats & Forum Topics**
- The bot works in groups/supergroups as well as private chats; private (1-on-1) behaviour is unchanged, because in a private chat `chat.id == from.id`
- `Support\Command::stripMention()` — normalises the `@botusername` suffix Telegram appends to commands in groups (`/start@MyBot`) before pattern matching; `telegram.username` config (env `LARAGRAM_BOT_USERNAME`) pins it to *your* bot, and an empty value strips any `@suffix`
- `BotAuth::findChatInPayload()` / `findThreadInPayload()` — pure statics resolving the originating chat and forum topic from any update payload (never the request-scoped `BotAuth` singleton, which can be stale under a long-running queue worker); instance accessors `chatId()`, `chatType()`, `threadId()`
- Topic detection is gated on **`is_topic_message`**, not on the mere presence of `message_thread_id`: Telegram also sets that field on a plain reply inside a *non-forum* supergroup, where the "thread" is the reply chain and the id is not a valid send target. A forum's General topic carries neither field, so it resolves to "no topic" like every private chat
- **Per-(user, chat, topic) session state** — the `laragram_sessions` upsert key is the composite `(user_id, chat_id, thread_id)`, so each member keeps independent station + scene state in each chat, and in each forum topic within it. `chat_id` / `thread_id` are computed once in `Http\RequestTransformer::build()` and threaded through `output['update']`; `User::session(?int $chatId, ?int $threadId)` reads them back. Scenes inherit this for free (their state lives in the same session payload)
- **Outbound targeting** — `Http\ResponseTransformer` falls back to the originating `chat_id` (rather than the sender's uid) so a reply with no explicit chat never DMs a group member, and injects `message_thread_id` onto every outbound method that accepts one (any `send*`, plus `copyMessage` / `forwardMessage`), so a reply to a message in a topic lands back in that topic. Methods that take no thread (`answer*`, `edit*`, `delete*`, `setMessageReaction`) never receive it
- `BotResponse::thread(?int $threadId)` — a modifier (mutating, like `keyboard()`): `thread(42)` sends into a specific topic regardless of where the update came from; `thread(null)` opts out of the injection and posts to the group's General topic. Throws `\LogicException` before a content method
- Route DSL: `->chat(string ...$types)` restricts a route to chat types, with `->inGroups()` (group+supergroup) and `->inPrivate()` shortcuts; `->thread(int ...$ids)` restricts it to specific forum topics. Empty (the default) matches any chat type / any topic, so existing routes are unaffected. `group(..., chatTypes: 'group', threads: 42)` sets either for a whole group
- `BotRequest`: `chatType()`, `isPrivate()`, `isGroup()` (group||supergroup), `isSupergroup()`, `isChannel()`, `threadId()`, `isTopicMessage()`; `chat()` resolves the chat from the object or the nested `message.chat` (callback_query)
- **Known limitation:** messages from anonymous group admins / on behalf of a chat carry `sender_chat` and no `from` user — they pass through as senderless and are not tied to a `User`

**Message Reactions**
- `message_reaction` (listener `user`) and `message_reaction_count` (listener `reactions`) update types are now routable
- `BotResponse::react(string|array $reaction, bool $big = false)` → `setMessageReaction` (clone-on-entry): accepts an emoji string, a list of emoji, raw `ReactionType` arrays (custom emoji), or `[]` to clear the bot's reaction; `ResponseTransformer` injects `chat_id` + `message_id` from the triggering message or `message_reaction` object, so it works from both reaction handlers and normal message handlers
- `BotRequest::messageReaction()` — the `MessageReactionUpdated` object (`chat`, `message_id`, `user`/`actor_chat`, `old_reaction`, `new_reaction`)
- `BotAuth::isSenderlessPayload()` extended so anonymous reactions (`actor_chat`, no `user`) and `message_reaction_count` updates pass `CheckAuth`; note Telegram only delivers reaction updates when `allowed_updates` explicitly includes them

**Admin Panel**
- Bundled server-rendered dashboard (own routes + Blade views with inline CSS, no build step, no external dependencies — the Horizon/Telescope model), mounted at `config('laragram.admin.path')` (default `laragram/admin`) with the `admin.middleware` group. Requires the `database` auth driver
- Pages (route names `laragram.admin.*`): dashboard metrics, users (set role, activate/deactivate), sessions (browse + prune), and a broadcast composer (dry-run count or send, honouring the queue/sync path); `Admin\Metrics` computes the read-only aggregates
- Broadcast composer offers a **Text** or **View** mode: View mode picks an on-disk view from `Broadcasting\ViewCatalog::all()` (validated against that whitelist) plus an optional JSON data field, so an operator can send a fully-formatted view — buttons/media from its component files, localized per recipient — not just plain text. Defaults to `text`, so a form/caller without `content_type` behaves exactly as before
- **Login-backed access.** The panel is protected by its own login page against the `laragram_admins` table (`Models\Admin`, a dedicated `Authenticatable`) via a self-registered `laragram_admin` session guard — host apps need **no** `config/auth.php` edits and it works in production from any IP. `Admin\Controllers\AuthController` handles `show`/`login`/`logout` (session regenerate/invalidate); `Admin\Middleware\Authorize` resolves access in order: a `viewLaragram` Gate ability decides if defined (escape hatch to reuse the host app's own web auth; a denying gate is a hard 403), otherwise an authenticated `laragram_admin` passes and an unauthenticated visitor is redirected to the login page. The guard/model/table are configurable via `admin.guard` / `admin.model` / `admin.table`
- `laragram:admin:create {username?}` (`--name`, `--password`, min 8 chars, prompted if omitted; password auto-hashed by the model's `hashed` cast) creates or resets a login account; `laragram:admin:delete {username}` removes one
- `Models\Admin` + `laragram_admins` migration (`name`, `username` unique, `password` hashed, `remember_token`, timestamps)
- Synchronous web broadcasts are capped by `broadcast.web_sync_limit` (default 200) — above it the panel asks you to enable the queue or use `laragram:broadcast`
- `admin.enabled` / `admin.path` / `admin.middleware` / `admin.guard` / `admin.model` / `admin.table` config keys (env: `LARAGRAM_ADMIN_ENABLED`, `LARAGRAM_ADMIN_PATH`)

**HTTP Layer**
- `Http\RequestTransformer` — builds `BotRequest` from the raw update and the matched route (replaces `Support\FormRequest`)
- `Http\ResponseTransformer` — normalizes the controller response — a single `BotResponse`/string **or an array/iterable of them** — into `output['response']['views']` (a list); per-view auto-injects `chat_id`, `message_thread_id`, `callback_query_id`, and `message_id`; resolves one `redirect` per batch (last-write-wins) (replaces `Support\FormResponse`)
- `Http\ResponseDispatcher` — delivers each formed view as a separate outbound `BotAPI::{method}()` call, in order; stops the batch on a terminal (user-unreachable) error; bound as the `laragram.dispatcher` singleton; shared by both the webhook entry point and `laragram:poll`
- **Multiple messages per update** — a controller or route closure may return an array of `BotResponse` (or strings) to send several messages in reply to one update
- Log warning in `ResponseTransformer` when `chat_id` cannot be determined for an outgoing API call

**Auth Drivers**
- `Auth\AuthDriverInterface` — contract for authentication drivers: `resolveUser()` and `isActive()`
- `Auth\DatabaseAuthDriver` — persists the user to the database on every request using `updateOrCreate`; merges settings without replacing them
- `Auth\ArrayAuthDriver` — in-memory driver; no database I/O; station is always `'start'`
- `BotAuth::getDriverInstance()` — returns the active `AuthDriverInterface` instance
- `BotAuth::findFromInPayload(array $payload): ?array` — public static helper to extract the `from` field from any update type

**Telegram Helpers**
- `Telegram\Keyboards\InlineKeyboard` — fluent builder for `InlineKeyboardMarkup` covering the full `InlineKeyboardButton` API: `button()`, `href()`, `webApp()`, `switchInline()`, `switchInlineChosen()`, `switchInlineChosenChat()` (Bot API 6.7+), `loginUrl()`, `copyText()` (Bot API 7.11+), `pay()`, `callbackGame()`, plus `raw()`, `row()`, and a `paginate()` helper
- `Telegram\Keyboards\ReplyKeyboard` — fluent builder for `ReplyKeyboardMarkup`; one-tap request buttons `requestContact()`, `requestLocation()`, `requestPoll()`, `requestUser()` / `requestChat()` (Bot API 6.5+), the `resize()` / `oneTime()` / `persistent()` / `selective()` / `placeholder()` options, and static `remove()` for `ReplyKeyboardRemove`
- `Telegram\Keyboards\ForceReply` — fluent builder for `ForceReply` with `placeholder()` and `selective()` options
- `Telegram\Media\MediaGroup` — fluent builder for `sendMediaGroup` payloads (up to 10 items)
- `Enums\ButtonStyle` — string-backed enum (`Primary` / `Success` / `Danger`) for the Bot API 9.4 button color; `normalize()` validates a string/enum and `decorate()` merges the `style` / `icon_custom_emoji_id` fields into a button payload
- Optional `$style` (a `ButtonStyle` case or `'primary'` / `'success'` / `'danger'`) and `$icon` (custom emoji id) trailing arguments on every button method of **both** `InlineKeyboard` and `ReplyKeyboard` (Bot API 9.4+); an unknown style throws `\InvalidArgumentException`

**Views**
- **No view component file opens with `<?php` any more** — one style across the whole view directory. `inline_keyboard.php`, `reply_keyboard.php` and `media.php` are now `eval`'d rather than `include`d, which is what makes the tag droppable; `text.php` and the single-media files were already tagless. A leading `<?php` is still stripped and accepted, so existing views keep rendering
- A syntax error in a keyboard/media component now raises `ViewInvalidException` naming the file instead of a fatal parse error, because `eval` turns it into a catchable `ParseError`. Trade-off: these files get no opcache entry and IDEs no longer syntax-highlight them
- `{{-- comment --}}` — comments in `text.php` and the single-media components, stripped before sending (together with the newline they sit on). A comment cannot contain its own `--}}`. This replaces the `<?php /* … */ ?>` header the `text.php` stub used to carry purely to hide its own documentation
- `BotResponse::renderInlineKeyboardComponent()` / `renderReplyKeyboardComponent()` / `renderMediaGroupComponent()` were three byte-identical bodies; they now delegate to one private `renderComponent()`

**View Helpers**
- Inline keyboard view helpers expanded to the full button API: `login_url()`, `switch_inline()`, `switch_inline_chosen()`, `switch_inline_chosen_chat()`, `copy_text()`, `pay()`, `callback_game()` (alongside the existing `button()`, `href()`, `web_app()`, `row()`)
- Every inline button helper and the reply `reply()` helper accept the same optional trailing `style:` / `icon:` attributes as the fluent builders (Bot API 9.4+)

**Services**
- `Services\MediaUploader` — uploads a local file or remote URL to Telegram and returns a reusable `file_id`; bound as `laragram.media` singleton; supports `photo`, `document`, `audio`, `video`, `voice`, `animation`, `video_note`, `sticker`
- `Services\TelegramErrorHandler` — maps Telegram API error responses to typed exceptions; provides `validateUserBeforeSend()` and `getUserStatus()` for checking user reachability
- `Enums\TelegramErrorCode` — int-backed enum for Telegram HTTP error codes (400–504) with `getDescription()`, `getDetailedDescription()`, `getRecommendedAction()`, `requiresUserDeactivation()`, and `requiresSpecialHandling()`

**Exceptions**
- `Exceptions\ExceptionHandler` — static utility (replaces `BotException`); `handle(\Throwable)` logs reportable exceptions and silences `AuthenticationException`, `BotBlockedException`, `UserDeactivatedException`, `ChatNotFoundException`; `isTerminal(\Throwable)` reports whether an exception means the user is unreachable (used by `ResponseDispatcher` to stop a multi-message batch)
- `Exceptions\BotBlockedException`, `UserDeactivatedException`, `ChatNotFoundException`, `TelegramApiException` — typed exceptions for Telegram API error conditions
- `Exceptions\AuthenticationException` — thrown when the sender cannot be authenticated

**Middleware**
- `Middleware\VerifyTelegramSecret` — validates the `X-Telegram-Bot-Api-Secret-Token` header using `hash_equals()`; empty configured secret → HTTP 500 (misconfiguration); wrong token → HTTP 401
- `Middleware\RateLimit` — per-user rate limiting via Laravel `RateLimiter` (falls back to IP)
- Log warning at boot when `security.verify_secret` is `true` but `LARAGRAM_WEBHOOK_SECRET` is not configured

**Queue & Scaling**
- Optional queue offload — when `laragram.queue.enabled` is `true`, `Laragram::index()` dispatches a `Jobs\ProcessTelegramUpdate` job carrying the raw update and answers the webhook with `OK 200` immediately; the routing and outbound Bot API calls then run on a queue worker instead of inside the web request. Disabled by default — behaviour is unchanged (fully synchronous)
- `Jobs\ProcessTelegramUpdate` — the queued update processor; rebuilds an `Illuminate\Http\Request` from the stored payload, rebinds it, forgets the request-scoped `laragram.auth` / `laragram.response` singletons so they re-resolve against this update (correct under long-running workers), then runs the same `Laragram::handle()` pipeline used synchronously. Implements `ShouldBeEncrypted`; sets `tries = 0` (retry forever, safe because `handle()` swallows every `Throwable` and back-pressure is self-clearing) and `timeout = 60`
- Job middleware — `WithoutOverlapping` keyed by the sender id (falling back to the unique `update_id` for senderless updates) serializes processing per user to avoid session races, and `RateLimited('laragram')` caps global throughput. Per-user ordering is mutual exclusion, not strict FIFO: run a single worker per queue when a station flow must never reorder
- `Laragram::handle()` — the synchronous processing pipeline, extracted from `index()` and shared by the webhook entry point and the queued job
- Named `laragram` rate limiter, registered in `LaragramServiceProvider::registerRateLimiter()` as `Limit::perSecond(config('laragram.queue.rate_limit'))` (default 25), keeping outbound traffic under Telegram's ~30 msg/sec limit; requires a shared cache store (Redis) to be accurate across workers
- `queue.enabled` / `queue.connection` / `queue.queue` / `queue.rate_limit` config keys (env: `LARAGRAM_QUEUE_ENABLED`, `LARAGRAM_QUEUE_CONNECTION`, `LARAGRAM_QUEUE_NAME`, `LARAGRAM_QUEUE_RATE_LIMIT`)

**Events**
- `Events\BotExceptionHandled($exception, $reportable, $terminal)` — fired by `ExceptionHandler::handle()` for **every** handled throwable, including the silenced user-unreachable ones (`$terminal = true`) that otherwise leave no trace. The observability seam for silently-handled failures (which never reach the `failed_jobs` table): bind a listener to push to metrics/alerting or to count product signals (e.g. how many users blocked the bot). Dispatch is guarded so a faulty listener can never re-throw out of `handle()`

**Database**
- Migration-stub indexes for fresh installs: `laragram_sessions (user_id, chat_id, thread_id, last_activity)` (backs `User::session()`), and `laragram_users.role` / `laragram_users.is_active` (back `scopeByRole()` / `scopeActive()` and admin/broadcast queries). Existing host apps must add these via their own `Schema::table()` migration
- `laragram_sessions.chat_id` (nullable) and `laragram_sessions.thread_id` (**NOT NULL default 0**, `0` = no topic) columns, with the unique key `(user_id, chat_id, thread_id)` that the `LogSession` upsert is keyed on. `thread_id` is non-nullable because SQL treats `NULL`s as distinct, which would defeat that unique key. **Upgrading apps must add both columns** (backfill `chat_id = uid`, `thread_id = 0`) and swap the unique key — without `thread_id` the upsert fails on every update
- `laragram_admins` table (admin-panel login) — `name` (nullable), `username` (unique), `password` (hashed), `remember_token`, timestamps; backs `Models\Admin`, rows created by `laragram:admin:create`

**BotResponse**
- `BotResponse::answer(string $text, bool $showAlert)` — sends `answerCallbackQuery`
- `BotResponse::edit(string $text, ?string $format)` — sends `editMessageText`
- `BotResponse::delete()` — sends `deleteMessage`
- `BotResponse::setUser(User $user)` — overrides the authenticated user in the response context
- `BotResponse::noPreview()` — a modifier (mutating, like `keyboard()`) suppressing the link preview card via `link_preview_options.is_disabled` (Telegram deprecated `disable_web_page_preview`, so the modern field is written). Restricted to the two methods that carry a link preview — `sendMessage` and `editMessageText` — and throws `\LogicException` before a content method or after one that carries none (`photo()`, `answer()`, `delete()`, a media `view()`), rather than sending a parameter Telegram ignores
- Content-entry methods (`text()`, `view()`, `photo()`, `answer()`, `edit()`, `delete()`, media methods, `action()`) return a **fresh** `BotResponse` instance (clone-on-entry), so several can be collected into an array for a multi-message reply even when built via the `BotResponse` facade (a shared singleton); modifier methods (`keyboard()`, `redirect()`, `setUser()`) still mutate and return the same instance
- `keyboard()` guard — throws `\LogicException` when called before a content method (`text()`, `view()`, etc.)
- Tolerates the absence of an authenticated Telegram sender — the constructor resolves the user best-effort (`null` when there is no incoming update), so broadcasts, the `laragram:broadcast` command and queue workers can build a response and name the recipient explicitly via `setUser()`
- Automatic escaping of `text` and `caption` fields for the active parse mode (HTML, MarkdownV2, Markdown); mark already-escaped content with `'_escaped' => true`

**BotRequest**
- `BotRequest::isUpdateType(string $type)` and `getUpdateType()` — read the detected event type from the matched route
- `BotRequest::message()`, `callbackQuery()`, `inlineQuery()` — return the correct update sub-object for their respective event types

**User Model**
- `User::hasRole(string|array $role)` — checks the `role` column
- `User::isAdmin()` — shorthand for `hasRole('admin')`
- `User::scopeByRole(Builder $query, string $role)` — query scope for filtering by role
- `User::activate()` / `deactivate()` — toggle `is_active` and `deactivated_at`
- `User::isActive()` — reads the `is_active` DB column (was previously reading `settings.active`)

**Artisan Commands**
- `laragram:poll` — starts long-polling; intended for development without a public URL
- `laragram:webhook:info` — calls `getWebhookInfo` and displays the current webhook state
- `laragram:route:list` — lists all registered bot routes with event, station, content, role (`*` = no restriction), and handler
- `laragram:route:match {event} {text} [--station=]` — debug command; shows which route would match a given event and text
- `laragram:session:prune` — deletes expired sessions
- `laragram:set-role {uid} {role}` — assigns a role to a user by their Telegram ID
- `laragram:admin:create {username?}` — creates (or resets the password of) an admin-panel login account (`--name`, `--password`, min 8 chars, prompted if omitted; password auto-hashed)
- `laragram:admin:delete {username}` — deletes an admin-panel login account
- `laragram:make:controller` — scaffolds a new bot controller
- `laragram:make:view` — scaffolds a new bot view
- `laragram:make:scene {name} [--steps=a,b]` — appends a scene (wizard) skeleton to the scenes file, creating it with the required imports if absent
- `laragram:scene:list` — lists all registered scenes with their steps and options

**Testing**
- `Testing\InteractsWithBot` — PHPUnit trait for feature-testing bot flows without HTTP; runs the full auth → router → session → **delivery** pipeline and captures the messages the bot actually sends
- `Testing\BotUpdateFactory` — factory for realistic Telegram update arrays; supports `message()`, `groupMessage()`, `topicMessage()`, `callbackQuery()`, `inlineQuery()`, `chosenInlineResult()`, `editedMessage()`, `channelPost()`, `preCheckoutQuery()`, `shippingQuery()`, `successfulPaymentMessage()`, `messageReaction()`. `message()` / `groupMessage()` / `callbackQuery()` take `chatType` and `threadId` params
- `Testing\RecordingBotAPI` — a `BotAPI` test double that records outbound calls instead of hitting Telegram; used by `InteractsWithBot` to capture sent messages
- Single-message assertions (inspect the first sent message): `assertBotRepliedWith()`, `assertBotRepliedText()`, `assertResponseContains()`, `getBotResponse()`
- Multi-message assertions: `assertBotRepliedTimes()`, `assertNthReplyWith()`, `assertNthReplyText()`, `getBotResponses()`
- Scene assertions: `assertInScene()`, `assertSceneStep()`, `assertSceneData()`, `assertNotInScene()`
- Shared assertions: `assertUserRedirectedTo()`, `assertNoResponse()`, `assertBotRepliedInThread(?int $threadId)` (null asserts the reply carries no forum topic)

**Config**
- `laragram.php` — new config file name (was `config.php`)
- `auth.session.model` / `auth.session.table` — session model class and table
- `auth.user.model` / `auth.user.table` — user model class and table
- `bot.languages` — array of supported language codes
- `telegram.username` — the bot's @username without `@` (env `LARAGRAM_BOT_USERNAME`); used to strip the `@botusername` suffix from commands in group chats
- `paths.route` / `paths.scenes` — bot route and scenes file names under `routes/`; may include a subdirectory, and the default layout keeps both together in `routes/laragram/` (`laragram/routes` and `laragram/scenes`). `Support\RouteFile` resolves and validates them (rejecting `..`, backslashes, and absolute paths)
- `scenes.cancel_commands` / `scenes.global_commands` — default cancel commands and commands that escape any scene
- `rate.max_attempts` / `rate.decay_seconds` — rate limiting parameters
- `security.verify_secret` — toggle for `X-Telegram-Bot-Api-Secret-Token` validation

**Other**
- `Support\UpdateType` — shared detection of the Telegram update type from a raw payload, used by both `Routing\Router` and `Scene\SceneManager`
- `Support\OutboundPayload` — shared extraction of the Bot API method name and parameters from a formed payload (stripping the `method` key and internal `_`-prefixed bookkeeping keys), used by both `Http\ResponseDispatcher` and `Broadcasting\PendingBroadcast`
- Laravel 13.x support (`illuminate/support: ^13.0`)
- Support for 9 additional Telegram update types: `channel_post`, `edited_channel_post`, `poll`, `poll_answer`, `my_chat_member`, `chat_member`, `chat_join_request`, `message_reaction`, `message_reaction_count`
- `BotAPI` PHPDoc `@method` annotations expanded from ~35 to ~70 Telegram API methods
- `Facades\BotAPI`, `Facades\BotAuth`, `Facades\BotRoute`, `Facades\BotResponse`, `Facades\BotScene` — registered facades
- `src/Examples/` removed (was incorrectly autoloaded as package code)

### Changed

- `laragram:install` and `laragram:publish` have distinct roles: `install` bootstraps the host app with **blank** route/scene files, config, migrations, and `.env` variables; `publish` layers the runnable demo on top — views, lang, demo controllers (`HelloController`, `OrderController`, `ExtrasController` — the last demos Stars payments via `/donate`, inline mode, and file receiving) — and **appends** the demo routes + the demo `order` scene idempotently (marker sentinels prevent duplication on a second run; `--force` overwrites instead)
- Bot route and scenes files now live together in `routes/laragram/` by default (`routes/laragram/routes.php` and `routes/laragram/scenes.php`); `paths.route` / `paths.scenes` accept a subdirectory and `laragram:install` scaffolds the folder. The publish targets follow the configured paths automatically
- PHP requirement raised to `^8.3` (minimum required by Laravel 13); Laravel requirement raised to `^12.0|^13.0`
- `BotAPI` replaced ~40 explicit wrapper methods with a single `__call()` magic proxy — all Telegram API methods work automatically
- `DatabaseAuthDriver` uses `updateOrCreate` atomically instead of `firstOrCreate` + `save()`; settings are merged (not replaced) on every request
- `LogSession` listener now uses `updateOrCreate` — station is correctly updated on every request, not only on first visit; database failures are logged via `try/catch` instead of crashing the event listener
- `LogSession` fires `CallbackFormed` event for both `database` and `array` drivers
- `BotAuth::authenticate()` is resolved lazily as a singleton closure — safe to override in tests before first resolution
- `BotResponse::escapeText()`: passing `null` as the parse mode now returns text unmodified; previously fell through to legacy Markdown escaping
- `RequestTransformer::extractNamedParams()`: argument splitting uses `preg_split('/\s+/', trim(...))` instead of `explode(' ', ...)` — handles extra whitespace in commands correctly
- `ExceptionHandler::handle()` no longer calls `response()->send()` directly; `Laragram::back()` is solely responsible for the HTTP response, eliminating the double-send
- `ExceptionHandler::handle()` now also emits the `Events\BotExceptionHandled` event for every handled throwable (logging behaviour is unchanged; the event is additive and a no-op without a listener)
- Responses are now delivered as outbound `BotAPI` calls via `Http\ResponseDispatcher`; the webhook body is always `response('OK', 200)` and no longer carries a message inline (the previous `response()->json($view)` webhook-reply path was removed) — each message is one outbound round-trip in exchange for a single uniform delivery path that also supports multiple messages
- `laragram:poll` now delivers controller responses through `ResponseDispatcher`; previously long-polling dispatched the route but never sent the reply
- `Routing\Router::prepareResponse()` accepts `mixed` (was `BotResponse|string|null`) so controllers and route closures can return an array of responses; response-type validation lives in `ResponseTransformer`
- `Laragram::bootstrap()` uses null-safe `$this->user?->settings?->get(...)` — guards against an unauthenticated user reaching the bootstrap phase
- `BotClient` retry loop removed — single attempt, immediate throw on failure
- `BotClient` business logic removed: `sendMessage()` with user validation, `getUserStatus()`, `isUserActive()` — use `TelegramErrorHandler` instead
- Lumen support removed (`isLumen()` check and `app->configure()` calls)
- `config/config.php` renamed to `config/laragram.php`
- `Support\FormRequest::setRequest()` renamed to `Http\RequestTransformer::build()`
- `BotResponse::user()` renamed to `setUser()`
- `BotRouter::findRoute()` — cryptic internal variable names (`$EF`, `$EC`, `$FS`, `$CD`, `$PD`, `$PEI`) replaced with descriptive ones
- User settings no longer store a redundant `active` key — only `language` is written on authentication
- Middleware stack order: `laragram.verify` → `laragram.auth` → `laragram.hook` → `laragram.throttle`
- `require-dev` updated: `laravel/framework ^12.60|^13.10` (the security-patched floors), `orchestra/testbench ^10.0|^11.0`, `phpunit/phpunit ^11.0|^12.0`
- Removed deprecated `stopOnFailure` attribute from `phpunit.xml` (removed in PHPUnit 11+; `false` is the default)

### Removed

- `BotRouteCollection.php`, `BotRouter.php` — replaced by `Routing\RouteCollection` and `Routing\Router`
- `Support\FormRequest.php`, `Support\FormResponse.php` — replaced by `Http\RequestTransformer` and `Http\ResponseTransformer`
- `Exceptions\BotException.php` — replaced by `Exceptions\ExceptionHandler`
- `BotResponse::user()` — replaced by `setUser()`
- `BotClient::sendMessage()`, `getUserStatus()`, `isUserActive()` — moved to `TelegramErrorHandler`

### Fixed

- `BotException.php` had a duplicate `AuthenticationException` class definition — removed
- All three `BotAuth` authentication paths incorrectly wrote `active=true` to user settings, silently masking the `is_active` DB column — fixed
- `BotRequest::message()` was always returning null for `message`/`edited_message`/`channel_post` events — now returns the correct update sub-object
- `FormResponse` now accesses `BotAuth::user()?->uid` with null-safe operator, preventing a null dereference when no user is authenticated
- `Middleware/CheckAuth`: `$user['is_bot']` changed to `$user['is_bot'] ?? false` — prevents an undefined-key warning on malformed payloads that omit the field
- `BotClient::setTimeout()` and `setConnectTimeout()` now throw `\InvalidArgumentException` when a value ≤ 0 is passed, instead of silently producing a broken cURL request
- `BotResponse` methods that accept a `$format` argument now throw `\InvalidArgumentException` for unrecognised parse modes (e.g. `'XML'`) instead of silently falling through to legacy Markdown escaping
- `BotClient` now forwards the outgoing `chat_id` / `user_id` to `TelegramErrorHandler` when an API call fails, so the typed exception (and the `BotExceptionHandled` listener that auto-deactivates unreachable users) carries the real recipient id instead of a placeholder `0`

### Security

- `VerifyTelegramSecret` validates the webhook secret token with `hash_equals()` to prevent timing attacks; empty configured secret now fails with HTTP 500 instead of silently accepting all requests
- `BotResponse::validateFormat()` — unknown `parse_mode` values are rejected early, preventing unescaped user text from being sent when an invalid format is accidentally passed
- `BotResponse::setPath()` rejects view names containing `..`, `/`, or `\` to prevent path traversal
- `config('laragram.paths.route')` validated against path traversal characters before building the route file path
- SSL certificate verification enabled in `BotClient`
- `Jobs\ProcessTelegramUpdate` implements `ShouldBeEncrypted` — the queued payload carries user PII (names, username, message text), so Laravel encrypts it at rest in the queue store with the app key and decrypts it on the worker

## [v1.6] (2025-05-16)

### Changed

- Update `composer` requirements

## [v1.5.14] (2024-05-15)

### Fixed

- Fixed bugs

## [v1.5.13] (2024-03-03)

### Fixed

- Fixed bugs

## [v1.5.12] (2024-03-03)

### Changed

- Update `composer` requirements

## [v1.5.11] (2023-02-07)

### Changed

- Change `settings` implementation in `User` model

## [v1.5.10] (2023-01-25)

### Changed

- Fix bugs and improved code

## [v1.5.9] (2022-12-23)

### Changed

- Fix bugs and improved code

## [v1.5.8] (2022-12-21)

### Changed

- Improved `BotClient`

## [v1.5.7] (2022-12-20)

### Added

- Added `sendMediaGroup` method in `BotAPI`

## [v1.5.6] (2022-12-16)

### Changed

- Improved `BotClient`
- Added client settings in `config.php`

## [v1.5.5] (2022-11-30)

### Changed

- Improved code

## [v1.5.4] (2022-11-29)

### Changed

- Improved code in `BotRouter`

## [v1.5.3] (2022-11-28)

### Added

- Added the ability to set array patterns in `contains` route

### Changed

- Extend model `User` for Laravel Authentication and Factories
- Fix bugs

## [v1.5.2] (2022-11-27)

### Added

- Added facade for `BotAPI`
- Added new API methods in `BotAPI`
- Added `BotRequest::validate()`

### Changed

- Delete facade for `BotClient`
- Extend basic bot example

## [v1.5.1] (2022-11-26)

### Added

- Added an indication of the content view method in `BotResponse::view()`

## [v1.5.0] (2022-11-25)

### Added

- Added customization auth in `config.php` and models
- Rewritten `Session` model and migration

### Changed

- Upgraded version PHP to ^9.0
- Replaced `language` column on table `laragram_user` to `settings`
- Improved code and fix bugs

## [v1.4.1] (2022-11-22)

### Added

- Added the ability to get parameters from a input
- Added `data()` in `BotRequest`

### Changed

- Improved code

## [v1.4.0] (2022-11-18)

### Added

- Added customization User model in `config.php`
- Added the ability to run callback functions in routes
- Added format configuration in `BotResponse::text()`
- Added the ability to «basic-text» return

### Changed

- Changed router method names:
    - bind -> get
    - catch -> contains
    - alias -> from
- Changed database table `laragram_sessions`
- Improved code

## [v1.3.0] (2022-11-12)

### Added

- Added path configuration for views and routes in `config.php`
- Added localization features in views
- Added `text()` in `BotResponse`
- Expanded functionality example chatbot

### Changed

- Optimized `config.php`
- Changed route specifications
- Improved code

### Fixed

- Fixed more bugs

## [v1.2.1] (2022-11-02)

### Changed

- Upgraded version PHP to ^8.0

## [v1.2.0] (2020-05-09)

### Fixed

- Fixed some errors

### Changed

- Improved code

## [v1.1.1] (2019-03-10)

### Added

- Added `ResponseEmptyException`

### Changed

- Renamed `Wekser\Laragram\Concerns\ApiMethods` to `BotApi` and moved in `Wekser\Laragram` directory
- Changed visibility `request()` in `Wekser\Laragram\BotClient` to `public`

## [v1.1.0] (2019-03-09)

### Added

- Added Telegram Bot API method wrappers
- Added new exceptions for `BotClient`

### Changed

- Add `Arr` aliases by default
- Changed loading configuration data when registering bindings in `LaragramServiceProvider`
- Update `Wekser\Laragram\BotClient`
- Removed `payload` column in `laragram_sessions` table
- Removed `Wekser\Laragram\Support\Authenticatable` trait

### Fixed

- Fixed PHPDoc comments

## [v1.0.4] (2019-02-22)

### Added

- Added `LaragramPublishCommand` in `Wekser\Laragram\Console`
- Added `laragram:publish` Artisan command

### Changed

- Changed `LaragramInstallCommand` in `Wekser\Laragram\Console`

## [v1.0.3] (2019-02-15)

### Added

- Added support for using authorization drivers `database` and `array`
- Added auth `driver` configuration option that defines where authorization data will be stored for each request
- Added `Aidable` trait to `Wekser\Laragram\Middleware\FrameHook`

### Changed

- Changed return response on basic text message in `home()` to `BotController`
- Removed view `resources\bot\home.php`
- Changed `Wekser\Laragram\Middleware\FrameHook` to auth `database` driver

## [v1.0.2] (2019-02-10)

### Added

- Added a package documentation to the [Wiki](https://github.com/wekser/laragram/wiki)
- Added possibility of returning a string from a route or controller as easy text message
- Added `ResponseInvalidException`

### Changed

- Replaced `get` on `input` and `input` on `query` methods in `BotRequest`

### Fixed

- Fixed and update PHPDoc descriptions

## [v1.0.1] (2019-02-08)

### Added

- Added Exceptions

### Changed

- Update work of adding route in collection

## [v1.0.0] (2019-02-05)

- This initial release