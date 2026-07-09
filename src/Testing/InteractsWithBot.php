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
use Wekser\Laragram\Http\ResponseDispatcher;
use Wekser\Laragram\Routing\Router;
use Wekser\Laragram\Scene\SceneManager;

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

    /**
     * Messages the bot sent during the last botReceives() call, in order.
     * Each entry is a Telegram payload of the form ['method' => ..., ...params].
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $sentMessages = [];

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

        $user       = $auth->user();
        $station    = 'start';
        $sceneState = null;

        if (config('laragram.auth.driver') === 'database' && !empty($session = $user->session($auth->chatId(), $auth->threadId()))) {
            $station    = $session->station;
            $sceneState = $session->payload['scene'] ?? null;
        }

        // Mirror Laragram::run(): a user inside a scene is handled by the scene
        // runtime; everyone else goes through the normal router.
        $output = str_starts_with($station, SceneManager::PREFIX)
            ? app(SceneManager::class)->continue($update, $station, $sceneState)
            : (new Router($station))->dispatch($update);

        $this->sentMessages = [];

        if (!empty($output)) {
            if (config('laragram.auth.driver') === 'database') {
                event(new CallbackFormed($user, $output));
            }

            // Deliver through the real dispatcher against a recording BotAPI, so
            // the messages the bot sends are captured exactly as in production.
            $api = new RecordingBotAPI();
            (new ResponseDispatcher($api))->send($output['response']['views'] ?? []);

            $this->sentMessages = array_map(
                static fn (array $call): array => array_merge(['method' => $call['method']], $call['params']),
                $api->calls,
            );
        }

        return $this->lastOutput = $output;
    }

    // -------------------------------------------------------------------------
    // Assertions
    // -------------------------------------------------------------------------

    /**
     * Assert the bot sent a response using the given Telegram API method.
     * Checks the first message sent.
     *
     * @param string $method e.g. 'sendMessage', 'sendPhoto'
     */
    protected function assertBotRepliedWith(string $method): void
    {
        $this->assertNthReplyWith(0, $method);
    }

    /**
     * Assert the bot produced no response (route returned null or no match).
     */
    protected function assertNoResponse(): void
    {
        $this->assertEmpty(
            $this->sentMessages,
            'Expected no response, but bot replied with: ' .
            ($this->sentMessages[0]['method'] ?? 'unknown')
        );
    }

    /**
     * Assert the forum topic the first sent message was addressed to.
     * Pass null to assert it carries no message_thread_id (General topic).
     */
    protected function assertBotRepliedInThread(?int $threadId): void
    {
        $this->assertNotEmpty($this->sentMessages, 'Bot sent no messages.');

        $actual = $this->sentMessages[0]['message_thread_id'] ?? null;

        $this->assertSame(
            $threadId,
            $actual === null ? null : (int) $actual,
            $threadId === null
                ? "Expected the reply to carry no thread, got topic {$actual}."
                : "Expected the reply in topic {$threadId}, got " . ($actual ?? 'none') . '.'
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
     * Checks the first message sent.
     */
    protected function assertBotRepliedText(string $expected): void
    {
        $this->assertNthReplyText(0, $expected);
    }

    /**
     * Assert the response contains a specific key/value pair in the payload.
     * Checks the first message sent.
     *
     * Example: assertResponseContains('parse_mode', 'MarkdownV2')
     */
    protected function assertResponseContains(string $key, mixed $value): void
    {
        $actual = $this->messageAt(0)[$key] ?? null;

        $this->assertSame(
            $value,
            $actual,
            "Expected response to contain [{$key} = {$value}], got [{$actual}]."
        );
    }

    /**
     * Assert the bot sent exactly the given number of messages.
     */
    protected function assertBotRepliedTimes(int $count): void
    {
        $this->assertCount(
            $count,
            $this->sentMessages,
            "Expected bot to send [{$count}] message(s), sent [" . count($this->sentMessages) . '].'
        );
    }

    /**
     * Assert the Nth sent message (0-based) used the given Telegram API method.
     */
    protected function assertNthReplyWith(int $index, string $method): void
    {
        $actual = $this->messageAt($index)['method'] ?? null;

        $this->assertSame(
            $method,
            $actual,
            "Expected message [{$index}] to use [{$method}], got [{$actual}]."
        );
    }

    /**
     * Assert the Nth sent message (0-based) text contains the given string.
     */
    protected function assertNthReplyText(int $index, string $expected): void
    {
        $actual = $this->messageAt($index)['text'] ?? '';

        $this->assertStringContainsString(
            $expected,
            (string) $actual,
            "Expected message [{$index}] text to contain [{$expected}]."
        );
    }

    /**
     * Return the sent message at the given index, failing if none was sent there.
     *
     * @return array<string, mixed>
     */
    private function messageAt(int $index): array
    {
        $this->assertArrayHasKey(
            $index,
            $this->sentMessages,
            "Expected a message at index [{$index}], but only " . count($this->sentMessages) . ' were sent.'
        );

        return $this->sentMessages[$index];
    }

    /**
     * Assert the user is inside the given scene after the last update.
     */
    protected function assertInScene(string $name): void
    {
        $this->assertNotNull($this->lastOutput, 'Bot did not produce a response.');

        $actual = $this->lastOutput['scene']['name'] ?? null;

        $this->assertSame(
            $name,
            $actual,
            "Expected user to be in scene [{$name}], got [" . ($actual ?? 'none') . '].'
        );

        $this->assertSame(
            SceneManager::PREFIX . $name,
            $this->lastOutput['response']['redirect'] ?? null,
            "Expected station [@scene:{$name}] while in the scene."
        );
    }

    /**
     * Assert the user is at the given step of the current scene.
     */
    protected function assertSceneStep(string $step): void
    {
        $this->assertNotNull($this->lastOutput, 'Bot did not produce a response.');

        $actual = $this->lastOutput['scene']['step'] ?? null;

        $this->assertSame(
            $step,
            $actual,
            "Expected scene step [{$step}], got [" . ($actual ?? 'none') . '].'
        );
    }

    /**
     * Assert an answer collected so far in the current scene equals $value.
     */
    protected function assertSceneData(string $key, mixed $value): void
    {
        $this->assertNotNull($this->lastOutput, 'Bot did not produce a response.');

        $actual = $this->lastOutput['scene']['data'][$key] ?? null;

        $this->assertSame(
            $value,
            $actual,
            "Expected scene data [{$key}] to equal the given value."
        );
    }

    /**
     * Assert the user is not inside any scene after the last update.
     */
    protected function assertNotInScene(): void
    {
        $scene = $this->lastOutput['scene'] ?? null;

        $this->assertNull(
            $scene,
            'Expected user not to be in a scene, but is in [' . ($scene['name'] ?? 'unknown') . '].'
        );
    }

    /**
     * Return the first sent message payload (['method' => ..., ...params]).
     *
     * @return array<string, mixed>
     */
    protected function getBotResponse(): array
    {
        return $this->sentMessages[0] ?? [];
    }

    /**
     * Return all sent message payloads from the last botReceives() call.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getBotResponses(): array
    {
        return $this->sentMessages;
    }
}
