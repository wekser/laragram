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

namespace Wekser\Laragram\Console;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Wekser\Laragram\BotAuth;
use Wekser\Laragram\Events\CallbackFormed;
use Wekser\Laragram\Exceptions\AuthenticationException;
use Wekser\Laragram\Exceptions\ExceptionHandler;
use Wekser\Laragram\Http\ResponseDispatcher;
use Wekser\Laragram\Routing\Router;

/**
 * Long-polling command: fetches updates from Telegram via getUpdates
 * and processes each one through the same Router pipeline used by the webhook.
 *
 * Requires removing the webhook first — Telegram does not allow both at once.
 */
class PollCommand extends Command
{
    protected $signature = 'laragram:poll
        {--timeout=25  : Long-poll timeout in seconds passed to getUpdates}
        {--limit=100   : Maximum updates per getUpdates call}
        {--once        : Process one batch then exit (useful in tests)}
        {--no-confirm  : Skip the webhook removal confirmation}';

    protected $description = 'Start long-polling for Telegram updates (development mode)';

    public function handle(): int
    {
        if (!$this->option('no-confirm')) {
            if (!$this->confirm('Long polling requires the webhook to be removed. Continue?', true)) {
                return self::FAILURE;
            }

            $this->removeWebhook();
        }

        $this->info('Laragram long-poll started. Press Ctrl+C to stop.');

        $offset = 0;

        while (true) {
            try {
                $updates = $this->fetchUpdates($offset);
            } catch (\Throwable $e) {
                $this->warn('getUpdates failed: ' . $e->getMessage() . ' — retrying in 5s.');
                sleep(5);
                continue;
            }

            foreach ($updates as $update) {
                $this->processUpdate($update);
                $offset = $update['update_id'] + 1;
            }

            if ($this->option('once')) {
                break;
            }
        }

        $this->info('Long-poll stopped.');

        return self::SUCCESS;
    }

    private function removeWebhook(): void
    {
        try {
            /** @var \Wekser\Laragram\BotAPI $api */
            $api = app('laragram.api');
            $api->getClient()->setTimeout(15);
            $api->deleteWebhook(['drop_pending_updates' => false]);
            $this->info('Webhook removed.');
        } catch (\Throwable $e) {
            $this->warn('Could not remove webhook: ' . $e->getMessage());
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchUpdates(int $offset): array
    {
        /** @var \Wekser\Laragram\BotAPI $api */
        $api = app('laragram.api');

        // Long-poll timeout requires the cURL read timeout to be longer
        $pollTimeout = (int) $this->option('timeout');
        $api->getClient()->setTimeout($pollTimeout + 10);

        $result = $api->getUpdates([
            'offset'  => $offset,
            'limit'   => (int) $this->option('limit'),
            'timeout' => $pollTimeout,
        ]);

        return is_array($result) ? $result : [];
    }

    private function processUpdate(array $update): void
    {
        try {
            // Skip updates from bots
            foreach ($update as $value) {
                if (is_array($value) && ($value['from']['is_bot'] ?? false)) {
                    return;
                }
            }

            // Build a synthetic HTTP request from the update payload
            $syntheticRequest = Request::create('/', 'POST', $update);

            // Authenticate fresh per update (the singleton is for HTTP context only)
            $auth = new BotAuth(
                $syntheticRequest,
                config('laragram.auth.driver'),
                (array) config('laragram.bot.languages', ['en']),
                config('laragram.auth.user.model'),
            );
            $auth->authenticate();

            // Rebind the singleton so facades (BotAuth::user(), BotResponse) work in controllers
            app()->instance('laragram.auth', $auth);

            $user = $auth->user();

            // Set locale from user settings
            $locale = $user->settings->get('language', config('app.locale'));
            app('translator')->setLocale($locale ?? 'en');

            // Resolve station for the originating chat + forum topic (per-conversation state)
            $station = (config('laragram.auth.driver') !== 'database')
                ? 'start'
                : (empty($session = $user->session($auth->chatId(), $auth->threadId())) ? 'start' : $session->station);

            // Dispatch through router
            $output = (new Router($station))->dispatch($update);

            // Persist session via event (same as Laragram::fireEvent())
            if (!empty($output) && config('laragram.auth.driver') === 'database') {
                event(new CallbackFormed($user, $output));
            }

            // Deliver responses as outbound Bot API calls (same as Laragram::deliver()).
            // Unlike the webhook, polling has no HTTP response to carry a message,
            // so this is the only delivery path in poll mode.
            if (!empty($output)) {
                app(ResponseDispatcher::class)->send($output['response']['views'] ?? []);
            }

            $this->line('  Processed update_id=' . $update['update_id']);
        } catch (AuthenticationException) {
            // Silently skip — no sender, already-handled bot rejection, etc.
        } catch (\Throwable $e) {
            ExceptionHandler::handle($e);
            $this->warn('  Error on update_id=' . ($update['update_id'] ?? '?') . ': ' . $e->getMessage());
        }
    }
}
