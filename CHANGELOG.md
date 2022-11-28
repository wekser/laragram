# Release Notes

All notable changes to `Laragram` will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

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