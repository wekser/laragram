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
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Wekser\Laragram\Exceptions\NotFoundRouteFileException;
use Wekser\Laragram\Routing\RouteCollection;

/**
 * Debug command: show which route matches a simulated incoming update.
 *
 * Usage:
 *   php artisan laragram:route:match message "/start"
 *   php artisan laragram:route:match message "hello" --station=home
 *   php artisan laragram:route:match callback_query "/click ok" --station=home
 */
class RouteMatchCommand extends Command
{
    use FormatsRoutes;

    protected $signature = 'laragram:route:match
        {event : Telegram update type (message, callback_query, inline_query, etc.)}
        {text  : The text / data / query content to match against}
        {--station= : Current user station (default: start)}';

    protected $description = 'Show which bot route would match a given event, text, and station';


    public function handle(): int
    {
        $event   = (string) $this->argument('event');
        $text    = (string) $this->argument('text');
        $station = (string) ($this->option('station') ?? 'start');

        try {
            $routes = (new RouteCollection())->collectRoutes();
        } catch (NotFoundRouteFileException $e) {
            $this->error('Route file not found: ' . $e->getMessage());
            return self::FAILURE;
        }

        $listener = RouteCollection::DEFAULT_LISTENERS[$event] ?? 'text';
        $object   = [$listener => $text, 'from' => ['id' => 0]];

        $matched  = null;
        $fallback = null;

        foreach ($routes as $route) {
            if (!empty($route['fallback'])) {
                $fallback = $route;
                continue;
            }

            if ($this->matchesEvent($route, $event)
                && $this->matchesContent($route, $object, $station, $listener)) {
                $matched = $route;
                break;
            }
        }

        $effective = $matched ?? $fallback;

        $this->newLine();

        if ($effective === null) {
            $this->line('<fg=red>✗ No route matched.</>');
            $this->line("  Event:   {$event}");
            $this->line("  Text:    {$text}");
            $this->line("  Station: {$station}");
            $this->newLine();
            return self::FAILURE;
        }

        $isFallback = !empty($effective['fallback']);
        $handler    = $this->formatHandler($effective);

        $this->line('<fg=green>✓ ' . ($isFallback ? 'Fallback route matched' : 'Route matched') . '</>');
        $this->newLine();

        $this->table(['Field', 'Value'], [
            ['Event',   $event],
            ['Text',    $text],
            ['Station', $station],
            ['—', '—'],
            ['Route event',   $isFallback ? '[fallback]' : ($effective['event'] ?? '—')],
            ['Route station', $this->formatStation($effective['from'] ?? null, '* (any)')],
            ['Route contains', $this->formatContains($effective['contains'] ?? null, '* (any)')],
            ['Route roles',   $this->formatRoles($effective['roles'] ?? null, '* (any)')],
            ['Handler',       $handler],
        ]);

        if (!empty($effective['roles'])) {
            $this->newLine();
            $this->comment('Note: role check is skipped in this command — the route may still be rejected at runtime if the user lacks the required role.');
        }

        $this->newLine();
        return self::SUCCESS;
    }

    // -------------------------------------------------------------------------
    // Matching helpers (simplified mirror of Router — no user/role resolution)
    // -------------------------------------------------------------------------

    private function matchesEvent(array $route, string $type): bool
    {
        return ($route['event'] ?? null) === $type;
    }

    private function matchesContent(array $route, array $object, string $station, string $listener): bool
    {
        if (!Arr::has($object, $listener)) {
            return false;
        }

        $data     = Arr::get($object, $listener);
        $from     = $route['from'] ?? null;
        $contains = $route['contains'] ?? null;

        $noStationFilter = empty($from);
        $noContentFilter = empty($contains);
        $stationMatches  = !$noStationFilter && in_array($station, (array) $from, true);

        if ($noStationFilter && $noContentFilter) {
            return true;
        }

        if ($stationMatches && $noContentFilter) {
            return true;
        }

        if (!is_array($contains)) {
            return false;
        }

        foreach ($contains as $contain) {
            if ($this->matchesPattern($contain, $data, $stationMatches, $noStationFilter)) {
                return true;
            }
        }

        return false;
    }

    private function matchesPattern(
        array $contain,
        mixed $data,
        bool  $stationMatches,
        bool  $noStationFilter,
    ): bool {
        $isCommand = $contain['is_command'] ?? false;
        $pattern   = $contain['pattern']    ?? '';
        $hasParams = !empty($contain['params']);

        $commandMatch = $isCommand
            && Str::startsWith($data, '/')
            && Str::before($pattern, ' ') === Str::before($data, ' ');

        $paramMatch = Str::startsWith($pattern, '{') && $hasParams;
        $exactMatch = $pattern === $data;

        return ($noStationFilter && $commandMatch)
            || ($stationMatches  && $paramMatch)
            || ($stationMatches  && $exactMatch);
    }

}
