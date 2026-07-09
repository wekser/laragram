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
    // Skipping updates with no actionable sender
    // -------------------------------------------------------------------------

    public function test_does_not_process_update_from_bot(): void
    {
        $request = $this->requestWithSender([
            'id' => 456, 'first_name' => 'MyBot', 'is_bot' => true,
        ]);

        $processed = false;
        $this->middleware()->handle($request, function ($r) use (&$processed) {
            $processed = true;

            return response('ok');
        });

        $this->assertFalse($processed);
    }

    public function test_does_not_process_update_with_no_sender(): void
    {
        $request = Request::create('/', 'POST', ['update_id' => 1]);

        $processed = false;
        $this->middleware()->handle($request, function ($r) use (&$processed) {
            $processed = true;

            return response('ok');
        });

        $this->assertFalse($processed);
    }

    /**
     * Telegram redelivers any update the webhook answers with a non-2xx status,
     * so a routine bot-authored update must still be acknowledged.
     */
    public function test_acknowledges_update_from_bot_with_ok(): void
    {
        $request = $this->requestWithSender([
            'id' => 456, 'first_name' => 'MyBot', 'is_bot' => true,
        ]);

        $response = $this->middleware()->handle($request, fn ($r) => response('ok'));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_acknowledges_update_with_no_sender_with_ok(): void
    {
        $request = Request::create('/', 'POST', ['update_id' => 1]);

        $response = $this->middleware()->handle($request, fn ($r) => response('ok'));

        $this->assertSame(200, $response->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // Logging
    // -------------------------------------------------------------------------

    /**
     * A bot sitting in a channel or group receives bot-authored updates
     * continuously, so skipping one is routine and must never touch the log.
     */
    public function test_does_not_log_when_skipping_bot_update(): void
    {
        $handler = $this->attachLogHandler();

        $request = $this->requestWithSender([
            'id' => 456, 'first_name' => 'MyBot', 'is_bot' => true,
        ]);

        $this->middleware()->handle($request, fn ($r) => response('ok'));

        $this->assertSame([], $handler->getRecords());
    }

    public function test_does_not_log_when_skipping_senderless_update(): void
    {
        $handler = $this->attachLogHandler();

        $request = Request::create('/', 'POST', ['update_id' => 1]);

        $this->middleware()->handle($request, fn ($r) => response('ok'));

        $this->assertSame([], $handler->getRecords());
    }

    public function test_does_not_log_for_valid_request(): void
    {
        $handler = $this->attachLogHandler();

        $request = $this->requestWithSender([
            'id' => 1, 'first_name' => 'Mike', 'is_bot' => false,
        ]);

        $this->middleware()->handle($request, fn ($r) => response('ok'));

        $this->assertFalse($handler->hasDebugRecords());
    }
}
