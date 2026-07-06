# Laragram

A Laravel package for building Telegram bots in MVC style — routing, controllers, views, and a station-based state machine, all wired into the Laravel ecosystem.

**Requirements:** PHP ^8.3 · Laravel ^12|^13

---

## Installation

```bash
composer require wekser/laragram
```

Publish the config and run migrations:

```bash
php artisan laragram:install
php artisan migrate
```

Add your bot credentials to `.env`:

```env
LARAGRAM_BOT_TOKEN=your-telegram-bot-token
LARAGRAM_WEBHOOK_PREFIX=laragram
LARAGRAM_WEBHOOK_SECRET=generated-secret
```

Register the webhook:

```bash
php artisan laragram:webhook:set
```

---

## How It Works

Telegram sends a POST request to `/{prefix}/{secret}`. Laragram authenticates the sender, resolves the user's current **station** (state), matches a route, and calls your controller. The controller returns one **or several** `BotResponse` objects; Laragram delivers each as an outbound Telegram Bot API call and answers the webhook with `200 OK`.

---

## Routes

Bot routes live in `routes/laragram/routes.php`. Use the injected `$collection` variable or the `BotRoute` facade — both are equivalent.

```php
use Wekser\Laragram\Facades\BotRoute;

// Match /start from any station
BotRoute::get('message')
    ->contains('/start')
    ->call([StartController::class, 'index']);

// Match any text when user is at 'ask_name' station
BotRoute::get('message')
    ->from('ask_name')
    ->call([OnboardingController::class, 'saveName']);

// Match callback with a named param, admin only
BotRoute::get('callback_query')
    ->from('home')
    ->contains('/action {id}')
    ->role('admin')
    ->call([AdminController::class, 'action']);

// Catch-all fallback
BotRoute::fallback()->call([StartController::class, 'fallback']);
```

**DSL reference:**

| Method | Description |
|---|---|
| `->get('event')` | Telegram update type (`message`, `callback_query`, `inline_query`, etc.) |
| `->from('station')` | Only match when user is at this station |
| `->contains('/cmd')` | Command, exact text, or `{param}` pattern |
| `->role('admin')` | Restrict to users with a specific role |
| `->name('route_name')` | Assign a name (shown in `route:list`) |
| `->call([Ctrl::class, 'method'])` | Controller action or closure |
| `->fallback()` | Catch-all — matches anything not matched above |

`->group()` applies shared station and role to multiple routes:

```php
BotRoute::group(function ($c) {
    $c->get('message')->contains('/users')->call([AdminController::class, 'users']);
    $c->get('callback_query')->call([AdminController::class, 'callback']);
}, from: 'admin_panel', roles: 'admin');
```

---

## Controllers

Controllers are resolved through Laravel's IoC container — constructor injection works out of the box.

```php
use Wekser\Laragram\BotRequest;
use Wekser\Laragram\BotResponse;
use Wekser\Laragram\Models\User;

class StartController extends Controller
{
    public function __construct(protected BotResponse $response) {}

    public function index(BotRequest $request, User $user): BotResponse
    {
        return $this->response
            ->text("Hello, {$user->first_name}!")
            ->redirect('home');
    }
}
```

**`BotRequest`** wraps the incoming update:

```php
$request->get('text');          // dot-notation access to any update field
$request->input('id');          // named {param} from the matched route pattern
$request->message();            // the message sub-object
$request->callbackQuery();      // the callback_query sub-object
$request->validate([...]);      // Laravel validation on the update payload
```

**`BotResponse`** builds the reply:

```php
$response->text('Hello!')                          // sendMessage (HTML by default)
$response->text('Hello!', 'MarkdownV2')            // MarkdownV2 parse mode
$response->text('Hello!', null)                    // no escaping
$response->view('welcome', ['name' => 'Alice'])    // render a view directory
$response->photo($fileId, caption: 'A photo')      // sendPhoto
$response->document($fileId)                       // sendDocument
$response->edit('Updated text')                    // editMessageText
$response->answer('Done!', showAlert: true)        // answerCallbackQuery
$response->delete()                                // deleteMessage
$response->react('👍')                             // setMessageReaction
$response->action('typing')                        // sendChatAction
$response->invoice(Invoice::make()...)             // sendInvoice (see Payments)
$response->inlineResults(InlineResults::make()...) // answerInlineQuery (see Inline Mode)
$response->keyboard([...])                         // attach reply_markup (call after content)
$response->redirect('next_station')                // move user to a new station
```

