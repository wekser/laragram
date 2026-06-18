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

namespace Wekser\Laragram\Tests\Unit\Middleware;

use Illuminate\Http\Request;
use Monolog\Handler\TestHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use Wekser\Laragram\Middleware\VerifyTelegramSecret;
use Wekser\Laragram\Tests\TestCase;

#[CoversClass(VerifyTelegramSecret::class)]
class VerifyTelegramSecretTest extends TestCase
{
    private const SECRET = 'my-secret-token';

    private function middleware(): VerifyTelegramSecret
    {
        return new VerifyTelegramSecret();
    }

    private function requestWithHeader(string $secret): Request
    {
        $request = Request::create('/', 'POST');
        $request->headers->set('X-Telegram-Bot-Api-Secret-Token', $secret);

        return $request;
    }

    private function attachLogHandler(): TestHandler
    {
        $handler = new TestHandler();
        app('log')->getLogger()->pushHandler($handler);

        return $handler;
    }

    // -------------------------------------------------------------------------
    // Passing requests
    // -------------------------------------------------------------------------

    public function test_passes_when_verification_is_disabled(): void
    {
        $this->app['config']->set('laragram.security.verify_secret', false);

        $response = $this->middleware()->handle(
            Request::create('/', 'POST'),
            fn ($r) => response('ok'),
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_passes_when_correct_secret_is_provided(): void
    {
        $this->app['config']->set('laragram.security.verify_secret', true);
        $this->app['config']->set('laragram.telegram.secret', self::SECRET);

        $response = $this->middleware()->handle(
            $this->requestWithHeader(self::SECRET),
            fn ($r) => response('ok'),
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // Rejecting requests
    // -------------------------------------------------------------------------

    public function test_rejects_when_no_secret_header_is_sent(): void
    {
        $this->app['config']->set('laragram.security.verify_secret', true);
        $this->app['config']->set('laragram.telegram.secret', self::SECRET);

        $response = $this->middleware()->handle(
            Request::create('/', 'POST'),
            fn ($r) => response('ok'),
        );

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_rejects_when_wrong_secret_is_provided(): void
    {
        $this->app['config']->set('laragram.security.verify_secret', true);
        $this->app['config']->set('laragram.telegram.secret', self::SECRET);

        $response = $this->middleware()->handle(
            $this->requestWithHeader('wrong-secret'),
            fn ($r) => response('ok'),
        );

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_rejects_with_500_when_expected_secret_is_empty(): void
    {
        $this->app['config']->set('laragram.security.verify_secret', true);
        $this->app['config']->set('laragram.telegram.secret', '');

        $response = $this->middleware()->handle(
            $this->requestWithHeader('any-secret'),
            fn ($r) => response('ok'),
        );

        // Empty secret is a misconfiguration, not a bad request — return 500.
        $this->assertSame(500, $response->getStatusCode());
    }

    public function test_rejection_response_is_json_with_message_key(): void
    {
        $this->app['config']->set('laragram.security.verify_secret', true);
        $this->app['config']->set('laragram.telegram.secret', self::SECRET);

        $response = $this->middleware()->handle(
            $this->requestWithHeader('wrong'),
            fn ($r) => response('ok'),
        );

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('message', $data);
    }

    // -------------------------------------------------------------------------
    // Security logging
    // -------------------------------------------------------------------------

    public function test_logs_warning_on_invalid_secret(): void
    {
        $handler = $this->attachLogHandler();

        $this->app['config']->set('laragram.security.verify_secret', true);
        $this->app['config']->set('laragram.telegram.secret', self::SECRET);

        $this->middleware()->handle(
            $this->requestWithHeader('wrong'),
            fn ($r) => response('ok'),
        );

        $this->assertTrue($handler->hasWarningRecords());
    }

    public function test_logs_warning_when_no_header_sent(): void
    {
        $handler = $this->attachLogHandler();

        $this->app['config']->set('laragram.security.verify_secret', true);
        $this->app['config']->set('laragram.telegram.secret', self::SECRET);

        $this->middleware()->handle(
            Request::create('/', 'POST'),
            fn ($r) => response('ok'),
        );

        $this->assertTrue($handler->hasWarningRecords());
    }

    public function test_does_not_log_for_valid_request(): void
    {
        $handler = $this->attachLogHandler();

        $this->app['config']->set('laragram.security.verify_secret', true);
        $this->app['config']->set('laragram.telegram.secret', self::SECRET);

        $this->middleware()->handle(
            $this->requestWithHeader(self::SECRET),
            fn ($r) => response('ok'),
        );

        $this->assertFalse($handler->hasWarningRecords());
    }
}
