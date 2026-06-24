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

namespace Wekser\Laragram\Events;

/**
 * Fired by ExceptionHandler every time a Throwable is swallowed.
 *
 * Laragram never lets an exception escape update processing — routing, delivery
 * and the queued job all funnel their errors through ExceptionHandler::handle(),
 * which logs reportable ones and silences user-unreachable ones. That makes
 * silently-handled failures invisible to operators (they never reach the
 * failed_jobs table). This event is the observability seam: bind a listener to
 * push to your metrics/alerting (Sentry, StatsD, Horizon tags, …).
 *
 * - $reportable mirrors ExceptionHandler::shouldReport() — false for the
 *   user-unreachable conditions (blocked, deactivated, chat gone, unauthenticated).
 * - $terminal is the inverse: true when the user cannot receive messages, which
 *   is itself a useful product metric (e.g. how many users blocked the bot).
 *
 * Listening is optional — with no listener bound this is a near-zero-cost no-op.
 */
class BotExceptionHandled
{
    /**
     * @param \Throwable $exception  The handled (swallowed) throwable.
     * @param bool       $reportable Whether it was logged (reportable) or silenced.
     * @param bool       $terminal   Whether it means the user is unreachable.
     */
    public function __construct(
        public readonly \Throwable $exception,
        public readonly bool       $reportable,
        public readonly bool       $terminal,
    ) {}
}
