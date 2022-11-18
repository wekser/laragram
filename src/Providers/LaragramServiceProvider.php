<?php

/*
 * This file is part of Laragram.
 *
 * (c) Sergey Lapin <hello@com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Wekser\Laragram\Providers;

use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use Wekser\Laragram\BotAuth;
use Wekser\Laragram\BotClient;
use Wekser\Laragram\BotResponse;
use Wekser\Laragram\Console\GetInfoCommand;
use Wekser\Laragram\Console\LaragramInstallCommand;
use Wekser\Laragram\Console\LaragramPublishCommand;
use Wekser\Laragram\Console\RemoveWebhookCommand;
use Wekser\Laragram\Console\SetWebhookCommand;
use Wekser\Laragram\Middleware\CheckAuth;
use Wekser\Laragram\Middleware\FrameHook;

class LaragramServiceProvider extends ServiceProvider
{
    /**
     * The middleware aliases.
     *
     * @var array
     */
    protected $middlewareAliases = [
        'laragram.auth' => CheckAuth::class,
        'laragram.hook' => FrameHook::class,
    ];

    /**
     * The event handler mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        'Wekser\Laragram\Events\CallbackFormed' => [
            'Wekser\Laragram\Listeners\LogSession',
        ],
    ];

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom($this->configPath(), 'laragram');

        if ($this->isLumen()) {
            $this->app->configure('laragram');
        }

        $this->registerAliases();

        $this->registerAuth();
        $this->registerClient();
        $this->registerResponse();
    }

    /**
     * Get config path.
     *
     * @return string
     */
    protected function configPath()
    {
        return realpath(__DIR__ . '/../../config/config.php');
    }

    /**
     * Check Lumen bootstrap any application services.
     *
     * @return bool
     */
    protected function isLumen()
    {
        return class_exists(\Laravel\Lumen\Application::class);
    }

    /**
     * Bind some aliases.
     *
     * @return void
     */
    protected function registerAliases()
    {
        $this->app->alias('laragram.auth', BotAuth::class);
        $this->app->alias('laragram.client', BotClient::class);
        $this->app->alias('laragram.response', BotResponse::class);
    }

    /**
     * Register the bindings for the main BotAuth class.
     *
     * @return void
     */
    protected function registerAuth()
    {
        $this->app->singleton('laragram.auth', function ($app) {
            return (new BotAuth(
                $app['request'],
                $this->config('auth.driver'),
                $this->config('auth.model'),
                $this->config('bot.languages')
            ))->authenticate();
        });
    }

    /**
     * Helper to get the config values.
     *
     * @param string $key
     * @param string|null $default
     *
     * @return mixed
     */
    protected function config(string $key, string $default = null)
    {
        return Arr::get($this->app['config']['laragram'], $key, $default);
    }

    /**
     * Register the bindings for the main Bot class.
     *
     * @return void
     */
    protected function registerClient()
    {
        $this->app->singleton('laragram.client', function () {
            return new BotClient(
                $this->config('env.token')
            );
        });
    }

    /**
     * Register the bindings for the main BotResponse class.
     *
     * @return void
     */
    protected function registerResponse()
    {
        $this->app->singleton('laragram.response', function () {
            return new BotResponse(
                $this->config('paths.views')
            );
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->isLaravel()) {
            $this->aliasLaravelMiddleware();
        } elseif ($this->isLumen()) {
            $this->app->routeMiddleware($this->middlewareAliases);
        }

        $this->loadRoutesFrom(__DIR__ . '/../../routes/routes.php');

        $this->registerEvents();

        $this->registerCommands();
    }

    /**
     * Check Laravel bootstrap any application services.
     *
     * @return bool
     */
    protected function isLaravel()
    {
        return class_exists(\Illuminate\Foundation\Application::class);
    }

    /**
     * Alias the middleware for Laravel.
     *
     * @return void
     */
    protected function aliasLaravelMiddleware()
    {
        $router = $this->app['router'];

        $method = method_exists($router, 'aliasMiddleware') ? 'aliasMiddleware' : 'middleware';

        foreach ($this->middlewareAliases as $alias => $middleware) {
            $router->$method($alias, $middleware);
        }
    }

    /**
     * Register the application's event listeners.
     *
     * @return void
     */
    protected function registerEvents()
    {
        foreach ($this->listen as $event => $listeners) {
            foreach ($listeners as $listener) {
                $this->app['events']->listen($event, $listener);
            }
        }
    }

    /**
     * Register the Artisan commands.
     *
     * @return void
     */
    protected function registerCommands()
    {
        $this->commands([
            GetInfoCommand::class,
            SetWebhookCommand::class,
            RemoveWebhookCommand::class,
            LaragramInstallCommand::class,
            LaragramPublishCommand::class,
        ]);
    }
}