Text is **auto-escaped** for the active parse mode — do not manually escape, it will double-escape. Pass `null` as the format to send pre-formatted text.

### Multiple messages

Return an **array** of responses to send several messages in reply to one update. Each is delivered as a separate Bot API call, in order:

```php
public function welcome(): array
{
    return [
        $this->response->view('greeting'),
        $this->response->text('Here is a quick tip 👇'),
        $this->response->photo($fileId, caption: 'And a picture')->redirect('home'),
    ];
}
```

- The next **station** is taken from the last response that calls `->redirect()` (last-write-wins); if none do, the user stays at the current station.
- Delivery is resilient: a failed message is logged and the batch continues — unless the user is unreachable (blocked the bot, deactivated, chat gone), in which case the remaining messages are skipped.
- Each `BotResponse::text()`, `view()`, `photo()`, etc. returns a **fresh** instance, so collecting several of them into an array always produces distinct messages — even when built through the `BotResponse` facade.

---

## Views

Views are **directories** under `resources/laragram/` (dot notation → subdirectories). Each component of the message is a separate PHP file:

```
resources/laragram/
└── welcome/
    ├── text.php               ← message text — use {{ expr }} for dynamic values
    ├── inline_keyboard.php    ← call button() / href() / row()
    └── reply_keyboard.php     ← call reply() / row() / resize() / one_time()
```

**`text.php`** — write plain text plus your own HTML markup (default parse mode is `HTML`); `{{ }}` escapes a value, `{!! !!}` emits it raw:

```
Hello, <b>{{ $first_name }}</b>!
{!! __('laragram.welcome.body') !!}
```

Static markup (`<b>…</b>`) renders as-is. `{{ }}` values are auto-escaped (safe for user data); `{!! !!}` values are emitted raw (use for trusted, pre-formatted content like translation strings). Variables from `$data` are extracted into scope, so `$name` works directly. `$user` (the authenticated `User` model) is also available.

**`inline_keyboard.php`** — use global helper functions:

```php
button('Click me', 'action_1');
href('Open site', 'https://example.com');
web_app('Open Mini App', 'https://example.com/app');
row();
button('Row 2', 'action_2');
```

The full `InlineKeyboardButton` API is available as helpers: `button()`, `href()`, `web_app()`, `login_url()`, `switch_inline()`, `switch_inline_chosen()`, `switch_inline_chosen_chat()`, `copy_text()`, `pay()`, `callback_game()`. Each one takes optional trailing `style:` (`primary`/`success`/`danger`) and `icon:` (custom emoji) attributes — e.g. `button('Delete', 'rm', style: 'danger')` (Bot API 9.4+).

**`reply_keyboard.php`:**

```php
resize();
reply('Option A'); reply('Option B');
row(); reply('Help');
```

**`media.php`** — for `sendMediaGroup`:

```php
photo($data['photo_id'], caption: 'First');
video($data['video_id']);
```

For single media, add a `photo.php` (or `video.php`, `document.php`, etc.) containing just the file_id or URL.

Render with:

```php
$response->view('welcome', ['first_name' => $user->first_name]);
```

Scaffold a new view directory:

```bash
php artisan laragram:make:view welcome
```

---

## Keyboards (programmatic)

For building keyboards in controllers without view files:

```php
use Wekser\Laragram\Telegram\Keyboards\InlineKeyboard;
use Wekser\Laragram\Telegram\Keyboards\ReplyKeyboard;
use Wekser\Laragram\Telegram\Keyboards\ForceReply;

$response->text('Choose:')->keyboard(
    InlineKeyboard::make()
        ->button('Yes', 'confirm')
        ->button('No', 'cancel')
        ->row()
        ->href('Open site', 'https://example.com')
        ->webApp('Open Mini App', 'https://example.com/app')
        ->toArray()
);

$response->text('Choose:')->keyboard(
    ReplyKeyboard::make()
        ->button('Option A')->button('Option B')
        ->row()
        ->requestLocation('📍 Send my location')   // also requestContact / requestPoll / requestUser / requestChat
        ->resize()->oneTime()
        ->toArray()
);

ReplyKeyboard::remove();   // ['remove_keyboard' => true]
ForceReply::make()->placeholder('Type here…')->toArray();
```

