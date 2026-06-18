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

namespace Wekser\Laragram\Testing;

use Illuminate\Http\Request;
use Wekser\Laragram\BotAuth;
use Wekser\Laragram\Events\CallbackFormed;
use Wekser\Laragram\Exceptions\AuthenticationException;
use Wekser\Laragram\Routing\Router;

/**
 * PHPUnit trait for feature-testing Laragram bots without a real HTTP request.
 *
 * Usage in your test class:
 *
 *   use Wekser\Laragram\Testing\InteractsWithBot;
 *
 *   class StartCommandTest extends TestCase
 *   {
 *       use InteractsWithBot;
 *
 *       public function test_start_command_replies_with_welcome(): void
 *       {
 *           $this->botReceives(BotUpdateFactory::message('/start'));
 *
 *           $this->assertBotRepliedWith('sendMessage');
 *           $this->assertUserRedirectedTo('home');
 *       }
 *   }
 */
trait InteractsWithBot
{
    /** Full output array from the last botReceives() call. */
    protected ?array $lastOutput = null;

    // -------------------------------------------------------------------------
    // Dispatch helper
    // -------------------------------------------------------------------------

    /**
     * Process a Telegram update through the full Laragram pipeline
     * (auth → router → session event), without HTTP middleware.
     *
     * Returns the raw output array or null when no route matched / no response.
     *
     * @param array<string, mixed> $update
     * @return array<string, mixed>|null
     */
    protected function botReceives(array $update): ?array
    {
        $syntheticRequest = Request::create('/', 'POST', $update);

        $auth = new BotAuth(
            $syntheticRequest,
            config('laragram.auth.driver'),
            (array) config('laragram.bot.languages', ['en']),
            config('laragram.auth.user.model'),
        );

        try {
            $auth->authenticate();
        } catch (AuthenticationException) {
            return $this->lastOutput = null;
        }

        // Rebind so facades (BotAuth::user(), BotResponse) resolve the right instance
        app()->instance('laragram.auth', $auth);

        $user    = $auth->user();
        $station = (config('laragram.auth.driver') !== 'database')
            ? 'start'
            : (empty($session = $user->session()) ? 'start' : $session->station);

        $output = (new Router($station))->dispatch($update);

        if (!empty($output) && config('laragram.auth.driver') === 'database') {
            event(new CallbackFormed($user, $output));
        }

        return $this->lastOutput = $output;
    }

    // -------------------------------------------------------------------------
    // Assertions
    // -------------------------------------------------------------------------

    /**
     * Assert the bot sent a response using the given Telegram API method.
     *
     * @param string $method e.g. 'sendMessage', 'sendPhoto'
     */
    protected function assertBotRepliedWith(string $method): void
    {
        $this->assertNotNull(
            $this->lastOutput,
            "Expected bot to reply with [{$method}], but no response was produced."
        );

        $actual = $this->lastOutput['response']['view']['method'] ?? null;

        $this->assertSame(
            $method,
            $actual,
            "Expected bot to reply with [{$method}], got [{$actual}]."
        );
    }

    /**
     * Assert the bot produced no response (route returned null or no match).
     */
    protected function assertNoResponse(): void
    {
        $this->assertNull(
            $this->lastOutput,
            'Expected no response, but bot replied with: ' .
            ($this->lastOutput['response']['view']['method'] ?? 'unknown')
        );
    }

    /**
     * Assert the next station written to the session.
     */
    protected function assertUserRedirectedTo(string $station): void
    {
        $this->assertNotNull($this->lastOutput, 'Bot did not produce a response.');

        $actual = $this->lastOutput['response']['redirect'] ?? null;

        $this->assertSame(
            $station,
            $actual,
            "Expected redirect to station [{$station}], got [{$actual}]."
        );
    }

    /**
     * Assert the response text contains the given string (after any escaping).
     */
    protected function assertBotRepliedText(string $expected): void
    {
        $this->assertNotNull($this->lastOutput, 'Bot did not produce a response.');

        $actual = $this->lastOutput['response']['view']['text'] ?? '';

        $this->assertStringContainsString(
            $expected,
            (string) $actual,
            "Expected response text to contain [{$expected}]."
        );
    }

    /**
     * Assert the response contains a specific key/value pair in the view payload.
     *
     * Example: assertResponseContains('parse_mode', 'MarkdownV2')
     */
    protected function assertResponseContains(string $key, mixed $value): void
    {
        $this->assertNotNull($this->lastOutput, 'Bot did not produce a response.');

        $actual = $this->lastOutput['response']['view'][$key] ?? null;

        $this->assertSame(
            $value,
            $actual,
            "Expected response to contain [{$key} = {$value}], got [{$actual}]."
        );
    }

    /**
     * Return the full response view payload from the last botReceives() call.
     *
     * @return array<string, mixed>
     */
    protected function getBotResponse(): array
    {
        return $this->lastOutput['response']['view'] ?? [];
    }
}
