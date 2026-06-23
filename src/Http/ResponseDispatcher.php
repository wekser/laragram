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

namespace Wekser\Laragram\Http;

use Wekser\Laragram\BotAPI;
use Wekser\Laragram\Exceptions\ExceptionHandler;

/**
 * Sends the view payloads produced by ResponseTransformer to Telegram.
 *
 * Every message — whether a single reply or one of several — is delivered as a
 * separate outbound Bot API call. The webhook itself always answers 'OK' 200;
 * it never carries a message in its body. The same dispatcher is used by both
 * the webhook entry point (Laragram) and long-polling (PollCommand), so message
 * delivery behaves identically in either mode.
 */
class ResponseDispatcher
{
    public function __construct(private readonly BotAPI $api) {}

    /**
     * Send a list of view payloads in order.
     *
     * Each payload carries the Telegram method name under 'method'; the remaining
     * keys are the API parameters. The 'method' routing key and any internal
     * bookkeeping keys (prefixed with '_', e.g. '_escaped') are stripped before
     * the call, so no junk param is ever sent to Telegram.
     *
     * Delivery is best-effort and resilient: a failure on one message is logged
     * via ExceptionHandler and the batch continues — unless the failure means the
     * user is unreachable (blocked, deactivated, chat gone), in which case the
     * remaining messages are skipped.
     *
     * @param array<int, array<string, mixed>> $views
     */
    public function send(array $views): void
    {
        foreach ($views as $view) {
            if (empty($view['method'])) {
                continue;
            }

            $method = $view['method'];
            $params = array_filter(
                $view,
                static fn (string $key): bool => $key !== 'method' && !str_starts_with($key, '_'),
                ARRAY_FILTER_USE_KEY
            );

            try {
                $this->api->{$method}($params);
            } catch (\Throwable $exception) {
                ExceptionHandler::handle($exception);

                if (ExceptionHandler::isTerminal($exception)) {
                    break;
                }
            }
        }
    }
}