`InlineKeyboard` covers the full button API (`switchInline()`, `switchInlineChosen()`, `switchInlineChosenChat()`, `loginUrl()`, `copyText()`, `pay()`, `callbackGame()`, plus a `paginate()` helper); `ReplyKeyboard` adds one-tap `requestContact()` / `requestLocation()` / `requestPoll()` / `requestUser()` / `requestChat()` buttons. Every button method on **both** builders accepts optional trailing `style:` (`primary`/`success`/`danger`) and `icon:` (custom emoji) attributes — e.g. `->button('Delete', 'rm', style: 'danger')` (Bot API 9.4+).

---

## Station (State Machine)

Each user has a **station** — a string stored in `laragram_sessions.station`. Routes match only when the user is at the declared station. Use `->redirect()` to move users between steps:

```php
// routes/laragram/routes.php
BotRoute::get('message')->contains('/start')->call([Ctrl::class, 'start']);
BotRoute::get('message')->from('ask_name')->call([Ctrl::class, 'saveName']);
BotRoute::get('message')->from('ask_email')->call([Ctrl::class, 'saveEmail']);

// controller
public function start(): array
{
    // Send several messages at once; the next station comes from the last
    // response that calls redirect() (here, the question).
    return [
        $this->response->text('Welcome! 👋'),
        $this->response->text("What's your name?")->redirect('ask_name'),
    ];
}

public function saveName(BotRequest $request): BotResponse
{
    // store name ...
    return $this->response->text('Now your email:')->redirect('ask_email');
}
```

Debug routing in your terminal:

```bash
php artisan laragram:route:match message "/start"
php artisan laragram:route:match message "hello" --station=ask_name
```

---

## Group Chats

The bot works in private chats **and** in groups/supergroups — private (1-on-1) behaviour is unchanged.

```php
// Restrict routes to a chat type (default: any)
BotRoute::get('message')->contains('/rules')->inGroups()->call([Ctrl::class, 'rules']);
BotRoute::get('message')->contains('/settings')->inPrivate()->call([Ctrl::class, 'settings']);
BotRoute::get('message')->contains('/kick')->chat('supergroup')->call([Ctrl::class, 'kick']);

// Inspect the chat in a handler
public function rules(BotRequest $request): BotResponse
{
    $request->isGroup();       // group || supergroup
    $request->isPrivate();
    $request->chatType();      // 'private' | 'group' | 'supergroup' | 'channel'
    return $this->response->view('rules');
}
```

