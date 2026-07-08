# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Is

Laragram is a Laravel package (namespace `Wekser\Laragram`) for building Telegram bots in a REST/MVC style. It is a **Composer library** — not a standalone Laravel app — so there is no `artisan` in the repo root.

- PHP ^8.3, Laravel ^12|^13
- Package is auto-discovered via `extra.laravel` in `composer.json`, which also registers short class aliases — `BotAPI`, `BotAuth`, `BotResponse`, `BotRoute`, `BotScene` — so `\BotRoute::get(...)` works in a host app equivalently to the fully-qualified `Wekser\Laragram\Facades\BotRoute` used throughout this doc. (`BotBroadcast` is deliberately **not** aliased — reach it only via the full `Wekser\Laragram\Facades\BotBroadcast` class.)

## Commands

```bash
# Run all tests (composer alias for the same command)
vendor/bin/phpunit
composer test

# Run a single test file
vendor/bin/phpunit tests/Unit/BotRouterTest.php

# Run a single test method
vendor/bin/phpunit --filter test_find_route_matches_command_without_station_requirement
```

There is no build or lint step. Tests live under `tests/` (PSR-4: `Wekser\Laragram\Tests\`). PHPUnit is configured via `phpunit.xml` at the repo root. Coverage source includes `src/` but excludes `src/Console` and `src/Examples`.

CI (`.github/workflows/tests.yml`) runs the suite on every push/PR to `master` across a matrix of PHP `8.3–8.5` × Laravel `12/13`, each against both `lowest` and `highest` Composer dependency versions — so a change must work at the advertised version floor, not just the latest patch. Framework/Testbench/PHPUnit are pinned per Laravel major (L12→framework ^12.60 + testbench ^10 + phpunit ^11, L13→framework ^13.10 + testbench ^11 + phpunit ^12). The framework floors are the security-patched releases (GHSA-5vg9-5847-vvmq) so CI never installs an affected version; `laravel/framework` is `require-dev` only.

## Artisan Commands (registered by the package in host apps)

| Command | Description |
|---|---|
| `laragram:install` | Bootstraps a host app: config, migrations, **blank** route + scene files, and `.env` variables |
| `laragram:publish` | Publishes the runnable demo: views, lang, demo controllers (`HelloController`, `OrderController`, `ExtrasController` — the last demos Stars payments / inline mode / file receiving), and **appends** demo routes + the demo `order` scene |
| `laragram:webhook:set` | Registers webhook with Telegram |
| `laragram:webhook:remove` | Removes the webhook |
| `laragram:getMe` | Calls `getMe`, displays bot info |
| `laragram:webhook:info` | Calls `getWebhookInfo`, displays current webhook state |
| `laragram:poll` | Starts long-polling (dev/testing without a public URL); auto-removes webhook first. Options: `--timeout=25`, `--limit=100`, `--once`, `--no-confirm` |
| `laragram:route:list` | Lists all registered bot routes |
| `laragram:session:prune` | Deletes expired sessions |
| `laragram:make:controller` | Scaffolds a new bot controller |
| `laragram:make:view` | Scaffolds a new bot view |
| `laragram:make:scene` | Appends a scene (wizard) skeleton to the scenes file (`--steps=a,b`) |
| `laragram:scene:list` | Lists all registered scenes with steps and options |
| `laragram:set-role {uid} {role}` | Assigns a role to a user by their Telegram ID (requires `database` driver) |
| `laragram:admin:create {username?}` | Creates (or resets the password of) an admin-panel login account; `--name=`, `--password=` (prompted if omitted, min 8 chars). Password auto-hashed by the model cast |
| `laragram:admin:delete {username}` | Deletes an admin-panel login account |
| `laragram:broadcast {message?}` | Mass-message the user base — view via `--view`, filter via `--role=*` / `--include-inactive`, `--dry-run`, `--no-confirm`; requires `database` driver |
| `laragram:route:match {event} {text}` | Debug: shows which route would match a given event + text (`--station=` optional) |

**`install` vs `publish` — how the demo is wired.** `install` lays down *blank* `routes/laragram/routes.php` and `scenes.php` (bare header comments). `publish` then layers the demo on top: it **appends** the demo routes to `routes.php` and the demo `order` scene to `scenes.php` (rather than skipping either) so the appended `/order` route — which enters `BotScene::enter('order')` — actually has its scene defined. Both appends are **idempotent** via marker sentinels (`// --- Demo routes (added by laragram:publish) ---` / `// --- Demo scene ... ---`): a second `publish` detects the marker and skips, so it never duplicates the demo block. `--force` on either overwrites the whole file with the demo stub instead of appending. When touching `LaragramPublishCommand::publishDemoStub()`, keep routes and scenes symmetric — an appended demo route with no matching demo scene is the exact bug this wiring exists to prevent (see `tests/Unit/Console/LaragramPublishCommandTest.php`).

## Architecture

### Request Lifecycle

Telegram sends a POST webhook to `/{prefix}/{secret}`. The middleware stack runs in this order:

1. **`laragram.verify`** (`VerifyTelegramSecret`) — validates `X-Telegram-Bot-Api-Secret-Token` header with `hash_equals()`; empty configured secret → **500**; wrong token in header → **401**
2. **`laragram.auth`** (`CheckAuth`) — rejects bot senders (`is_bot = true`) using `BotAuth::findFromInPayload()` to locate the sender across all update types
3. **`laragram.hook`** (`FrameHook`) — deduplicates updates via `update_id` uniqueness check (database driver only)
4. **`laragram.throttle`** (`RateLimit`) — per-user rate limiting via Laravel `RateLimiter` (falls back to IP); returns **429** with `retry_after` on excess

**Optional queue offload.** When `laragram.queue.enabled` is true, `Laragram::index()` does **not** process the update inline — it dispatches a `Jobs\ProcessTelegramUpdate` job carrying `$request->all()`, returns `OK 200` immediately, and the routing + outbound delivery run on a queue worker. The 4 middleware above still run synchronously in the webhook (they gate what gets queued — verified, non-bot, non-duplicate, rate-limited). The worker has no live HTTP request, so the job rebuilds an `Illuminate\Http\Request` from the stored payload, rebinds it (`app()->instance('request', …)`), and **forgets the request-scoped singletons** (`laragram.auth`, `laragram.response`) so they re-resolve against this update — important under long-running workers where those singletons would otherwise cache the first request's user. The job then calls `Laragram::handle()`, the same pipeline used synchronously. Config: `queue.enabled` / `queue.connection` (null = default; use Redis in prod) / `queue.queue` / `queue.rate_limit`. When disabled (default), behavior is unchanged — fully synchronous. **The job implements `ShouldBeEncrypted`** — the stored payload carries user PII (names, username, message text), so Laravel encrypts it at rest in the queue store with the app key and decrypts on the worker.

**Job middleware** (`ProcessTelegramUpdate::middleware()`): `WithoutOverlapping` keyed by the sender id (falling back to the unique `update_id` for senderless updates, so they don't all collide on one global lock) serializes processing per user — mutual exclusion that avoids session races; blocked updates are released back and retried. Note this is *not* strict FIFO: with multiple workers per queue, two updates from one user can still run out of order, so run a single worker per queue when strict station ordering matters. `RateLimited('laragram')` caps global throughput under Telegram's ~30 msg/sec outbound limit. The job also sets `public int $timeout = 60` as a safety net bounding a whole multi-message batch (each outbound call is already capped at 30s by `BotClient`). The named `laragram` limiter is registered in `LaragramServiceProvider::registerRateLimiter()` as `Limit::perSecond(config('laragram.queue.rate_limit'))` (default 25) — needs a shared cache store (Redis) to be accurate across multiple workers. **The job sets `public int $tries = 0` (retry forever):** both middleware apply back-pressure by *releasing* the job, so under the default `queue:work --tries=1` a throttled/overlapping update would otherwise be marked failed and dropped on its next reservation. Unlimited retries are safe here because `handle()` swallows every `Throwable` (no poison loop) and the back-pressure is self-clearing (overlap lock expires after 30s, the limiter drains).

`Laragram::index()` (synchronous path — also reused by the queue worker via `Laragram::handle()`) then:
1. Bootstraps locale from `$user->settings->get('language')`
2. Resolves the user's current station (state) from `laragram_sessions`
3. Calls `Routing\Router->dispatch()` → matches route → invokes controller or closure
4. Fires `CallbackFormed` event → `LogSession` persists the session via `updateOrCreate`
5. `Http\ResponseDispatcher` delivers every formed message as a separate **outbound** `BotAPI` call
6. Always returns `response('OK', 200)` — the webhook body never carries a message

**Message delivery (multiple messages per update).** A controller/closure may return a **single** response (`BotResponse|string`) **or an array/iterable of them** for a multi-message reply:

```php
return [
    BotResponse::view('greeting'),
    BotResponse::text('A follow-up tip'),
    BotResponse::photo($fileId, 'And a picture')->redirect('home'),
];
```

- `Http\ResponseTransformer` collects every item into `output['response']['views']` (a **list** of payloads — replaces the old single `output['response']['view']`).
- `Http\ResponseDispatcher::send()` calls each `BotAPI::{method}()` in order. The webhook HTTP response is always `OK 200`; nothing rides in its body. **One consequence: a message is always one extra outbound round-trip** (the old webhook-reply fast path is gone) in exchange for a single uniform delivery path.
- **Station/redirect is one per batch**, last-write-wins: the last response that calls `redirect()` sets the next station; if none do, it falls back to the route's current station.
- **Batch error handling:** a failed send is logged via `ExceptionHandler::handle()` and the batch continues — **unless** the error means the user is unreachable (`BotBlockedException`, `UserDeactivatedException`, `ChatNotFoundException`, `AuthenticationException` — i.e. `ExceptionHandler::isTerminal()`), in which case the remaining messages are skipped.
- **`PollCommand` uses the same `ResponseDispatcher`**, so long-polling now delivers controller responses (previously poll mode dispatched but never sent).
- **`BotResponse` content-entry methods return a fresh instance (clone-on-entry).** `text()`, `view()`, `photo()`, `answer()`, `edit()`, `delete()`, media methods and `action()` each return a NEW `BotResponse`, so `[BotResponse::text('a'), BotResponse::text('b')]` yields two distinct payloads even though the `BotResponse` facade resolves a shared singleton. Modifier methods (`keyboard()`, `redirect()`, `setUser()`) still mutate and return the same instance, so chaining works unchanged.

### Supported Telegram Update Types

| Event | Default `contains()` listener field |
|---|---|
| `message` / `edited_message` / `channel_post` / `edited_channel_post` | `text` |
| `callback_query` | `data` |
| `inline_query` | `query` |
| `chosen_inline_result` | `result_id` |
| `shipping_query` / `pre_checkout_query` | `invoice_payload` |
| `poll` | `question` |
| `poll_answer` | `option_ids` |
| `my_chat_member` / `chat_member` / `chat_join_request` | `from` |
| `message_reaction` | `user` |
| `message_reaction_count` | `reactions` |

### Routing System

Bot routes live in `routes/laragram/routes.php`. The injected `$collection` variable and the `BotRoute` facade are interchangeable:

```php
// Using the injected $collection variable
$collection->get('message')
           ->from('start')
           ->contains('/start')
           ->role('admin')
           ->call([Controller::class, 'method']);

// Using the static BotRoute facade (same result)
use Wekser\Laragram\Facades\BotRoute;

BotRoute::get('message')->contains('/start')->call([Controller::class, 'method']);
```

`BotRoute` is a static proxy set up by `RouteCollection::collectRoutes()` before the routes file is `require`d. Calling it outside that context throws `\RuntimeException`.

The DSL is on `Routing\RouteCollection`:

```php
$collection->get('message')           // Telegram event type
           ->from('start')            // optional: user must be at this station
           ->contains('/start')       // optional: content/command match
           ->role('admin')            // optional: user must have this role
           ->call([Controller::class, 'method']);
```

`Routing\Router::findRoute()` matching rules (all must pass):
- **event** — update type equals `$type` detected from the payload
- **station** — `from` is empty (any station) OR equals user's current station
- **role** — `role` is empty (any user) OR `$user->hasRole($roles)` returns `true`; unmatched role falls through to the next route (including fallback)
- **content** — `contains` is empty (any content), OR command prefix match, OR exact match, OR param-pattern match (`{name}`)

**Order matters — first match wins.** `findRoute()` iterates routes in declaration order and returns the *first* one that matches (`fallback` is the sole exception: it's remembered and only used if nothing else matched). A **station catch-all** — a route with `from('x')` and **no** `contains` — matches *any* message at station `x`, so it must be declared **below** every specific command route for that station. The demo (`src/Console/stubs/routes/laragram.stub`) puts all command routes (`/donate`, file demos, …) first and the `from('home')` echo route **last**; declaring the echo above `/donate` would make `/donate` echo instead of firing its handler while the user is at `home`.

`contains()` accepts a string or array of patterns. Patterns starting with `/` are commands; `{param}` placeholders extract positional args into `BotRequest::input('param')`.

`group()` supports shared `from` and `roles` constraints:

```php
$collection->group(function ($c) {
    $c->get('message')->contains('/users')->call([AdminController::class, 'users']);
    $c->get('callback_query')->call([AdminController::class, 'callback']);
}, from: 'admin_panel', roles: 'admin');
```

**Controllers are resolved via `app($class)`**, so constructor injection through Laravel's IoC container is fully supported.

**Route file name validation:** `config('laragram.paths.route')` (and `paths.scenes`) is resolved via `Support\RouteFile`, which **allows a subdirectory** (the default layout is `routes/laragram/routes.php` + `routes/laragram/scenes.php`) but rejects `..`, backslashes, and absolute paths so the file always resolves under `routes/`.

### Station (State Machine)

Each user has a "station" string in `laragram_sessions.station`. `BotResponse::redirect('next_station')` sets the next station written by `LogSession`. With `auth.driver = array`, station is always `'start'`.

### Group Chats

The bot works in private chats **and** in groups/supergroups. Three pieces make this work; **private (1-on-1) behaviour is unchanged** because in a private chat `chat.id == from.id`.

- **Commands with `@botusername`.** In groups Telegram appends the bot's username to commands (`/start@MyBot`). `Support\Command::stripMention()` normalises the command token before `Routing\Router::matchesPattern()` compares it. Set `config('laragram.telegram.username')` (`LARAGRAM_BOT_USERNAME`, no leading `@`) so only *your* bot's mention is stripped (`/start@OtherBot` won't match); when empty, any `@suffix` is stripped (works out of the box, but matches other bots' mentions too). Param routes (`/order {id}`) already worked — `RequestTransformer::extractNamedParams()` drops the whole command token (mention included) before extracting args.
- **Per-(user, chat) state.** The session key is the composite `(user_id, chat_id)` — each member keeps **independent** station + scene state in each chat (Alice's `/order` wizard in group A is separate from group B and from her private chat). The `chat_id` is derived **from the update payload** by `BotAuth::findChatInPayload()` (a pure static — never the request-scoped `BotAuth` singleton, which can be stale under a long-running queue worker), computed once in `Http\RequestTransformer::build()` and threaded through `output['update']['chat_id']`. `LogSession` keys the `updateOrCreate` on it; `Laragram::defineStation()` (and `PollCommand` / `InteractsWithBot`) read the session for that chat via `User::session(?int $chatId)`. Scenes inherit this for free (their state lives in the same session payload).
- **Outbound targeting.** `Http\ResponseTransformer::injectTelegramIds()` targets `chat.id` from the update (already correct for group replies); its fallback is now `output['update']['chat_id']` (the originating chat) instead of the sender's uid, so a reply with no explicit chat never DMs the member.

**Chat-type routing & introspection.**
- Route DSL: `->chat('group', 'supergroup', …)` restricts a route to chat types; `->inGroups()` (group+supergroup) and `->inPrivate()` are shortcuts. Empty (default) = any chat type, so existing routes are unaffected. `group(..., chatTypes: 'group')` sets it for a whole group. `Routing\Router::matchesChatType()` reads the chat type from the payload (`Router::$chatType`, set in `dispatch()`).
- `BotRequest`: `chatType()`, `isPrivate()`, `isGroup()` (group||supergroup), `isSupergroup()`, `isChannel()`. `chat()` resolves the chat from the object or the nested `message.chat` (callback_query).

**Setup gotchas (document for host apps):**
- **Group privacy mode** is ON by default in @BotFather: the bot only receives commands directed at it (`/cmd`, `/cmd@bot`), replies to its own messages, and @mentions. To receive *all* group messages, disable privacy via @BotFather (`/setprivacy` → Disable) and **re-add** the bot to the group.
- Reaction / `chat_member` updates arrive only when listed in `allowed_updates` on `setWebhook`/`getUpdates`.
- Route the bot being added/removed/promoted via the `my_chat_member` event (already supported).
- **Known limitation:** messages from anonymous group admins / on behalf of a chat carry `sender_chat` and no `from` user — they pass through as senderless and are not tied to a `User`; handle them explicitly if needed.

**Testing:** `BotUpdateFactory::groupMessage(text, chatId, userId, …)` builds a group update (chat.id ≠ from.id); `message()` / `callbackQuery()` gained a `chatType` param. See `tests/Unit/GroupChatTest.php` and `tests/Unit/GroupSessionIsolationTest.php`.

### Scenes (Wizards)

Scenes are a high-level multi-step dialog layer **above** the station router — for forms, surveys, onboarding. While a user is in a scene their station is the sentinel `@scene:<name>`; `Laragram::run()` (and `InteractsWithBot::botReceives()`) route those updates to `Scene\SceneManager` instead of `Routing\Router`. The current step + collected answers live in `laragram_sessions.payload['scene']`, persisted by `LogSession` from `output['scene']` — **no new table**. Scenes **require the `database` auth driver** (station/payload must survive across updates); `BotScene::enter()` throws `\RuntimeException` under the `array` driver.

Define scenes in `routes/laragram/scenes.php` (`config('laragram.paths.scenes')`, default `laragram/scenes`); the file is loaded once and statically cached by `SceneRegistry` (`flushCache()` in tests):

```php
use Wekser\Laragram\Facades\BotScene;

BotScene::define('order')
    ->step('size')
        ->ask('order.size')                                 // view name OR closure(SceneContext)
        ->rules(['required', 'in:S,M,L'])                   // Laravel rules; on failure the SAME ask repeats
    ->step('address')
        ->ask(fn ($ctx) => BotResponse::text("Address for {$ctx->get('size')}?"))
        ->rules(['min:5'])
        ->transform(fn ($v) => trim($v))                    // map raw answer before storing
    ->cancelOn('/cancel')                                   // default: config laragram.scenes.cancel_commands
    ->onCancel(fn ($ctx) => BotResponse::text('Cancelled')->redirect('start'))
    ->onComplete([OrderController::class, 'place']);         // [Controller, method] or closure; gets SceneContext
```

Enter a scene from a normal route handler — the handler returns the transition, `Router::prepareResponse()` hands off to `SceneManager::start()`:

```php
public function order(BotRequest $r) { return BotScene::enter('order'); }

public function place(SceneContext $ctx) {                  // onComplete handler
    Order::create($ctx->all());
    return BotResponse::view('order.done')->redirect('home');
}
```

Runtime (`SceneManager::continue()`): the step answer is read via `BotRequest::query()` (type-appropriate field — message text, callback data; override with `->using(closure)`), validated; **invalid → re-ask the same step**, valid → store (after `transform`) and advance, last step → run `onComplete`. A `cancelOn` command runs `onCancel` and exits. Redirect/scene-state is one-per-batch: mid-scene the station stays `@scene:<name>`; on completion/cancel the handler's `redirect()` wins, else falls back to `'start'`. A `ValidationException` is handled internally (re-ask, not an error); other throwables bubble to `ExceptionHandler` leaving scene state intact for a retry. Under `queue.enabled`, scenes run on the worker via the shared `Laragram::handle()`, and the job's `WithoutOverlapping` per-user lock serializes steps. `SceneContext`: `get()/all()/has()/request()/user()`. Testing: `assertInScene()`, `assertSceneStep()`, `assertSceneData()`, `assertNotInScene()`.

**Step/scene options (phase 2):**
- **Conditional steps** — `->when(fn (SceneContext $ctx) => bool)` skips a step (when entering, advancing, and going back) if the condition fails; `firstEligibleStep`/`nextEligibleStep`/`prevEligibleStep` honour it. If no step is eligible at entry, the scene completes immediately.
- **Back navigation** — `->allowBack('/back')` (opt-in; default disabled) returns the user to the previous **eligible** step and re-asks it; on the first step it re-asks the first.
- **Timeout** — `->timeout($minutes)` + optional `->onTimeout(handler)`; each persisted scene state carries an `at` timestamp, and a stale scene is reset (running `onTimeout` if set) before the answer is processed.
- **Global escape commands** — `config('laragram.scenes.global_commands')` (default `[]`, opt-in) lists commands that leave any scene and are re-dispatched through `Routing\Router` (`escape()`); scene state is always cleared.
- **Typed extractors** — `->expectText()/expectCallback()/expectContact()/expectLocation()/expectPhoto()` set the step's extractor (photo → largest size's `file_id`); equivalent to a `->using(closure)` reading `update.object.<field>`.
- **Custom error prompt** — `->onInvalid(view|closure)` shows a dedicated message on validation failure instead of re-asking the question (still stays on the step).

Scaffolding: `laragram:make:scene {name} --steps=a,b` appends a `BotScene::define()` block to the scenes file (creating it with imports if absent); `laragram:scene:list` tabulates registered scenes.

### Incoming Files (getFile + download)

The mirror image of `MediaUploader`: turn a file a user **sent** to the bot back
into bytes or a stored file. `MediaUploader` only uploads (local file/URL →
file_id); `MediaDownloader` closes the loop.

```php
public function receive(BotRequest $request, MediaDownloader $downloader)
{
    // Fluent handle off the request:
    $path  = $request->file()?->save('local', 'inbox/receipt.jpg');  // → stored path
    $bytes = $request->file()?->bytes();                              // → raw bytes

    // Or the service directly:
    $path = $downloader->save($request->fileId(), 's3', 'kyc/doc.pdf');
}
```

- **`Services\MediaDownloader`** (container alias `laragram.downloader`): `getFile(fileId): array` (Telegram File object), `download(fileId): string` (raw bytes), `save(fileId, ?disk, ?path): string` (streams to a Laravel Storage disk, returns the stored path — path defaults to Telegram's basename, disk to `config('laragram.downloads.disk')`). Fetches from `api.telegram.org/file/bot<token>/<file_path>` via the `Http` client.
- **Security.** The download URL host is asserted to be `api.telegram.org` and the Telegram-supplied `file_path` is rejected if it contains `..` or a URL scheme (no SSRF). `config('laragram.downloads.max_size')` caps the byte size (default 20 MB, matching Telegram's getFile limit; 0 disables). A missing `file_path` (file too big/expired) throws.
- **`BotRequest::fileId(): ?string`** — extracts the file_id from the incoming update across the common media fields (photo → **largest** size; document/video/audio/voice/animation/video_note/sticker), precedence document-first. **`BotRequest::file(): ?IncomingFile`** returns a `Support\IncomingFile` handle (`id()` / `bytes()` / `save(?disk, ?path)`) that defers to `laragram.downloader`; both return null when the update carries no file.
- **Config** `downloads.disk` (`LARAGRAM_DOWNLOADS_DISK`, default `local`) and `downloads.max_size` (`LARAGRAM_DOWNLOADS_MAX_SIZE`, default 20 MB).

### Inline Mode (answerInlineQuery)

First-class support for answering `inline_query` updates — the `@bot query`
results shown inline in any chat.

```php
use Wekser\Laragram\Telegram\Inline\InlineResults;
use Wekser\Laragram\Facades\BotResponse;

return BotResponse::inlineResults(
    InlineResults::make()
        ->article('1', 'Say hello', 'Hello there!')            // sends text when picked
        ->photo('2', 'https://ex.com/p.jpg', title: 'A photo')
        ->cachedPhoto('3', $fileId)                            // by cached file_id
        ->cache(300)->personal()->nextOffset('20')             // answer-level options
);
```

- **`Telegram\Inline\InlineResults`** — fluent builder (mirrors `MediaGroup`/`Invoice`). Result methods: `article()`, `photo()`, `gif()`, `video()`, `document()`, `cachedPhoto()`, `sticker()` (file_id), and `raw(array)` for any other InlineQueryResult type. Each accepts an optional `reply_markup` (InlineKeyboard array) and captions default `parse_mode` to HTML only when a caption is present. Answer-level: `cache(int)` (cache_time), `personal()` (is_personal), `nextOffset(string)` (pagination), `button(text, startParameter?, webApp?)` (InlineQueryResultsButton). `toArray()` validates unique ids and the 50-result cap → `\InvalidArgumentException` / `\OverflowException`. Text/captions are passed **verbatim** (like the keyboard builders) — write HTML or pre-escape yourself.
- **`BotResponse::inlineResults(InlineResults|array)`** → `answerInlineQuery` (clone-on-entry).
- **`Http\ResponseTransformer`** injects `inline_query_id` from the update object's `id` (early-return, **no** `chat_id`), like the payment answer methods.
- **`BotRequest::chosenInlineResult()`** — the `chosen_inline_result` object (post-selection analytics; requires inline feedback enabled with @BotFather). Routes via the already-present `chosen_inline_result` event (listener `result_id`).
- **Testing:** `BotUpdateFactory::inlineQuery()` (existing) and `chosenInlineResult()` drive `InteractsWithBot` flows.

### Payments (Invoices + Telegram Stars)

First-class support for the full Telegram payment lifecycle — send an invoice,
answer the pre-checkout / shipping steps, handle the completed payment, refund.
Works for both fiat (Telegram Payments 2.0) and **Telegram Stars** (`XTR`).

```php
use Wekser\Laragram\Telegram\Payments\Invoice;
use Wekser\Laragram\Facades\BotResponse;

// 1. Send an invoice (Stars) — chat_id is injected automatically.
return BotResponse::invoice(
    Invoice::make()->title('Pro')->description('1 month')->payload('sub_42')->stars(500, 'Pro access')
)->keyboard(InlineKeyboard::make()->pay('Pay ⭐500')->toArray());

// Fiat variant: ->currency('USD')->price('Item', 1998)->providerToken(...)->flexible()
```

- **`Telegram\Payments\Invoice`** — fluent builder (mirrors `Telegram\Media\MediaGroup`):
  `title()/description()/payload()/currency()/price(label, minorUnits)/providerToken()`,
  optional `photo()/needName()/needPhoneNumber()/needEmail()/needShippingAddress()/flexible()/maxTip()/suggestedTips()/startParameter()/providerData()`.
  `->stars(int $amount, string $label)` is the Stars shortcut (currency `XTR`, empty provider token, one whole-number price).
  `toArray()` validates required fields (`title`/`description`/`payload`/`prices`, plus a provider token for fiat and **exactly one** price for Stars) → `\InvalidArgumentException`. Fiat falls back to `config('laragram.payments.currency'|'provider_token')` when unset. **Amounts are in the currency's smallest unit** (cents; whole stars for `XTR`).
- **`BotResponse` payment helpers** (clone-on-entry like `answer()`/`edit()`):
  - `invoice(Invoice|array)` → `sendInvoice`
  - `approveCheckout()` / `declineCheckout(string $reason)` → `answerPreCheckoutQuery` (ok true/false)
  - `approveShipping(array $options)` / `declineShipping(string $reason)` → `answerShippingQuery`
- **`Http\ResponseTransformer` id injection.** `answerPreCheckoutQuery`/`answerShippingQuery` get their `pre_checkout_query_id`/`shipping_query_id` auto-injected from the update object's `id` (analogous to `callback_query_id`); these two methods carry **no** `chat_id`.
- **`BotRequest` accessors:** `preCheckoutQuery()`, `shippingQuery()`, `successfulPayment()` (the last reads `message.successful_payment`).
- **Routing the completed payment** — `successful_payment` is a field on a `message` update, not its own type, so route it via the listener override (no router change): `BotRoute::get('message', 'successful_payment')->call(...)`.
- **`Services\Payments`** (container alias `laragram.payments`) — outbound actions that are direct API calls, not webhook responses: `invoiceLink(Invoice|array): string` (`createInvoiceLink`) and `refund(int $userId, string $chargeId): bool` (`refundStarPayment`).
- **Config** `payments.provider_token` (`LARAGRAM_PAYMENT_PROVIDER_TOKEN`) and `payments.currency` (`LARAGRAM_PAYMENT_CURRENCY`, default `USD`) — fiat defaults only; Stars ignore both.
- **Completed-payment event (phase 2).** The processing pipeline (`Laragram::capturePayment()`, run in the shared `handle()` so it covers the sync **and** queued paths) detects `message.successful_payment` and fires **`Events\PaymentReceived($user, $payment)`** — **independently of routing**, so a host can grant the entitlement from one listener without wiring a route. Accessors: `invoicePayload()`, `chargeId()`, `totalAmount()`, `currency()`, `isStars()`. Guarded so a listener error never breaks processing.
- **Payment history (opt-in).** The bundled `Listeners\RecordPayment` (bound to `PaymentReceived`) persists each payment to `laragram_payments` (`Models\Payment`) via `updateOrCreate` keyed on `telegram_payment_charge_id` — **idempotent** under redelivery. Gated by `config('laragram.payments.store')` (default `false`; needs the `database` driver and the published `create_laragram_payments_table` migration); exception-safe. The `PaymentReceived` event fires regardless of this flag.
- **`Services\Payments::starTransactions(offset, limit)`** — the bot's Stars statement (`getStarTransactions`).
- **Config:** `payments.store` (`LARAGRAM_PAYMENTS_STORE`, default `false`) and `payments.table` (default `laragram_payments`).
- **Testing:** `BotUpdateFactory::preCheckoutQuery()`, `shippingQuery()`, `successfulPaymentMessage()` build the payment updates for `InteractsWithBot` flows.

### Message Reactions (setMessageReaction)

First-class support for reactions: routing `message_reaction` / `message_reaction_count` updates and reacting to messages.

```php
BotRoute::get('message_reaction')->call(fn (BotRequest $r) => BotResponse::react('❤️'));

BotResponse::react('👍');                 // react to the triggering message
BotResponse::react(['❤️', '🔥'], big: true);
BotResponse::react([]);                   // clear the bot's reaction
BotResponse::react([['type' => 'custom_emoji', 'custom_emoji_id' => '...']]); // raw ReactionType passthrough
```

- **`BotResponse::react(string|array $reaction, bool $big = false)`** → `setMessageReaction` (clone-on-entry). Emoji strings become `['type' => 'emoji', 'emoji' => …]`; array items are passed verbatim (custom_emoji/paid types); empty array removes the reaction.
- **`Http\ResponseTransformer`** injects `message_id` for `setMessageReaction` (from `message.message_id` or the update object's top-level `message_id` — the `message_reaction` object carries both `chat` and `message_id` at its top level, so `chat_id` injection works too). So `react()` works both from a `message_reaction` handler and from a normal `message` handler (reacts to the incoming message).
- **`BotRequest::messageReaction()`** — the MessageReactionUpdated object (`chat`, `message_id`, `user` or `actor_chat`, `old_reaction`, `new_reaction`).
- **Sender handling:** `message_reaction.user` is found by `BotAuth::findFromInPayload()` (same `user` branch as `poll_answer`). `BotAuth::isSenderlessPayload()` also passes anonymous reactions (`actor_chat`, no `user`) and all `message_reaction_count` updates through `CheckAuth`.
- **Webhook note:** Telegram only delivers reaction updates when `allowed_updates` explicitly includes them — pass it in `setWebhook`/`getUpdates`.
- **Testing:** `BotUpdateFactory::messageReaction(new, old, …, anonymous: true)` builds the update.

### Broadcasting (Mass Messaging)

Push one message to many users at once (announcements, promos, downtime notices) via the
`Facades\BotBroadcast` facade (container alias `laragram.broadcast`, `Broadcasting\Broadcaster`)
or the `laragram:broadcast` command. **Requires the `database` auth driver** (there are no
persisted users under `array`) — the command guards for this.

```php
use Wekser\Laragram\Facades\BotBroadcast;

BotBroadcast::text('We are back online!')->send();                 // raw text to all active users
BotBroadcast::view('news.release', ['version' => '2.0'])           // a view, rendered PER recipient
    ->role(['admin', 'moderator'])                                 // optional role filter (whereIn)
    ->includeInactive()                                            // optional: also deactivated users
    ->query(fn ($q) => $q->where('created_at', '>', now()->subMonth()))  // arbitrary extra constraint
    ->send();

// Full BotResponse — formatting + keyboard + media, anything a reply can carry:
BotBroadcast::message(
    BotResponse::photo($fileId, 'Launch day!')->keyboard(InlineKeyboard::make()->href('Read more', $url)->toArray())
)->send();
```

- `Broadcaster::view()` / `text()` / `message()` each return a fresh `Broadcasting\PendingBroadcast`
  (clone-on-entry pattern, like `BotResponse`), so the shared singleton never leaks recipient filters
  between broadcasts.
- Content is a **serializable spec** (`['type' => 'view'|'text'|'payload', ...]`).
  - `view` / `text` are rendered **per recipient** by `Broadcasting\BroadcastRenderer`: it sets the
    translator locale from the user's `settings['language']` (mirroring `Laragram::bootstrap()`), builds a
    `BotResponse` with `setUser($recipient)`, and injects `chat_id = $user->uid` (a broadcast has no
    incoming update, so nothing else sets it).
  - `message(BotResponse $r)` stores the **already-built** `$r->contents` (a plain, queue-safe array
    carrying method/keyboard/media/`_escaped`) as a `payload` spec. It is rendered **once** at compose
    time — **not** re-localized per recipient — so use `view()` when you need per-user language. The
    renderer's `payload` arm returns it verbatim and only injects `chat_id`. `message()` throws
    `\InvalidArgumentException` on an empty `BotResponse` (no content method called).
- **Delivery:** `PendingBroadcast::send()` iterates recipients with `chunkById(config('laragram.broadcast.chunk_size'))`.
  When `queue.enabled`, it dispatches one `Jobs\SendBroadcastMessage` per recipient onto the configured
  connection/queue (throttled by the same `laragram` rate limiter as incoming updates). Otherwise it sends
  synchronously, pausing `config('laragram.broadcast.sync_delay_ms')` between sends (default 40ms ≈ 25/sec).
  Returns a `Broadcasting\BroadcastResult` (`total` / `sent` / `failed`, or `queued` for the queue path).
- **Auto-deactivation of unreachable users.** `Listeners\DeactivateUnreachableUser` is bound to the
  existing `Events\BotExceptionHandled` event, so the **first time any send** (broadcast *or* a normal
  reply) fails with a terminal/unreachable error (`BotBlockedException` / `UserDeactivatedException` →
  `getUserId()`, `ChatNotFoundException` → `getChatId()`), the matching `User::deactivate()` runs and
  future broadcasts skip them. Gated by `config('laragram.broadcast.deactivate_unreachable')` (default
  `true`), no-op under the `array` driver, and exception-safe (never breaks the swallow contract).
- `laragram:broadcast {message?} {--view=} {--data=} {--role=*} {--include-inactive} {--dry-run} {--no-confirm}`:
  requires exactly one of `message` / `--view`, prints a recipient count on `--dry-run`, confirms before
  sending unless `--no-confirm`. `--data=` is a JSON object of template data for `--view` (invalid JSON
  fails the command).
- **View discovery** (`Broadcasting\ViewCatalog::all()`) enumerates on-disk view directories under
  `resources/{paths.views}` that contain a renderable component (`text.php` or a media component),
  dot-notated and sorted — used to populate the admin panel's view picker.

### Admin Panel (bundled web dashboard)

A server-rendered admin dashboard for the bot's user base — metrics, users &
roles, sessions, and a broadcast launcher. Self-contained: its own routes +
Blade views with inline CSS (theme-aware), **no build step and no external
dependencies** (Horizon/Telescope model). **Requires the `database` auth driver.**

- **Mount & config.** Routes are registered in `LaragramServiceProvider::registerAdminPanel()` (called from `boot()`), loaded from `routes/admin.php`, gated purely on `config('laragram.admin.enabled')`. It mounts at `config('laragram.admin.path')` (default `laragram/admin`) with the `config('laragram.admin.middleware')` group (default `['web']`); the login/logout routes run under that group **only**, while the panel routes add the `Admin\Middleware\Authorize` gate on top. Blade views live in `resources/views/admin/`, registered via `loadViewsFrom(..., 'laragram')` → referenced as `view('laragram::admin.*')`.
- **Access control — login page backed by `laragram_admins`.** The panel is protected by a **session login** against the `laragram_admins` table (`Models\Admin`), via a dedicated `laragram_admin` session guard. `registerAdminPanel()` calls `registerAdminGuard()`, which injects a `session` guard + `eloquent` provider into `config('auth.*')` at boot — so **host apps need no `config/auth.php` edits**. `Admin\Controllers\AuthController` handles `show`/`login`/`logout` (`Auth::guard('laragram_admin')->attempt()`, session regenerate on login/invalidate on logout). Create accounts with `laragram:admin:create` (password auto-hashed by the model's `hashed` cast). `Admin\Middleware\Authorize` resolves in order: (1) if a `viewLaragram` Gate ability is defined it **decides** (escape hatch to reuse the host app's own web auth; a denying Gate is a hard **403**); (2) otherwise an authenticated `laragram_admin` passes, and an unauthenticated visitor is **redirected to the login page** (`redirect()->guest()`, so `redirect()->intended()` returns them after login). The guard/model/table names are configurable via `admin.guard` / `admin.model` / `admin.table`. **The `POST login` route carries `throttle:5,1`** (5 attempts/min/IP → **429**) as a brute-force guard on the sole unauthenticated endpoint; the `throttle` alias is Laravel's own, so no host-app wiring is needed.
- **Pages / routes** (route names `laragram.admin.*`): `dashboard` (metrics), `users` + `users.role` / `users.toggle` (POST — set role, activate/deactivate), `sessions` + `sessions.prune` (POST), `broadcast` + `broadcast.store` (POST — dry-run count or send). **`users.role` honours an optional role whitelist:** when `config('laragram.admin.roles')` is non-empty, `UserController::updateRole()` adds `Rule::in(...)` so only those role names are assignable (a typo is rejected instead of silently creating a dead role no route matches); empty (the default) allows any free-form role string — backward-compatible.
- **`Admin\Metrics`** computes the dashboard numbers (total/active/inactive users, new today/week, role breakdown, active sessions) — read-only, all derived from the DB (no tracking table). "Inactive" doubles as the blocked/unreachable count (auto-deactivation writes `is_active = false`).
- **Reuse.** Users page uses `User` scopes/`activate()`/`deactivate()`; sessions page uses the `Session` model + the same prune rule as `laragram:session:prune`; the broadcast composer drives the existing `Broadcaster`/`PendingBroadcast` (dry-run = `->count()`, send = `->send()`), so it honours the queue/sync path. The composer offers a **Text** or **View** mode: View mode picks an on-disk view from `Broadcasting\ViewCatalog::all()` (validated against that whitelist) plus an optional JSON data field, so an operator can send a fully-formatted view (buttons/media from its component files, localized per recipient) — not just plain text. Mode defaults to `text`, so a form/caller without `content_type` behaves exactly as before.

### Namespace Structure

```
src/
├── Admin/                    # bundled web admin panel (requires database driver)
│   ├── Metrics.php           # dashboard aggregates (users/sessions), read-only
│   ├── Middleware/Authorize.php  # gates access: viewLaragram Gate → laragram_admin login (redirects to login page)
│   └── Controllers/          # Auth (login/logout) / Dashboard / User / Session / Broadcast controllers
│                             # (Blade views in resources/views/admin incl. login.blade.php, routes in routes/admin.php)
├── Routing/
│   ├── Route.php             # Immutable Value Object (readonly props)
│   ├── RouteCollection.php   # Fluent route DSL + file loader
│   └── Router.php            # dispatch(), findRoute(), matching helpers
├── Scene/
│   ├── Scene.php             # Scene definition (steps, onComplete/onCancel, cancel commands)
│   ├── Step.php              # One step: ask/rules/messages/transform/using (fluent, delegates scene-level calls back to Scene)
│   ├── SceneContext.php      # Passed to prompts/handlers: get()/all()/request()/user()
│   ├── SceneTransition.php   # Marker returned by BotScene::enter()
│   ├── SceneRegistry.php     # Lazy loader + static cache of scenes file (flushCache() for tests)
│   └── SceneManager.php      # Runtime: start()/continue(); produces Router-shaped output (container alias: laragram.scene)
├── Broadcasting/
│   ├── Broadcaster.php       # mass-messaging entry point (alias laragram.broadcast); view()/text()/message() → PendingBroadcast
│   ├── PendingBroadcast.php  # fluent audience (role/includeInactive/query) + send(): queue-or-sync; count()
│   ├── BroadcastRenderer.php # content spec + recipient User → payload (view/text per-recipient locale; payload verbatim; chat_id = uid)
│   ├── ViewCatalog.php       # discovers on-disk broadcastable views (dot-notated, sorted) for the admin picker
│   └── BroadcastResult.php   # value object: total/sent/failed/queued
├── Jobs/
│   ├── ProcessTelegramUpdate.php # queued update processor (when queue.enabled); rebuilds Request from payload, re-resolves auth, calls Laragram::handle()
│   └── SendBroadcastMessage.php  # queued per-recipient broadcast delivery (when queue.enabled); RateLimited('laragram'), ShouldBeEncrypted, renders + sends via ResponseDispatcher
├── Http/
│   ├── RequestTransformer.php  # update array + route → BotRequest
│   ├── ResponseTransformer.php # controller response (single OR array) → output['response']['views'] list + chat_id injection
│   └── ResponseDispatcher.php  # sends each view payload as an outbound BotAPI call (container alias: laragram.dispatcher)
├── Auth/
│   ├── AuthDriverInterface.php   # resolveUser() + isActive()
│   ├── DatabaseAuthDriver.php    # persists User to DB on every request
│   └── ArrayAuthDriver.php       # in-memory User, no DB I/O
├── View/
│   ├── ComponentContext.php      # stack-based context for active component builders
│   ├── InlineKeyboardState.php   # accumulates buttons during inline_keyboard.php eval
│   ├── ReplyKeyboardState.php    # accumulates buttons during reply_keyboard.php eval
│   ├── MediaGroupState.php       # accumulates items during media.php eval
│   └── helpers.php               # global functions: button(), href(), web_app(), login_url(), switch_inline(), switch_inline_chosen(), switch_inline_chosen_chat(), copy_text(), pay(), callback_game(), reply(), row(), resize(), one_time(), photo(), video() — every button helper takes optional trailing style:/icon: attributes
├── Telegram/
│   ├── Keyboards/InlineKeyboard.php  # fluent InlineKeyboardMarkup builder
│   ├── Keyboards/ReplyKeyboard.php   # fluent ReplyKeyboardMarkup builder
│   ├── Keyboards/ForceReply.php      # fluent ForceReply markup builder
│   ├── Media/MediaGroup.php          # fluent sendMediaGroup payload builder
│   ├── Payments/Invoice.php          # fluent sendInvoice/createInvoiceLink builder (fiat + Stars)
│   └── Inline/InlineResults.php      # fluent answerInlineQuery results builder
├── Services/
│   ├── MediaUploader.php         # upload local file/URL to Telegram, return file_id (container alias: laragram.media)
│   ├── MediaDownloader.php       # download an incoming file: getFile()/download()/save() (container alias: laragram.downloader)
│   ├── Payments.php              # outbound payment actions: invoiceLink()/refund() (container alias: laragram.payments)
│   └── TelegramErrorHandler.php  # maps API errors to typed exceptions; validateUserBeforeSend(), getUserStatus()
├── Enums/
│   ├── TelegramErrorCode.php     # int-backed enum for Telegram HTTP error codes (400–504)
│   └── ButtonStyle.php           # string-backed enum for button colors (primary/success/danger); ButtonStyle::normalize()
├── Exceptions/
│   ├── ExceptionHandler.php   # static handler (NOT an exception class itself)
│   └── ...                    # typed exceptions hierarchy
├── Support/
│   ├── RouteFile.php          # validates/resolves config paths.route & paths.scenes under routes/ (rejects .., backslash, absolute)
│   ├── OutboundPayload.php    # method()/params() — strips internal '_'-prefixed keys; shared by ResponseDispatcher + PendingBroadcast so both send identically
│   ├── IncomingFile.php       # handle from BotRequest::file(): id()/bytes()/save() — defers to laragram.downloader
│   ├── Command.php            # stripMention(token, ?botUsername) — strips the @botusername suffix from group commands
│   └── UpdateType.php         # detect(array $update): string — single source of update-type detection, shared by Router + SceneManager
├── BotAPI.php        # __call() proxy → BotClient (all Telegram methods work automatically)
├── BotAuth.php       # extracts sender, drives AuthDriverInterface
├── BotClient.php     # cURL transport to api.telegram.org
├── BotRequest.php    # wraps parsed update; get(), input(), query(), validate()
├── BotResponse.php   # builds response payload; text(), view(), answer(), edit(), delete(), photo(), document(), etc.
└── Laragram.php      # entry-point controller
```

### Key Classes

| Class | Role |
|---|---|
| `Routing\Router` | Finds and executes matching route |
| `Routing\RouteCollection` | Fluent route builder; `require`d fresh per request |
| `Routing\Route` | Immutable value object representing one route |
| `Http\RequestTransformer` | Builds `BotRequest` from raw update + matched route via `build()` |
| `Http\ResponseTransformer` | Normalizes the controller response (single `BotResponse`/string OR array of them) into `output['response']['views']`; per-view auto-injects `chat_id`, `callback_query_id` (for `answerCallbackQuery`), and `message_id` (for `deleteMessage` / `editMessageText`); resolves one `redirect` per batch (last-write-wins) |
| `Http\ResponseDispatcher` | `send(array $views)` — delivers each view as an outbound `BotAPI::{method}()` call, in order; stops the batch on a terminal (user-unreachable) error. Used by both `Laragram` (webhook) and `PollCommand` (polling) |
| `BotClient` | cURL transport (token + method validation, SSL, logging) |
| `BotAPI` | `__call()` proxy to `BotClient`; use any Telegram method directly |
| `BotAuth` | Authenticates sender via `AuthDriverInterface` |
| `BotRequest` | `get('field')`, `input('param')`, `query()`, `message()`, `callbackQuery()`, `validate()` |
| `BotResponse` | `text()`, `view()`, `redirect()`, `answer()`, `edit()`, `delete()`, `photo()`, `document()`, `audio()`, `video()`, `voice()`, `animation()`, `sticker()`, `videoNote()`, `action()`, `invoice()`, `approveCheckout()`, `declineCheckout()`, `approveShipping()`, `declineShipping()`, `inlineResults()`, `react()` |
| `Telegram\Inline\InlineResults` | Fluent `answerInlineQuery` results builder; `article()`/`photo()`/`sticker()`/`raw()` + `cache()`/`personal()`/`nextOffset()`/`button()` |
| `Telegram\Payments\Invoice` | Fluent `sendInvoice`/`createInvoiceLink` params builder; `stars()` shortcut for Telegram Stars |
| `Services\Payments` | `invoiceLink()` (createInvoiceLink) + `refund()` (refundStarPayment); alias `laragram.payments` |
| `Facades\BotRoute` | Static proxy for `RouteCollection`; use inside `routes/laragram/routes.php` instead of `$collection` |
| `Facades\BotScene` | Facade for `Scene\SceneManager`; `define()` scenes in `routes/laragram/scenes.php`, `enter()` from a route handler |
| `Scene\SceneManager` | Scene runtime; `start()` / `continue()` produce Router-shaped output (alias `laragram.scene`) |
| `Facades\BotBroadcast` | Facade for `Broadcasting\Broadcaster` (alias `laragram.broadcast`); `view()` / `text()` → `PendingBroadcast`, then `->send()` |
| `Broadcasting\Broadcaster` | Mass-messaging entry point; `view()` / `text()` return a fresh `PendingBroadcast` |
| `Listeners\DeactivateUnreachableUser` | Bound to `BotExceptionHandled`; deactivates a user on a terminal/unreachable send error |
| `Events\PaymentReceived` | Fired for every completed payment (`successful_payment`); carries the user + payment, with accessors |
| `Listeners\RecordPayment` | Bound to `PaymentReceived`; persists to `laragram_payments` when `payments.store` is on (idempotent) |
| `View\ComponentContext` | Stack-based context shared between `BotResponse` renderer and global helper functions |
| `ExceptionHandler` | `handle(\Throwable)` — logs reportable exceptions, silences others |
| `Services\MediaUploader` | `upload(string $type, string $source, int $chatId): string` — uploads local file or URL, returns `file_id` |
| `Services\MediaDownloader` | `getFile()` / `download()` / `save()` — fetch an incoming file's bytes or store it; alias `laragram.downloader` |
| `Support\IncomingFile` | Handle from `BotRequest::file()`; `id()` / `bytes()` / `save(?disk, ?path)` |
| `Services\TelegramErrorHandler` | `handleError()` maps API error arrays → typed exceptions; `validateUserBeforeSend()` / `getUserStatus()` check DB |
| `Enums\TelegramErrorCode` | Int-backed enum (400–504); `getDescription()`, `getRecommendedAction()`, `requiresUserDeactivation()` |

### Views

Views are **directories** under `resources/laragram/` (dot-notation maps to subdirectories). Each component of a Telegram message lives in its own file:

| File | Role |
|---|---|
| `text.php` | Message text or media caption — template syntax with `{{ expr }}` (escaped) and `{!! expr !!}` (raw) |
| `photo.php` / `video.php` / `document.php` / `audio.php` / `voice.php` / `animation.php` / `sticker.php` / `video_note.php` | Single-line: file_id or URL — triggers the matching `send*` method |
| `media.php` | Album (sendMediaGroup) — call `photo()` / `video()` global helpers |
| `inline_keyboard.php` | InlineKeyboard — call `button()` / `href()` / `web_app()` / `row()` global helpers |
| `reply_keyboard.php` | ReplyKeyboard — call `reply()` / `row()` / `resize()` / `one_time()` global helpers |

Only one media component may be present per directory. `inline_keyboard.php` and `reply_keyboard.php` cannot coexist.

**Template syntax** in `text.php` — write plain text plus your own formatting markup; use interpolation for dynamic values:

```
Thank you for using <b>Laragram</b>!  ← static markup renders as-is (bold)
Hello, {{ $name }}!                   ← {{ }} value is auto-escaped (user data)
{!! __('start.body') !!}              ← {!! !!} value is emitted raw (trusted/pre-formatted)
Welcome, {{ $user->first_name }}!     ← $user is the authenticated User
```

Variables from `$data` are extracted into scope via `extract($data, EXTR_SKIP)`, so use `$name` directly (not `$data['name']`). `$user` is also available.

- `{{ expr }}` — value is **escaped** for the active parse mode. Use for untrusted/user data so it can't break formatting or inject markup.
- `{!! expr !!}` — value is emitted **raw, unescaped**. Use for trusted, already-formatted content such as translation strings (`{!! __('...') !!}`) that themselves contain `<b>` / `<i>` markup.
- **Static template text is never escaped** — write `<b>bold</b>` / `<i>italic</i>` directly in the file and it renders.

**Global view helpers** (registered in `src/View/helpers.php`) delegate to `ComponentContext` — they are only meaningful inside the matching component file:

```php
// inside inline_keyboard.php
button('Click me', 'action_1');                       // callback_data
href('Open site', 'https://example.com');             // url
web_app('Open Mini App', 'https://example.com/app');  // web_app
login_url('Sign in', 'https://example.com/auth');     // login_url (Telegram Login Widget)
switch_inline('Search here', 'query');                // switch_inline_query_current_chat
switch_inline_chosen('Share', 'query');               // switch_inline_query (pick a chat)
switch_inline_chosen_chat('Pick', 'q', allowUserChats: true); // switch_inline_query_chosen_chat
copy_text('Copy code', 'COUPON10');                   // copy_text (Bot API 7.11+)
pay('Pay');                                           // pay (invoices; first button)
callback_game('Play');                                // callback_game (first button)
row();

// Optional style/icon attributes (Bot API 9.4) — trailing params on EVERY button helper:
button('Delete', 'rm', style: 'danger');              // style: primary|success|danger
button('Confirm', 'ok', style: 'success', icon: '5368324170671202286'); // + custom emoji

// inside reply_keyboard.php
resize();
one_time();
reply('Option A'); reply('Option B');
row(); reply('Help');

// inside media.php
photo($data['photo_id'], caption: 'First photo');
video($data['video_id']);
```

**Naming convention for helpers vs class methods:**

| Action | View helper (snake_case) | InlineKeyboard method (camelCase) | ReplyKeyboard method (camelCase) |
|---|---|---|---|
| Callback button | `button()` | `button()` | — |
| URL button | `href()` | `href()` | — |
| Mini App button | `web_app()` | `webApp()` | — |
| Login Widget button | `login_url()` | `loginUrl()` | — |
| Switch inline (this chat) | `switch_inline()` | `switchInline()` | — |
| Switch inline (pick chat) | `switch_inline_chosen()` | `switchInlineChosen()` | — |
| Switch inline (chosen chat) | `switch_inline_chosen_chat()` | `switchInlineChosenChat()` | — |
| Copy-text button | `copy_text()` | `copyText()` | — |
| Pay button | `pay()` | `pay()` | — |
| Game button | `callback_game()` | `callbackGame()` | — |
| Reply button | `reply()` | — | `button()` |
| New row | `row()` | `row()` | `row()` |
| Resize | `resize()` | — | `resize()` |
| One-time | `one_time()` | — | `oneTime()` |

**Button `style` / `icon` (Bot API 9.4+):** every button helper and builder method (inline **and** reply) accepts two optional trailing params — `$style` (`Wekser\Laragram\Enums\ButtonStyle` enum or string `primary`/`success`/`danger`) and `$icon` (custom emoji id → `icon_custom_emoji_id`). They are merged into that single button only; both are omitted from the payload when `null`. Invalid `$style` throws `\InvalidArgumentException` (via `ButtonStyle::normalize()`). E.g. `button('Delete', 'rm', style: 'danger')` or `InlineKeyboard::make()->button('Ok', 'ok', ButtonStyle::Success)`.

**Auto-escaping:** In `text.php` (and media captions) **only `{{ }}` interpolated values are escaped** for the active parse mode — static template text and `{!! !!}` output are emitted verbatim. This lets view authors write formatting markup directly while user data stays safe. Do not manually escape `{{ }}` values — it will double-escape. The whole-string `text()` / `edit()` methods escape their entire argument (treated as raw user data); pass `$format = null` there to send already-formatted text.

**Default parse mode is `HTML`.** `text()`, `edit()`, `view()`, and the media methods default `$format` to `'HTML'`. HTML escaping (`htmlspecialchars`) only touches `< > & "`, so static prose and punctuation (`. ! , - * _`) are emitted verbatim — view authors write `<b>bold</b>` / `<i>italic</i>` directly while `{{ }}` user data is escaped so it can't inject tags. Legacy `'Markdown'` and `'MarkdownV2'` remain available by passing them explicitly; with MarkdownV2 any static markup/punctuation in the view must be hand-escaped.

**Format validation:** `text()`, `edit()`, `view()`, and media methods accept only `'HTML'`, `'MarkdownV2'`, `'Markdown'`, or `null` as `$format`. Any other value throws `\InvalidArgumentException`.

### Auth Drivers

`BotAuth` selects a driver at construction time; no `if/match` scattered elsewhere:

- `database` → `DatabaseAuthDriver` — uses `updateOrCreate(['uid' => ...], [...fields])`; settings are merged only when the language actually changed (avoids a second UPDATE on every request)
- `array` → `ArrayAuthDriver` — instantiates an in-memory `User` without any DB call

Only `'database'` and `'array'` are valid driver names. Any other value throws `\InvalidArgumentException` immediately at service-provider boot — fail-fast, no silent fallback.

`BotAuth::getDriver()` returns the driver name string. `BotAuth::getDriverInstance()` returns the `AuthDriverInterface` object. `BotAuth::findFromInPayload(array $payload): ?array` is a public static helper that extracts the sender `from` object from any update type — used by `CheckAuth` middleware and available standalone.

### Telegram Helpers

```php
// Inline keyboard
use Wekser\Laragram\Telegram\Keyboards\InlineKeyboard;

InlineKeyboard::make()
    ->button('Click me', 'action_1')
    ->href('Open site', 'https://example.com')
    ->webApp('Open Mini App', 'https://example.com/app')
    ->row()
    ->button('Row 2', 'action_2')
    ->toArray();   // → ['inline_keyboard' => [...]]

// Reply keyboard
use Wekser\Laragram\Telegram\Keyboards\ReplyKeyboard;

ReplyKeyboard::make()
    ->button('Option A')->button('Option B')
    ->row()->button('Help')
    ->resize()->oneTime()
    ->toArray();   // → ['keyboard' => [...], 'resize_keyboard' => true, ...]

ReplyKeyboard::remove();   // → ['remove_keyboard' => true]

// Force reply (prompts user to reply)
use Wekser\Laragram\Telegram\Keyboards\ForceReply;

ForceReply::make()->toArray();                                     // → ['force_reply' => true]
ForceReply::make()->placeholder('Enter your name…')->selective()->toArray();

// Media group
use Wekser\Laragram\Telegram\Media\MediaGroup;

$media = MediaGroup::make()
    ->photo('file_id_1', caption: 'First')
    ->video('file_id_2')
    ->toArray();   // pass as 'media' param to BotAPI::sendMediaGroup()
```

### BotResponse Helpers

```php
// Answer a callback query (answerCallbackQuery)
BotResponse::answer('Done!', showAlert: true);

// Edit the current inline message (editMessageText)
BotResponse::edit('Updated text');

// Delete the current message (deleteMessage)
BotResponse::delete();
```

**`keyboard()` guard:** calling `keyboard()` before a content method (`text()`, `view()`, etc.) throws `\LogicException`. Always set the message content first.

**`setUser()`** (formerly `user()`) — injects the authenticated `User` into a response context. The old `user()` name is removed.

### MediaUploader

`Services\MediaUploader` uploads a local file or remote URL to Telegram and returns the reusable `file_id`. It is bound as a singleton in the container under the `laragram.media` alias.

```php
use Wekser\Laragram\Services\MediaUploader;

$uploader = app(MediaUploader::class);

// From a local file path
$fileId = $uploader->upload('photo', '/path/to/image.jpg', $user->uid);

// From a remote URL
$fileId = $uploader->upload('document', 'https://example.com/file.pdf', $user->uid);

// Then use the file_id in a BotResponse
return (new BotResponse(config('laragram.paths.views')))->photo($fileId);
```

Supported types: `photo`, `document`, `audio`, `video`, `voice`, `animation`, `video_note`, `sticker`.

**`$source` is trusted server-side input — never pass unvalidated user input.** `resolveSource()` inspects the URL scheme: a schemeless value is treated as a local path (`is_file()` is only called on schemeless input, so a remote scheme can never trigger a network stat), and a URL is accepted **only** when its scheme is `http`/`https`. Other schemes (`file://`, `ftp://`, …) are rejected with `\InvalidArgumentException`.

**Architecture note:** `BotResponse` returns JSON to Telegram via the webhook response — it cannot upload files (multipart/form-data is incompatible with JSON). `MediaUploader` uses `BotAPI` to make a direct outbound HTTP call, then the returned `file_id` is passed to `BotResponse`. This two-step pattern is mandatory for local file uploads.

### Exception Handling

`ExceptionHandler` (formerly `BotException`) is a **static utility**, not an exception. It is called in `Laragram::run()`:

```php
try {
    $this->output = (new Router($this->station))->dispatch(...);
} catch (\Throwable $e) {
    ExceptionHandler::handle($e);
}
```

`handle()` only logs — it does **not** send an HTTP response. `Laragram::back()` returns `response('OK', 200)` when `$this->output` is empty, which is the natural outcome after an exception. Do not call `render()` or `send()` from inside the handler — it would produce a double-send.

Exceptions in `$dontReport` (`AuthenticationException`, `BotBlockedException`, `UserDeactivatedException`, `ChatNotFoundException`) are silenced; all others are logged via `app('log')->error()`. `TelegramErrorHandler` maps Telegram API error descriptions to these typed exceptions.

**Observability seam.** Because `handle()` swallows everything, silently-handled failures never reach the `failed_jobs` table. So `handle()` fires an `Events\BotExceptionHandled($exception, $reportable, $terminal)` event for **every** handled throwable — including the silenced user-unreachable ones (`$terminal = true`), which are otherwise invisible. Bind a listener to push to metrics/alerting (Sentry, StatsD, Horizon tags) or to count product signals like how many users blocked the bot. Listening is optional (no listener = near-zero-cost no-op); dispatch is guarded, so a faulty listener can never re-throw out of `handle()` and break the swallow contract.

**`BotClient` error contract (matters for any direct `BotAPI::*` caller):** `BotClient::processResponse()` returns `$decoded['result']` on success — for many methods this is a scalar (`deleteWebhook`/`setWebhook` → `true`), not an array. On API failure (`ok: false`) it **throws** a typed exception via `TelegramErrorHandler` — it does **not** return an error array. So callers must `try/catch` around the call and check the result shape; inspecting the return value for an `error_code` key is dead code. Console commands (`WebhookSetCommand`, `WebhookRemoveCommand`) follow this pattern: wrap the call in `try/catch`, verify `$response === true`, and return `self::SUCCESS` / `self::FAILURE` for correct exit codes.

**`BotClient` transport hardening.** `setTimeout()` / `setConnectTimeout()` reject anything outside `1..MAX_TIMEOUT` (300s) — a value that hangs a worker indefinitely is a config error, not a tunable. `buildCurlOptions()` re-applies `CURLOPT_SSL_VERIFYPEER = true`, `CURLOPT_SSL_VERIFYHOST = 2`, and `CURLOPT_MAXREDIRS = 3` **after** the union merge with caller-supplied `curlOptions`, so user options can never weaken TLS verification or open unbounded redirects (SSRF). Don't try to override these via `curlOptions` — they always win.

### Models & Database

- `laragram_users` — `uid` (unique), `first_name`, `last_name`, `username`, `settings` (JSON cast to `AsCollection`), `role` (string, default `'user'`, indexed), `is_active` (indexed), `deactivated_at`
- `laragram_sessions` — `user_id`, `chat_id` (nullable — the conversation the session belongs to; group support), `station`, `update_id` (unique), `payload`, `last_activity` (no timestamps); **unique `(user_id, chat_id)`** is the per-conversation upsert key, and composite index `(user_id, chat_id, last_activity)` backs `User::session(?int $chatId)`. In a private chat `chat_id == uid`. **Migration-stub changes only apply to fresh installs — existing host apps need their own migration to add `chat_id` (backfill `chat_id = uid`), swap the unique key to `(user_id, chat_id)`, and add the index.**
- `laragram_payments` (opt-in, phase-2 payments) — `user_id`/`uid` (nullable, indexed), `currency`, `total_amount`, `invoice_payload`, `telegram_payment_charge_id` (unique → idempotent), `provider_payment_charge_id`, `payload` (JSON), timestamps. Written by `Listeners\RecordPayment` only when `payments.store` is enabled; needs its published migration (`php artisan vendor:publish --tag=laragram-migrations`).

**Migration publishing.** `laragram:install` writes the base `users`/`sessions`/`admins` migrations directly into `database/migrations/` **with a `Y_m_d_His` timestamp prefix** (`LaragramInstallCommand::createMigrations()`, one-second offsets to keep order). The `laragram-migrations` `vendor:publish` tag publishes **only** the opt-in `laragram_payments` migration — with a publish-time timestamp prefix computed in `LaragramServiceProvider::boot()`. The tag must never re-expose users/sessions/admins: those already exist (timestamped) after `install`, and republishing them (formerly done with fixed, un-timestamped filenames) duplicated the tables. Guarded by `tests/Unit/Console/MigrationPublishTagTest.php`.
- `laragram_admins` (admin panel) — `name` (nullable), `username` (unique), `password` (hashed), `remember_token`, timestamps. Backs the admin-panel login (`Models\Admin`, a plain `Authenticatable` distinct from `Models\User`); rows are created by `laragram:admin:create`. Needs its published migration.
- `User::session()` returns the most recent session within the configured lifetime
- `User::activate()` / `deactivate()` toggle `is_active` + `deactivated_at`
- `User::hasRole(string|array $role)` — checks the `role` column; `isAdmin()` is a shorthand for `hasRole('admin')`
- `User::scopeByRole(string $role)` — query scope for filtering by role
- `User::scopeActive()` / `scopeInactive()` — query scopes for filtering by `is_active`
- The `role` column is **not** written by the auth drivers — it must be set manually (e.g. via a migration or admin panel)

### Configuration (`config/laragram.php`)

| Key | Purpose |
|---|---|
| `telegram.token` | Bot token (`LARAGRAM_BOT_TOKEN`) |
| `telegram.username` | Bot @username without `@` (`LARAGRAM_BOT_USERNAME`); strips the `@botusername` suffix Telegram appends to commands in groups. Empty → strips any `@suffix` |
| `telegram.prefix` / `telegram.secret` | Webhook URL segments |
| `auth.driver` | `database` or `array` |
| `queue.enabled` | Defer update processing to a queue worker (`LARAGRAM_QUEUE_ENABLED`, default `false`) |
| `queue.connection` / `queue.queue` | Queue connection (null = default) and queue name for `ProcessTelegramUpdate` |
| `queue.rate_limit` | Max update jobs/sec across workers (`LARAGRAM_QUEUE_RATE_LIMIT`, default 25); enforced by the `laragram` named limiter (also throttles queued broadcasts) |
| `broadcast.chunk_size` | Recipients loaded per `chunkById` batch when broadcasting (`LARAGRAM_BROADCAST_CHUNK_SIZE`, default 500) |
| `broadcast.sync_delay_ms` | Pause between sends on the synchronous broadcast path (`LARAGRAM_BROADCAST_SYNC_DELAY_MS`, default 40 ≈ 25/sec) |
| `broadcast.deactivate_unreachable` | Mark a user inactive on a terminal send error (`LARAGRAM_BROADCAST_DEACTIVATE_UNREACHABLE`, default `true`) |
| `auth.session.lifetime` | Minutes before session expires (default 10080 = 7 days) |
| `paths.route` | Routes filename under `routes/` |
| `paths.scenes` | Scenes (wizards) filename under `routes/` (default `laragram/scenes`) |
| `scenes.cancel_commands` | Default commands that abort any scene (default `['/cancel']`) |
| `scenes.global_commands` | Commands that escape any scene and route normally (default `[]`) |
| `paths.views` | Views directory under `resources/` |
| `auth.session.model` / `auth.session.table` | Session model class and table name |
| `auth.user.model` / `auth.user.table` | User model class and table name |
| `bot.languages` | Array of supported language codes (e.g. `['en', 'ru']`) |
| `rate.max_attempts` / `rate.decay_seconds` | Rate limiting |
| `security.verify_secret` | Toggle `X-Telegram-Bot-Api-Secret-Token` check |
| `payments.provider_token` / `payments.currency` | Fiat invoice defaults (`LARAGRAM_PAYMENT_PROVIDER_TOKEN` / `LARAGRAM_PAYMENT_CURRENCY`); Stars ignore both |
| `payments.store` / `payments.table` | Persist payments to history (`LARAGRAM_PAYMENTS_STORE`, default off) + its table name |
| `downloads.disk` / `downloads.max_size` | Incoming-file download disk + byte cap (`LARAGRAM_DOWNLOADS_DISK` / `LARAGRAM_DOWNLOADS_MAX_SIZE`) |
| `admin.enabled` / `admin.path` / `admin.middleware` / `admin.guard` / `admin.model` / `admin.table` | Admin panel toggle, URL prefix, middleware group, and the session guard / Eloquent model / table backing the login page (`LARAGRAM_ADMIN_ENABLED` / `LARAGRAM_ADMIN_PATH`) |
| `admin.roles` | Optional whitelist of role names the Users page may assign (default `[]` = any free-form role); when set, `users.role` validates via `Rule::in(...)` |

### BotRequest — update-type helpers

`BotRequest::isUpdateType(string $type)` and `getUpdateType()` both read from `route('event')`, which is populated by `RequestTransformer` **after** a route has been matched. Do not call these before `Router::dispatch()` has run (e.g., not in middleware).

### Testing Patterns

- Base class: `tests/TestCase.php` extends Orchestra `OrchestraTestCase`
- Override `applicationBasePath()` (static) — not `getBasePath()` — for Testbench 10.x
- Auth stub is registered by `bindAuthStub(?User $user = null)` in `setUp()`. Override this method in a subclass to inject a specific user (e.g. with `role = 'admin'`). Passing `null` (default) simulates an unauthenticated context
- `Log::fake()` does **not** work in Orchestra Testbench — use `Monolog\Handler\TestHandler` pushed onto `app('log')->getLogger()`
- Use `#[CoversClass(Foo::class)]` attribute — `@covers` docblock is removed in PHPUnit 12
- Call `Router::flushCache()` in `tearDown()` (or `setUp()`) whenever testing route-related code — the route file is cached in a static property and persists across test cases within the same process
- Call `BotUpdateFactory::reset()` in `setUp()` to reset the `update_id` counter between test cases
- Call `ComponentContext::reset()` in `tearDown()` when testing view rendering — the component stack is static and leaks between tests if a previous test left it dirty
- Call `BotResponse::flushTemplateCache()` when a test renders the **same** view path across cases with **different on-disk contents** — compiled `text.php` templates are cached in a static keyed by path (invalidated only on mtime change), so two cases writing different content to one fixture path within the same second would otherwise see the first case's compiled output
- Current suite: **478 tests / 999 assertions**

#### Feature testing with InteractsWithBot

`src/Testing/InteractsWithBot` is a PHPUnit trait for testing full bot flows without HTTP middleware. `BotUpdateFactory` builds realistic Telegram update arrays.

```php
use Wekser\Laragram\Testing\InteractsWithBot;
use Wekser\Laragram\Testing\BotUpdateFactory;

class StartCommandTest extends TestCase
{
    use InteractsWithBot;

    public function test_start_command_replies_with_welcome(): void
    {
        $this->botReceives(BotUpdateFactory::message('/start'));

        $this->assertBotRepliedWith('sendMessage');
        $this->assertBotRepliedText('Welcome');
        $this->assertUserRedirectedTo('home');
    }
}
```

Available factory methods: `BotUpdateFactory::message()`, `callbackQuery()`, `inlineQuery()`, `chosenInlineResult()`, `editedMessage()`, `channelPost()`, `preCheckoutQuery()`, `shippingQuery()`, `successfulPaymentMessage()`, `messageReaction()`.

Available assertions: `assertBotRepliedWith(string $method)`, `assertBotRepliedText(string $expected)`, `assertResponseContains(string $key, mixed $value)` (all three inspect the **first** sent message), `assertUserRedirectedTo(string $station)`, `assertNoResponse()`, `getBotResponse(): array` (first message).

For multi-message replies: `assertBotRepliedTimes(int $count)`, `assertNthReplyWith(int $index, string $method)`, `assertNthReplyText(int $index, string $expected)`, `getBotResponses(): array` (all sent messages, 0-based).

For scenes: `assertInScene(string $name)`, `assertSceneStep(string $step)`, `assertSceneData(string $key, mixed $value)`, `assertNotInScene()`.

`botReceives()` runs the full auth → router → session → **delivery** pipeline. Delivery runs the real `Http\ResponseDispatcher` against a `Testing\RecordingBotAPI` double, so assertions read the messages the bot actually sends (each `['method' => ..., ...params]`), not the raw output array. It does **not** run HTTP middleware (`VerifyTelegramSecret`, `FrameHook`, `RateLimit`). The `database` driver fires the `CallbackFormed` event; the `array` driver does not.

#### Testing BotAPI-dependent classes

`BotAPI` uses `__call()` magic, making PHPUnit mocks unreliable (PHPUnit 11 deprecated `addMethods()`, removed in PHPUnit 12). Use a hand-written spy class instead:

```php
class FakeBotAPI extends \Wekser\Laragram\BotAPI
{
    public string $calledMethod = '';
    public array  $calledParams = [];
    private int   $callCount    = 0;

    public function __construct(private readonly array $responses = []) {}

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
```

Place the `FakeBotAPI` class at the bottom of the test file (outside the test class). This pattern avoids the deprecated `addMethods()` and extends `BotAPI` with a no-arg constructor that skips the real initialization.
