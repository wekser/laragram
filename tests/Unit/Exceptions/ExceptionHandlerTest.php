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

namespace Wekser\Laragram\Tests\Unit\Exceptions;

use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Wekser\Laragram\Exceptions\AuthenticationException;
use Wekser\Laragram\Exceptions\BotBlockedException;
use Wekser\Laragram\Exceptions\ChatNotFoundException;
use Wekser\Laragram\Exceptions\ExceptionHandler;
use Wekser\Laragram\Exceptions\UserDeactivatedException;
use Wekser\Laragram\Tests\TestCase;

#[CoversClass(ExceptionHandler::class)]
class ExceptionHandlerTest extends TestCase
{
    // -------------------------------------------------------------------------
    // shouldReport()
    // -------------------------------------------------------------------------

    public function test_should_report_returns_true_for_generic_exception(): void
    {
        $method = new \ReflectionMethod(ExceptionHandler::class, 'shouldReport');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke(null, new RuntimeException('Something went wrong')));
    }

    public function test_should_report_returns_false_for_authentication_exception(): void
    {
        $method = new \ReflectionMethod(ExceptionHandler::class, 'shouldReport');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke(null, new AuthenticationException()));
    }

    public function test_should_report_returns_false_for_bot_blocked_exception(): void
    {
        $method = new \ReflectionMethod(ExceptionHandler::class, 'shouldReport');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke(null, new BotBlockedException(12345)));
    }

    public function test_should_report_returns_false_for_user_deactivated_exception(): void
    {
        $method = new \ReflectionMethod(ExceptionHandler::class, 'shouldReport');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke(null, new UserDeactivatedException(12345)));
    }

    public function test_should_report_returns_false_for_chat_not_found_exception(): void
    {
        $method = new \ReflectionMethod(ExceptionHandler::class, 'shouldReport');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke(null, new ChatNotFoundException('12345')));
    }

    // -------------------------------------------------------------------------
    // handle() — catches \Throwable (PHP Error subclasses)
    // -------------------------------------------------------------------------

    public function test_handle_accepts_throwable_error(): void
    {
        // \TypeError is a \Throwable but not an \Exception.
        // Before the fix, catch(Exception) would miss it.
        $error = new \TypeError('type mismatch');

        // handle() should not throw — it swallows and logs/renders.
        try {
            ExceptionHandler::handle($error);
            $this->assertTrue(true);
        } catch (\Throwable $t) {
            $this->fail('ExceptionHandler::handle() should not rethrow: ' . $t->getMessage());
        }
    }

    public function test_handle_does_not_report_silenced_exception_type(): void
    {
        $logged = false;

        // Swap the log channel with a spy.
        $this->app->singleton('log', function () use (&$logged) {
            return new class($logged) {
                public function __construct(private bool &$logged) {}

                public function error(mixed $message, array $context = []): void
                {
                    $this->logged = true;
                }
            };
        });

        ExceptionHandler::handle(new AuthenticationException());

        $this->assertFalse($logged, 'AuthenticationException must not be logged');
    }

    public function test_handle_reports_generic_exception(): void
    {
        $logged = false;

        $this->app->singleton('log', function () use (&$logged) {
            return new class($logged) {
                public function __construct(private bool &$logged) {}

                public function error(mixed $message, array $context = []): void
                {
                    $this->logged = true;
                }
            };
        });

        ExceptionHandler::handle(new RuntimeException('boom'));

        $this->assertTrue($logged, 'Generic RuntimeException should be logged');
    }

    public function test_handle_does_not_send_http_response_directly(): void
    {
        // handle() must NOT call response()->send() — the caller (Laragram::back())
        // is responsible for returning the HTTP response.
        // We verify this by checking that no output is buffered after handle().
        ob_start();
        ExceptionHandler::handle(new AuthenticationException());
        $output = ob_get_clean();

        $this->assertSame('', $output, 'handle() must not write to output buffer directly');
    }

    public function test_render_returns_response_object(): void
    {
        $method = new \ReflectionMethod(ExceptionHandler::class, 'render');
        $method->setAccessible(true);

        $response = $method->invoke(null);

        $this->assertInstanceOf(\Illuminate\Http\Response::class, $response);
    }
}
