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

namespace Wekser\Laragram\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Wekser\Laragram\Models\Session;
use Wekser\Laragram\Models\User;
use Wekser\Laragram\Providers\LaragramServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [LaragramServiceProvider::class];
    }

    /**
     * Point base_path() to tests/Fixtures so that:
     *   base_path('routes/laragram.php') → tests/Fixtures/routes/laragram.php
     *   resource_path('laragram/...')    → tests/Fixtures/resources/laragram/...
     *
     * Testbench 10.x uses applicationBasePath() (static) — not getBasePath().
     */
    public static function applicationBasePath(): string
    {
        return __DIR__ . '/Fixtures';
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Needed by the "web" middleware group (EncryptCookies) exercised by the
        // admin-panel feature tests; harmless for the rest of the suite.
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $app['config']->set('laragram.telegram.token', '123456789:TestTokenABCDEFGHIJKLMNOPQRSTUVWXYZ');
        $app['config']->set('laragram.telegram.prefix', 'laragram');
        $app['config']->set('laragram.telegram.secret', null);
        $app['config']->set('laragram.auth.driver', 'array');
        $app['config']->set('laragram.auth.session.lifetime', 10080);
        $app['config']->set('laragram.auth.session.model', Session::class);
        $app['config']->set('laragram.auth.session.table', 'laragram_sessions');
        $app['config']->set('laragram.auth.user.model', User::class);
        $app['config']->set('laragram.auth.user.table', 'laragram_users');
        $app['config']->set('laragram.bot.languages', ['en']);
        $app['config']->set('laragram.paths.route', 'laragram/routes');
        $app['config']->set('laragram.paths.views', 'laragram');
        $app['config']->set('laragram.paths.scenes', 'laragram/scenes');
        $app['config']->set('laragram.rate.max_attempts', 60);
        $app['config']->set('laragram.rate.decay_seconds', 60);
        $app['config']->set('laragram.security.verify_secret', false);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Override the auth singleton AFTER providers are booted so our stub
        // replaces the real binding before any test code resolves it.
        // BotAuth::authenticate() normally requires a live Telegram request,
        // which is not available in tests.
        $this->bindAuthStub();
    }

    /**
     * Bind the default auth stub (no authenticated user).
     * Override bindAuthStub() in subclasses to provide a specific user.
     */
    protected function bindAuthStub(?User $user = null): void
    {
        $this->app->singleton('laragram.auth', static function () use ($user) {
            return new class($user) {
                public function __construct(private readonly ?User $resolvedUser) {}

                public function user(): ?User
                {
                    return $this->resolvedUser;
                }

                public function chat(): ?array
                {
                    return null;
                }

                public function chatId(): ?int
                {
                    return null;
                }

                public function chatType(): ?string
                {
                    return null;
                }

                public function authenticate(): static
                {
                    return $this;
                }

                public function getDriver(): string
                {
                    return 'array';
                }
            };
        });
    }
}