- **Commands** like `/start` arrive as `/start@YourBot` in groups — the mention is stripped automatically. Set `LARAGRAM_BOT_USERNAME` (your bot's @username, without `@`) so only *your* bot's mention matches.
- **State is per-(user, chat):** each member has independent station + scene state in each chat, so wizards never collide between a group and a private chat.
- **Group privacy is ON by default** in @BotFather: the bot only sees commands aimed at it, replies to its own messages, and @mentions. To receive all group messages, disable privacy via @BotFather (`/setprivacy` → Disable) and re-add the bot.

---

## Scenes (Wizards)

For multi-step forms — registration, an order, a survey — a **scene** manages the whole flow for you: it asks each step's question, validates the answer, stores it, and hands all answers to a completion handler. It's built on top of stations, but you don't wire them by hand.

Define scenes in `routes/laragram/scenes.php`:

```php
use Wekser\Laragram\Facades\BotResponse;
use Wekser\Laragram\Facades\BotScene;
use Wekser\Laragram\Telegram\Keyboards\InlineKeyboard;

BotScene::define('order')
    ->step('size')
        ->ask(fn ($ctx) => BotResponse::text('What size?')->keyboard(   // pick by tapping
            InlineKeyboard::make()
                ->button('Small', 'Small')->button('Medium', 'Medium')->button('Large', 'Large')
                ->toArray()))
        ->expectCallback()                                             // read the tapped button
        ->rules(['required', 'in:Small,Medium,Large'])
    ->step('address')
        ->ask(fn ($ctx) => BotResponse::text("Address for {$ctx->get('size')}?"))
        ->rules(['required', 'min:5'])
        ->transform(fn ($v) => trim($v))
    ->cancelOn('/cancel')
    ->onComplete([OrderController::class, 'place']);
```

Enter the scene from any route handler, and handle the result when it finishes:

```php
public function order(BotRequest $request)
{
    return BotScene::enter('order');        // sends the first question
}

public function place(SceneContext $ctx)    // onComplete handler — gets every answer
{
    Order::create($ctx->all());
    return BotResponse::view('order.done')->redirect('home');
}
```

More step/scene options: `->when(fn ($ctx) => …)` (conditional steps), `->allowBack('/back')`, `->timeout($minutes)` + `->onTimeout()`, `->onInvalid($view|$closure)` (custom error message), and typed extractors `->expectContact()/expectLocation()/expectPhoto()/expectCallback()`. A `SceneContext` exposes `get()/all()/has()/request()/user()`. Back navigation drops any answers a changed earlier answer makes irrelevant (so `onComplete` never sees inconsistent data), and an invalid reply re-asks the step without resetting the `timeout()` clock.

> Scenes require the `database` auth driver (step state is persisted between updates in `laragram_sessions.payload`). Scaffold one with `php artisan laragram:make:scene order --steps=size,address`.

See the [Scenes wiki page](https://github.com/wekser/laragram/wiki/Scenes) for the full reference.

---

## Payments (Telegram Stars & fiat)

Send invoices, answer the checkout steps, and handle completed payments — for both **Telegram Stars** (`XTR`) and fiat currencies (Telegram Payments 2.0):

```php
use Wekser\Laragram\Telegram\Payments\Invoice;
use Wekser\Laragram\Telegram\Keyboards\InlineKeyboard;

// 1. Send a Stars invoice — chat_id is injected automatically
public function donate()
{
    return $this->response->invoice(
        Invoice::make()->title('Support us')->description('One-time donation')
            ->payload('donate_1')->stars(100, 'Donation')
    )->keyboard(InlineKeyboard::make()->pay('Pay ⭐100')->toArray());
}

// Fiat variant: ->currency('USD')->price('Item', 1999)->providerToken(...)

// 2. Approve (or decline) the pre-checkout step — answer within 10 seconds
BotRoute::get('pre_checkout_query')->call(fn () => BotResponse::approveCheckout());

// 3. Handle the completed payment (a field on a "message" update — note the listener override)
BotRoute::get('message', 'successful_payment')->call([ShopController::class, 'paid']);
```

- Amounts are in the currency's **smallest unit** (cents; whole stars for `XTR`).
- `approveShipping(array $options)` / `declineShipping($reason)` cover flexible-price shipping.
- `BotRequest` accessors: `preCheckoutQuery()`, `shippingQuery()`, `successfulPayment()`.
- **`Events\PaymentReceived`** fires for every completed payment — independently of routing — so you can grant the entitlement from a single listener (`invoicePayload()`, `chargeId()`, `totalAmount()`, `isStars()`).
- Opt-in **payment history**: set `LARAGRAM_PAYMENTS_STORE=true` (plus the published `laragram_payments` migration) and every payment is persisted idempotently.
- Outbound actions live on the `Services\Payments` service: `invoiceLink()` (createInvoiceLink), `refund($userId, $chargeId)` (refundStarPayment), `starTransactions()`.

```env
LARAGRAM_PAYMENT_PROVIDER_TOKEN=   # fiat only; Stars need no provider
LARAGRAM_PAYMENT_CURRENCY=USD
LARAGRAM_PAYMENTS_STORE=false
```

---

## Inline Mode

Answer `inline_query` updates — the results users get typing `@yourbot query` in any chat:

```php
use Wekser\Laragram\Telegram\Inline\InlineResults;

BotRoute::get('inline_query')->call(function (BotRequest $request) {
    return BotResponse::inlineResults(
        InlineResults::make()
            ->article('1', 'Say hello', 'Hello there!')       // sends text when picked
            ->photo('2', 'https://example.com/pic.jpg')
            ->cachedPhoto('3', $fileId)                        // by cached file_id
            ->cache(300)->personal()
    );
});

// Optional: know which result the user picked (enable inline feedback with @BotFather)
BotRoute::get('chosen_inline_result')->call([StatsController::class, 'picked']);
```

Result builders: `article()`, `photo()`, `gif()`, `video()`, `document()`, `cachedPhoto()`, `sticker()`, and `raw(array)` for any other `InlineQueryResult` type. Answer-level options: `cache()`, `personal()`, `nextOffset()` (pagination), `button()`. The `inline_query_id` is injected automatically.

---

## Receiving Files

Turn a file a user **sent to the bot** into bytes or a stored file — the mirror image of `MediaUploader`:

```php
use Wekser\Laragram\Services\MediaDownloader;

public function receive(BotRequest $request, MediaDownloader $downloader)
{
    // Fluent handle off the request (null when the update carries no file):
    $path  = $request->file()?->save('local', 'inbox/receipt.jpg');   // store on a disk
    $bytes = $request->file()?->bytes();                              // raw bytes

    // Or the service directly:
    $path = $downloader->save($request->fileId(), 's3', 'kyc/doc.pdf');

    return $this->response->text('Got it!');
}
```

`BotRequest::fileId()` finds the file across the common media fields (photo → largest size, document, video, audio, voice, animation, video_note, sticker). Downloads are SSRF-hardened and size-capped:

```env
LARAGRAM_DOWNLOADS_DISK=local
LARAGRAM_DOWNLOADS_MAX_SIZE=20971520   # 20 MB — Telegram's getFile limit
LARAGRAM_DOWNLOADS_TIMEOUT=30          # download HTTP timeout, seconds
```

---

## Message Reactions

React to messages and handle users' reactions:

```php
// React to an incoming message (chat_id + message_id injected automatically)
BotRoute::get('message')->contains('/like')
    ->call(fn () => BotResponse::react('👍', big: true));

// Handle a user changing their reaction on a message
BotRoute::get('message_reaction')->call(function (BotRequest $request) {
    $reaction = $request->messageReaction();   // chat, message_id, user, old/new_reaction
    return BotResponse::react('❤️');           // react back on the same message
});
```

`react()` accepts an emoji string, a list of them, raw `ReactionType` arrays (custom emoji), or `[]` to clear the bot's reaction.

> Telegram delivers `message_reaction` / `message_reaction_count` updates only when `allowed_updates` explicitly includes them — pass it when calling `setWebhook`.

---

## Queue (optional, for scale)

By default Laragram processes each update **inside** the webhook request. Under bursts of concurrent users you can offload processing to a queue: the webhook validates the update, dispatches a job, and answers `200 OK` immediately, while routing and the outbound Bot API calls run on a worker.

Enable it in `.env`:

```env
LARAGRAM_QUEUE_ENABLED=true
LARAGRAM_QUEUE_CONNECTION=redis   # leave unset to use your default connection
LARAGRAM_QUEUE_NAME=default
LARAGRAM_QUEUE_RATE_LIMIT=25      # max update jobs/sec across all workers
```

Run a worker:

```bash
php artisan queue:work --queue=default
```

- The four webhook middleware (verify → auth → dedup → throttle) still run synchronously, so only **verified, non-bot, non-duplicate, rate-limited** updates are ever queued.
- **Per-user ordering:** jobs are serialized per sender (`WithoutOverlapping`), avoiding session races. This is mutual exclusion, not strict FIFO — run a **single worker per queue** if a step-by-step station flow must never reorder.
- **Throughput:** a named `laragram` rate limiter caps global execution to stay under Telegram's ~30 msg/sec outbound limit.
- **Privacy:** the job implements `ShouldBeEncrypted`, so the payload (which carries user PII) is encrypted at rest in the queue store.

> Use Redis in production — the rate limiter and the per-user lock need a shared cache store to be accurate across multiple workers. When disabled (the default), behaviour is fully synchronous and unchanged.

---

## Broadcasting (mass messaging)

Send one message to your whole user base — announcements, promos, downtime notices — with the `BotBroadcast` facade or the `laragram:broadcast` command. Requires the `database` auth driver (there are no persisted users under `array`).

```php
use Wekser\Laragram\Facades\BotBroadcast;

// Raw text to every active user
BotBroadcast::text('We are back online!')->send();

// A view, rendered per recipient (in their own language, with $user in scope)
BotBroadcast::view('news.release', ['version' => '2.0'])
    ->role(['admin', 'moderator'])     // optional: restrict to roles
    ->includeInactive()                // optional: also reach deactivated users
    ->send();
```

From the CLI:

```bash
php artisan laragram:broadcast "We are back online!"             # text to all active users
php artisan laragram:broadcast --view=news.release              # render a view per recipient
php artisan laragram:broadcast "Admins only" --role=admin --dry-run   # just print the recipient count
```

- **Delivery scales with your setup.** With the queue enabled, a broadcast dispatches one job per recipient, throttled by the same `laragram` rate limiter as incoming updates; otherwise it sends synchronously, paced just under Telegram's ~30 msg/sec limit. Each message is rendered per recipient, so views localize to each user.
- **Unreachable users self-prune.** The first time a send fails because a user blocked the bot, deactivated, or the chat is gone, that user is marked inactive (`User::deactivate()`) so future broadcasts skip them. This runs for *every* send, not just broadcasts, via the `BotExceptionHandled` event — toggle with `LARAGRAM_BROADCAST_DEACTIVATE_UNREACHABLE`.

```env
LARAGRAM_BROADCAST_CHUNK_SIZE=500             # recipients loaded per batch
LARAGRAM_BROADCAST_SYNC_DELAY_MS=40           # pause between sends on the synchronous path (≈25/sec)
LARAGRAM_BROADCAST_DEACTIVATE_UNREACHABLE=true
```

---

## Admin Panel

A bundled, server-rendered dashboard for your bot's user base — metrics, users & roles, sessions, and a broadcast composer. Self-contained (own routes + Blade views, no build step, no external dependencies), mounted at `/laragram/admin` by default. Requires the `database` auth driver.

The panel is protected by its **own login page** backed by the `laragram_admins` table — no host-app web auth is required, and it works in production from any IP. Run the migration (`laragram:install` scaffolds it), then create an account:

```bash
php artisan laragram:admin:create            # prompts for username + password (min 8 chars)
php artisan laragram:admin:create alice --name="Alice" --password=secret123   # or non-interactively
php artisan laragram:admin:delete alice      # remove an account
```

Browse to `/laragram/admin` and you'll be redirected to the login page. Passwords are hashed automatically by `Models\Admin` (a dedicated `Authenticatable`, distinct from the Telegram `User`), which logs in through a self-registered `laragram_admin` session guard — so **no `config/auth.php` edits are needed**.

**Escape hatch:** if you'd rather reuse your host app's own web auth, define a `viewLaragram` Gate ability — it overrides the login and decides access itself (a denying gate is a hard 403):

```php
// app/Providers/AppServiceProvider.php
Gate::define('viewLaragram', fn ($user) => $user->isAdmin());
```

```env
LARAGRAM_ADMIN_ENABLED=true
LARAGRAM_ADMIN_PATH=laragram/admin
```

Pages: **Dashboard** (total/active users, new today/week, roles, active sessions) · **Users** (set role, activate/deactivate) · **Sessions** (browse, prune) · **Broadcast** (dry-run count or send — honours the queue/sync path).

---

## Observability

Laragram never lets an exception escape update processing — routing, delivery, and the queued job all funnel their errors through `ExceptionHandler`, which logs reportable ones and silences user-unreachable ones. That makes silently-handled failures invisible (they never reach `failed_jobs`). The `BotExceptionHandled` event is the seam for surfacing them:

```php
use Illuminate\Support\Facades\Event;
use Wekser\Laragram\Events\BotExceptionHandled;

Event::listen(function (BotExceptionHandled $e) {
    // $e->exception, $e->reportable (was it logged?), $e->terminal (user unreachable?)
    if ($e->terminal) {
        // e.g. count how many users blocked the bot
    }
    // push to Sentry / StatsD / Horizon tags…
});
```

Listening is optional (no listener = near-zero-cost no-op); dispatch is guarded, so a faulty listener can never break exception handling.

---

## Artisan Commands

| Command | Description |
|---|---|
| `laragram:install` | Bootstraps the host app: config, migrations, blank route/scene files, `.env` variables |
| `laragram:publish` | Publishes the runnable demo: views, lang, demo controllers + routes (incl. Stars payments, inline mode, file receiving) |
| `laragram:webhook:set` | Register the webhook with Telegram |
| `laragram:webhook:remove` | Remove the webhook |
| `laragram:getMe` | Display bot info (`getMe`) |
| `laragram:webhook:info` | Display current webhook state |
| `laragram:poll` | Start long-polling (dev without a public URL) |
| `laragram:route:list` | List all registered bot routes |
| `laragram:route:match {event} {text}` | Debug: show which route matches |
| `laragram:session:prune` | Delete expired sessions |
| `laragram:make:controller` | Scaffold a new bot controller |
| `laragram:make:view` | Scaffold a new bot view directory |
| `laragram:make:scene` | Scaffold a new scene (wizard) in the scenes file |
| `laragram:scene:list` | List all registered scenes |
| `laragram:set-role {uid} {role}` | Assign a role to a user |
| `laragram:broadcast {message?}` | Mass-message users (`--view`, `--role=*`, `--include-inactive`, `--dry-run`, `--no-confirm`) |
| `laragram:admin:create {username?}` | Create (or reset the password of) an admin-panel login account (`--name`, `--password`) |
| `laragram:admin:delete {username}` | Delete an admin-panel login account |

---

## Supported Update Types

| Event | Matched against |
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

---

## Changelog

See [CHANGELOG](CHANGELOG.md) for release notes.

## License

MIT — see [LICENSE](LICENSE).
