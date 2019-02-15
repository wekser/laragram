# Release Notes

All notable changes to `Laragram` will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

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