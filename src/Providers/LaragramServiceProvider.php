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

namespace Wekser\Laragram\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Wekser\Laragram\BotAPI;
use Wekser\Laragram\BotAuth;
use Wekser\Laragram\BotResponse;
use Wekser\Laragram\Broadcasting\Broadcaster;
use Wekser\Laragram\Broadcasting\BroadcastRenderer;
use Wekser\Laragram\Http\ResponseDispatcher;
use Wekser\Laragram\Scene\SceneManager;
use Wekser\Laragram\Scene\SceneRegistry;
use Wekser\Laragram\Services\MediaUploader;
use Wekser\Laragram\Console\BroadcastCommand;
use Wekser\Laragram\Console\GetInfoCommand;
use Wekser\Laragram\Console\LaragramInstallCommand;
use Wekser\Laragram\Console\LaragramPublishCommand;
use Wekser\Laragram\Console\MakeControllerCommand;
use Wekser\Laragram\Console\MakeSceneCommand;
use Wekser\Laragram\Console\MakeViewCommand;
use Wekser\Laragram\Console\PollCommand;
use Wekser\Laragram\Console\SceneListCommand;
use Wekser\Laragram\Console\WebhookRemoveCommand;
use Wekser\Laragram\Console\RouteListCommand;
use Wekser\Laragram\Console\RouteMatchCommand;
use Wekser\Laragram\Console\SessionPruneCommand;
use Wekser\Laragram\Console\SetRoleCommand;
use Wekser\Laragram\Console\WebhookSetCommand;
use Wekser\Laragram\Console\WebhookInfoCommand;
use Wekser\Laragram\Middleware\CheckAuth;
use Wekser\Laragram\Middleware\FrameHook;
use Wekser\Laragram\Middleware\RateLimit;
use Wekser\Laragram\Middleware\VerifyTelegramSecret;

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
        'laragram.throttle' => RateLimit::class,
        'laragram.verify' => VerifyTelegramSecret::class,
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
        'Wekser\Laragram\Events\BotExceptionHandled' => [
            'Wekser\Laragram\Listeners\DeactivateUnreachableUser',
        ],
    ];

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        require_once __DIR__ . '/../View/helpers.php';

        $this->mergeConfigFrom($this->configPath(), 'laragram');

        $this->registerAliases();

        $this->registerAPI();
        $this->registerAuth();
        $this->registerResponse();
        $this->registerMediaUploader();
        $this->registerDispatcher();
        $this->registerScenes();
        $this->registerBroadcaster();
    }

    /**
     * Get config path.
     *
     * @return string
     */
    protected function configPath()
    {
        return realpath(__DIR__ . '/../../config/laragram.php');
    }

    /**
     * Bind some aliases.
     *
     * @return void
     */
    protected function registerAliases()
    {
        $this->app->alias('laragram.api', BotAPI::class);
        $this->app->alias('laragram.auth', BotAuth::class);
        $this->app->alias('laragram.response', BotResponse::class);
        $this->app->alias('laragram.media', MediaUploader::class);
        $this->app->alias('laragram.dispatcher', ResponseDispatcher::class);
        $this->app->alias('laragram.scene', SceneManager::class);
        $this->app->alias('laragram.broadcast', Broadcaster::class);
    }

    /**
     * Register the bindings for the main Bot class.
     *
     * @return void
     */
    protected function registerAPI()
    {
        $this->app->singleton('laragram.api', function () {
            $token = (string) $this->config('telegram.token');

            if (empty($token)) {
                throw new \RuntimeException(
                    'Laragram: LARAGRAM_BOT_TOKEN is not configured. Add it to your .env file.'
                );
            }

            return new BotAPI($token);
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
    protected function config(string $key, ?string $default = null)
    {
        return Arr::get($this->app['config']['laragram'], $key, $default);
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
                $this->config('bot.languages'),
                $this->config('auth.user.model')
            ))->authenticate();
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
     * Register the MediaUploader service.
     *
     * @return void
     */
    protected function registerMediaUploader(): void
    {
        $this->app->singleton(MediaUploader::class, function ($app) {
            return new MediaUploader($app['laragram.api']);
        });
    }

    /**
     * Register the ResponseDispatcher service.
     *
     * @return void
     */
    protected function registerDispatcher(): void
    {
        $this->app->singleton('laragram.dispatcher', function ($app) {
            return new ResponseDispatcher($app['laragram.api']);
        });
    }

    /**
     * Register the scene (wizard) runtime.
     *
     * @return void
     */
    protected function registerScenes(): void
    {
        $this->app->singleton('laragram.scene', function () {
            return new SceneManager(new SceneRegistry());
        });
    }

    /**
     * Register the broadcast (mass messaging) service.
     *
     * @return void
     */
    protected function registerBroadcaster(): void
    {
        $this->app->singleton('laragram.broadcast', function () {
            return new Broadcaster(new BroadcastRenderer());
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
        }

        $this->loadRoutesFrom(__DIR__ . '/../../routes/routes.php');

        $this->validateSecretConfig();

        $this->registerEvents();

        $this->registerRateLimiter();

        if ($this->app->runningInConsole()) {
            $this->registerCommands();

            $this->publishes([
                $this->configPath() => config_path('laragram.php'),
            ], 'laragram-config');

            $this->publishes([
                __DIR__ . '/../Console/stubs/migrations/create_laragram_users_table.stub' => database_path('migrations/create_laragram_users_table.php'),
                __DIR__ . '/../Console/stubs/migrations/create_laragram_sessions_table.stub' => database_path('migrations/create_laragram_sessions_table.php'),
            ], 'laragram-migrations');

            $this->publishes([
                __DIR__ . '/../Console/stubs/views/start.stub'          => resource_path(config('laragram.paths.views') . '/start/text.php'),
                __DIR__ . '/../Console/stubs/views/start_keyboard.stub' => resource_path(config('laragram.paths.views') . '/start/inline_keyboard.php'),
            ], 'laragram-views');

            $this->publishes([
                __DIR__ . '/../Console/stubs/lang/en/laragram.stub' => lang_path('en/laragram.php'),
            ], 'laragram-lang');

            $this->publishes([
                __DIR__ . '/../Console/stubs/routes/laragram.stub' => base_path('routes/' . config('laragram.paths.route') . '.php'),
                __DIR__ . '/../Console/stubs/routes/scenes.stub'    => base_path('routes/' . config('laragram.paths.scenes', 'laragram/scenes') . '.php'),
            ], 'laragram-routes');
        }
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
     * Register the named rate limiter used to throttle queued update jobs.
     *
     * Caps the global rate at which ProcessTelegramUpdate jobs run, keeping
     * outbound Bot API traffic under Telegram's ~30 msg/sec limit. Referenced by
     * the RateLimited job middleware as 'laragram'.
     *
     * @return void
     */
    protected function registerRateLimiter(): void
    {
        RateLimiter::for('laragram', function () {
            return Limit::perSecond((int) config('laragram.queue.rate_limit', 25));
        });
    }

    /**
     * Warn if webhook secret verification is enabled but no secret is configured.
     */
    protected function validateSecretConfig(): void
    {
        if ($this->config('security.verify_secret') && empty($this->config('telegram.secret'))) {
            $this->app['log']->warning(
                'laragram: verify_secret is enabled but LARAGRAM_WEBHOOK_SECRET is not set — ' .
                'all webhook requests will be rejected with 500. Set LARAGRAM_WEBHOOK_SECRET or disable with LARAGRAM_VERIFY_SECRET=false.'
            );
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
            BroadcastCommand::class,
            GetInfoCommand::class,
            WebhookSetCommand::class,
            WebhookRemoveCommand::class,
            LaragramInstallCommand::class,
            LaragramPublishCommand::class,
            MakeControllerCommand::class,
            MakeViewCommand::class,
            MakeSceneCommand::class,
            SceneListCommand::class,
            RouteListCommand::class,
            RouteMatchCommand::class,
            SessionPruneCommand::class,
            SetRoleCommand::class,
            PollCommand::class,
            WebhookInfoCommand::class,
        ]);
    }
}