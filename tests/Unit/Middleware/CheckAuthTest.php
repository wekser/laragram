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
use Wekser\Laragram\Middleware\CheckAuth;
use Wekser\Laragram\Tests\TestCase;

#[CoversClass(CheckAuth::class)]
class CheckAuthTest extends TestCase
{
    private function middleware(): CheckAuth
    {
        return new CheckAuth();
    }

    private function requestWithSender(array $sender): Request
    {
        return Request::create('/', 'POST', [
            'update_id' => 1,
            'message' => [
                'from' => $sender,
                'text' => 'hello',
            ],
        ]);
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

    public function test_passes_request_from_human_user(): void
    {
        $request = $this->requestWithSender([
            'id' => 123, 'first_name' => 'Mike', 'is_bot' => false,
        ]);

        $response = $this->middleware()->handle($request, fn ($r) => response('ok'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', $response->getContent());
    }

    // -------------------------------------------------------------------------
    // Rejecting requests
    // -------------------------------------------------------------------------

    public function test_rejects_request_from_bot(): void
    {
        $request = $this->requestWithSender([
            'id' => 456, 'first_name' => 'MyBot', 'is_bot' => true,
        ]);

        $response = $this->middleware()->handle($request, fn ($r) => response('ok'));

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_rejects_request_with_no_sender(): void
    {
        $request = Request::create('/', 'POST', ['update_id' => 1]);

        $response = $this->middleware()->handle($request, fn ($r) => response('ok'));

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_rejection_response_is_json_with_message_key(): void
    {
        $request = Request::create('/', 'POST', ['update_id' => 1]);

        $response = $this->middleware()->handle($request, fn ($r) => response('ok'));

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('message', $data);
    }

    // -------------------------------------------------------------------------
    // Security logging
    // -------------------------------------------------------------------------

    public function test_logs_warning_when_rejecting_bot_request(): void
    {
        $handler = $this->attachLogHandler();

        $request = $this->requestWithSender([
            'id' => 456, 'first_name' => 'MyBot', 'is_bot' => true,
        ]);

        $this->middleware()->handle($request, fn ($r) => response('ok'));

        $this->assertTrue($handler->hasWarningRecords());
    }

    public function test_logs_warning_when_rejecting_no_sender_request(): void
    {
        $handler = $this->attachLogHandler();

        $request = Request::create('/', 'POST', ['update_id' => 1]);

        $this->middleware()->handle($request, fn ($r) => response('ok'));

        $this->assertTrue($handler->hasWarningRecords());
    }

    public function test_does_not_log_for_valid_request(): void
    {
        $handler = $this->attachLogHandler();

        $request = $this->requestWithSender([
            'id' => 1, 'first_name' => 'Mike', 'is_bot' => false,
        ]);

        $this->middleware()->handle($request, fn ($r) => response('ok'));

        $this->assertFalse($handler->hasWarningRecords());
    }
}
