# Release Notes

All notable changes to `Laragram` will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [v2.0.0] (2026-03-28)

This is a major release. It introduces a redesigned namespace structure, an auth driver system, a full test suite, and several breaking changes. Deprecated aliases are provided where possible to ease migration.

### Added

**Routing**
- `Routing\Route` — immutable value object representing a single route (readonly properties)
- `Routing\RouteCollection` — fluent DSL for building routes (replaces `BotRouteCollection`)
- `Routing\Router` — route dispatcher (replaces `BotRouter`)
- `->role(string|array $roles)` DSL method restricts a route to users with a specific role
- `->group(callable $callback, from: '...', roles: '...')` — applies shared station and role constraints to a group of routes
- `Router::flushCache()` — clears the static route file cache (useful in tests)

**HTTP Layer**
- `Http\RequestTransformer` — builds `BotRequest` from the raw update and the matched route (replaces `Support\FormRequest`)
- `Http\ResponseTransformer` — wraps the controller response into the output array; auto-injects `chat_id`, `callback_query_id`, and `message_id` (replaces `Support\FormResponse`)
- Log warning in `ResponseTransformer` when `chat_id` cannot be determined for an outgoing API call

**Auth Drivers**
- `Auth\AuthDriverInterface` — contract for authentication drivers: `resolveUser()` and `isActive()`
- `Auth\DatabaseAuthDriver` — persists the user to the database on every request using `updateOrCreate`; merges settings without replacing them
- `Auth\ArrayAuthDriver` — in-memory driver; no database I/O; station is always `'start'`
- `BotAuth::getDriverInstance()` — returns the active `AuthDriverInterface` instance
- `BotAuth::findFromInPayload(array $payload): ?array` — public static helper to extract the `from` field from any update type

**Telegram Helpers**
- `Telegram\Keyboards\InlineKeyboard` — fluent builder for `InlineKeyboardMarkup`
- `Telegram\Keyboards\ReplyKeyboard` — fluent builder for `ReplyKeyboardMarkup`; static `remove()` for `ReplyKeyboardRemove`
- `Telegram\Keyboards\ForceReply` — fluent builder for `ForceReply` with `placeholder()` and `selective()` options
- `Telegram\Media\MediaGroup` — fluent builder for `sendMediaGroup` payloads (up to 10 items)

**Services**
- `Services\MediaUploader` — uploads a local file or remote URL to Telegram and returns a reusable `file_id`; bound as `laragram.media` singleton; supports `photo`, `document`, `audio`, `video`, `voice`, `animation`, `video_note`, `sticker`
- `Services\TelegramErrorHandler` — maps Telegram API error responses to typed exceptions; provides `validateUserBeforeSend()` and `getUserStatus()` for checking user reachability
- `Enums\TelegramErrorCode` — int-backed enum for Telegram HTTP error codes (400–504) with `getDescription()`, `getDetailedDescription()`, `getRecommendedAction()`, `requiresUserDeactivation()`, and `requiresSpecialHandling()`

**Exceptions**
- `Exceptions\ExceptionHandler` — static utility (replaces `BotException`); `handle(\Throwable)` logs reportable exceptions and silences `AuthenticationException`, `BotBlockedException`, `UserDeactivatedException`, `ChatNotFoundException`
- `Exceptions\BotBlockedException`, `UserDeactivatedException`, `ChatNotFoundException`, `TelegramApiException` — typed exceptions for Telegram API error conditions
- `Exceptions\AuthenticationException` — thrown when the sender cannot be authenticated

**Middleware**
- `Middleware\VerifyTelegramSecret` — validates the `X-Telegram-Bot-Api-Secret-Token` header using `hash_equals()`; empty configured secret → HTTP 500 (misconfiguration); wrong token → HTTP 401
- `Middleware\RateLimit` — per-user rate limiting via Laravel `RateLimiter` (falls back to IP)
- Log warning at boot when `security.verify_secret` is `true` but `LARAGRAM_WEBHOOK_SECRET` is not configured

