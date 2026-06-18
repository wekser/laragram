# Laragram

A Laravel package for building Telegram bots in MVC style — routing, controllers, views, and a station-based state machine, all wired into the Laravel ecosystem.

**Requirements:** PHP ^8.2 · Laravel ^11|^12

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

Telegram sends a POST request to `/{prefix}/{secret}`. Laragram authenticates the sender, resolves the user's current **station** (state), matches a route, calls your controller, and returns a JSON response back to Telegram.

---

## Routes

Bot routes live in `routes/laragram.php`. Use the injected `$collection` variable or the `BotRoute` facade — both are equivalent.

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
$response->text('Hello!')                          // sendMessage (MarkdownV2 by default)
$response->text('Hello!', 'HTML')                  // HTML parse mode
$response->text('Hello!', null)                    // no escaping
$response->view('welcome', ['name' => 'Alice'])    // render a view directory
$response->photo($fileId, caption: 'A photo')      // sendPhoto
$response->document($fileId)                       // sendDocument
$response->edit('Updated text')                    // editMessageText
$response->answer('Done!', showAlert: true)        // answerCallbackQuery
$response->delete()                                // deleteMessage
$response->action('typing')                        // sendChatAction
$response->keyboard([...])                         // attach reply_markup (call after content)
$response->redirect('next_station')                // move user to a new station
```

Text is **auto-escaped** for the active parse mode — do not manually escape, it will double-escape. Pass `null` as the format to send pre-formatted text.

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

**`text.php`** — write plain text, use `{{ }}` for variables:

```
Hello, {{ $first_name }}!
Your email: {{ $data['email'] }}
```

Variables from `$data` are extracted into scope, so `$name` works directly. `$user` (the authenticated `User` model) is also available.

**`inline_keyboard.php`** — use global helper functions:

```php
button('Click me', 'action_1');
href('Open site', 'https://example.com');
web_app('Open Mini App', 'https://example.com/app');
row();
button('Row 2', 'action_2');
```

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
        ->row()->button('Help')
        ->resize()->oneTime()
        ->toArray()
);

ReplyKeyboard::remove();   // ['remove_keyboard' => true]
ForceReply::make()->placeholder('Type here…')->toArray();
```

---

## Station (State Machine)

Each user has a **station** — a string stored in `laragram_sessions.station`. Routes match only when the user is at the declared station. Use `->redirect()` to move users between steps:

```php
// routes/laragram.php
BotRoute::get('message')->contains('/start')->call([Ctrl::class, 'start']);
BotRoute::get('message')->from('ask_name')->call([Ctrl::class, 'saveName']);
BotRoute::get('message')->from('ask_email')->call([Ctrl::class, 'saveEmail']);

// controller
public function start(): BotResponse
{
    return $this->response->text("What's your name?")->redirect('ask_name');
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

## Artisan Commands

| Command | Description |
|---|---|
| `laragram:install` | Publish all package assets |
| `laragram:publish` | Selective publish (config / migrations / views / routes) |
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
| `laragram:set-role {uid} {role}` | Assign a role to a user |
| `laragram:add-user-activity-fields` | Publish migration for `is_active` / `deactivated_at` |
| `laragram:add-role-field` | Publish migration for the `role` column |

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

---

## Changelog

See [CHANGELOG](CHANGELOG.md) for release notes.

## License

MIT — see [LICENSE](LICENSE).
