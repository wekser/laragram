Laragram
=====

This is best package extends the capabilities of Laravel and Lumen for rapid implementation in Telegram chatbot within the REST style with Authentication.

## Getting Started

### Requirements

This package requires **PHP 7.1+** and **Laravel 5.5+** or **Lumen 5.5+**.

### Installation

First, you need to install the package via Composer:

```winbatch
composer require wekser/laragram
```

In **Laravel 5.5+**, the service provider and facade will automatically get registered. For **Lumen** register the service provider and facades, and also enable Eloquent in `bootstrap/app.php`:

```php
$app->withFacades(true, [
    'Wekser\Laragram\Facades\BotAuth' => 'BotAuth',
    'Wekser\Laragram\Facades\BotClient' => 'BotClient',
    'Wekser\Laragram\Facades\BotResponse' => 'BotResponse',
]);

$app->withEloquent();

$app->register(Wekser\Laragram\Providers\LaragramServiceProvider::class);
```

Run setup Laragram for publish the controllers, migrations, routes and views:

```winbatch
php artisan laragram:install
```

Finally, you need to run the `migrate` command:

```winbatch
php artisan migrate
```

### Configuration

Receive your bot authorization token from [@BotFather](https://telegram.me/botfather) and set it in `.env`:

```php
LARAGRAM_BOT_TOKEN=bot_api_token
```

Run the command to specify a url and receive incoming updates via an outgoing webhook:
```winbatch
php artisan laragram:setWebhook
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Helpful Resources

**Helpful Resources:**

* [Telegram Bot API](https://core.telegram.org/bots/api)

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.