**BotResponse**
- `BotResponse::answer(string $text, bool $showAlert)` — sends `answerCallbackQuery`
- `BotResponse::edit(string $text, ?string $format)` — sends `editMessageText`
- `BotResponse::delete()` — sends `deleteMessage`
- `BotResponse::setUser(User $user)` — overrides the authenticated user in the response context
- `keyboard()` guard — throws `\LogicException` when called before a content method (`text()`, `view()`, etc.)
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
- `laragram:make:controller` — scaffolds a new bot controller
- `laragram:make:view` — scaffolds a new bot view
- `laragram:add-user-activity-fields` — publishes a migration adding `is_active` and `deactivated_at` columns to the users table
- `laragram:add-role-field` — publishes a migration adding a `role VARCHAR DEFAULT 'user'` column to the users table

**Testing**
- `Testing\InteractsWithBot` — PHPUnit trait for feature-testing bot flows without HTTP; runs the full auth → router → session pipeline
- `Testing\BotUpdateFactory` — factory for realistic Telegram update arrays; supports `message()`, `callbackQuery()`, `inlineQuery()`, `editedMessage()`, `channelPost()`
- Assertions: `assertBotRepliedWith()`, `assertBotRepliedText()`, `assertUserRedirectedTo()`, `assertNoResponse()`, `assertResponseContains()`, `getBotResponse()`

**Config**
- `laragram.php` — new config file name (was `config.php`)
- `auth.session.model` / `auth.session.table` — session model class and table
- `auth.user.model` / `auth.user.table` — user model class and table
- `bot.languages` — array of supported language codes
- `rate.max_attempts` / `rate.decay_seconds` — rate limiting parameters
- `security.verify_secret` — toggle for `X-Telegram-Bot-Api-Secret-Token` validation

**Other**
- Support for 7 additional Telegram update types: `channel_post`, `edited_channel_post`, `poll`, `poll_answer`, `my_chat_member`, `chat_member`, `chat_join_request`
- `BotAPI` PHPDoc `@method` annotations expanded from ~35 to ~70 Telegram API methods
- `Facades\BotAPI`, `Facades\BotAuth`, `Facades\BotRoute`, `Facades\BotResponse` — registered facades
- `src/Examples/` removed (was incorrectly autoloaded as package code)

### Changed

- PHP requirement raised to `^8.2`; Laravel requirement raised to `^11.0|^12.0`
- `BotAPI` replaced ~40 explicit wrapper methods with a single `__call()` magic proxy — all Telegram API methods work automatically
- `DatabaseAuthDriver` uses `updateOrCreate` atomically instead of `firstOrCreate` + `save()`; settings are merged (not replaced) on every request
- `LogSession` listener now uses `updateOrCreate` — station is correctly updated on every request, not only on first visit; database failures are logged via `try/catch` instead of crashing the event listener
- `LogSession` fires `CallbackFormed` event for both `database` and `array` drivers
- `BotAuth::authenticate()` is resolved lazily as a singleton closure — safe to override in tests before first resolution
- `BotResponse::escapeText()`: passing `null` as the parse mode now returns text unmodified; previously fell through to legacy Markdown escaping
- `RequestTransformer::extractNamedParams()`: argument splitting uses `preg_split('/\s+/', trim(...))` instead of `explode(' ', ...)` — handles extra whitespace in commands correctly
- `ExceptionHandler::handle()` no longer calls `response()->send()` directly; `Laragram::back()` is solely responsible for the HTTP response, eliminating the double-send
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

### Security

- `VerifyTelegramSecret` validates the webhook secret token with `hash_equals()` to prevent timing attacks; empty configured secret now fails with HTTP 500 instead of silently accepting all requests
- `BotResponse::validateFormat()` — unknown `parse_mode` values are rejected early, preventing unescaped user text from being sent when an invalid format is accidentally passed
- `BotResponse::setPath()` rejects view names containing `..`, `/`, or `\` to prevent path traversal
- `config('laragram.paths.route')` validated against path traversal characters before building the route file path
- SSL certificate verification enabled in `BotClient`

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