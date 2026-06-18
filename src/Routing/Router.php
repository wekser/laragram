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

namespace Wekser\Laragram\Routing;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Wekser\Laragram\BotRequest;
use Wekser\Laragram\BotResponse;
use Wekser\Laragram\Exceptions\NotExistMethodException;
use Wekser\Laragram\Exceptions\NotExistsControllerException;
use Wekser\Laragram\Http\RequestTransformer;
use Wekser\Laragram\Http\ResponseTransformer;

/**
 * Finds and executes the bot route that matches the incoming Telegram update.
 */
class Router
{
    /** Update type detected from the incoming payload (e.g. 'message'). */
    protected string $type = '';

    /** The BotRequest built for the matched route. */
    protected ?BotRequest $request = null;

    /** Cached routes — loaded once per process, reset between tests via flushCache(). */
    private static ?array $cachedRoutes = null;

    public function __construct(protected readonly string $station) {}

    /**
     * Flush the route cache (useful in tests and after route file changes).
     */
    public static function flushCache(): void
    {
        self::$cachedRoutes = null;
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Dispatch the update payload to the matching route and return the response.
     *
     * @param array<string, mixed> $update Raw Telegram update array.
     * @return array<string, mixed>|null
     */
    public function dispatch(array $update): ?array
    {
        $this->getType($update);

        return $this->runRoute($update, $this->findRoute($update, $this->station));
    }

    /**
     * Find the first route that matches the update, station, and content.
     *
     * @param array<string, mixed> $update
     * @param string               $station
     * @return array<string, mixed>|null
     */
    public function findRoute(array $update, string $station): ?array
    {
        $object   = $update[$this->type] ?? [];
        $routes   = self::$cachedRoutes ??= (new RouteCollection())->collectRoutes();
        $fallback = null;

        foreach ($routes as $route) {
            if (!empty($route['fallback'])) {
                $fallback = $route;
                continue;
            }

            if ($this->matchesEvent($route) && $this->matchesRole($route) && $this->matchesContent($route, $object, $station)) {
                return $route;
            }
        }

        return $fallback;
    }

    // -------------------------------------------------------------------------
    // Dispatch helpers
    // -------------------------------------------------------------------------

    /**
     * Detect the update type from the payload.
     *
     * Telegram updates always contain exactly one type-specific key besides 'update_id'.
     * We prefer keys with 'from' (most types), then fall back to any array key that
     * isn't 'update_id' — this covers poll, poll_answer, and future types.
     */
    protected function getType(array $update): void
    {
        // First pass: prefer types with 'from' (most common)
        foreach ($update as $key => $value) {
            if ($key !== 'update_id' && is_array($value) && isset($value['from'])) {
                $this->type = (string) $key;
                return;
            }
        }

        // Second pass: any non-scalar key besides 'update_id' (poll, poll_answer, etc.)
        foreach ($update as $key => $value) {
            if ($key !== 'update_id' && is_array($value)) {
                $this->type = (string) $key;
                return;
            }
        }

        $this->type = '';
    }

    /**
     * @throws NotExistsControllerException
     * @throws NotExistMethodException
     */
    protected function runRoute(array $update, ?array $route): ?array
    {
        if ($route === null) {
            return null;
        }

        $botRequest = $this->prepareRequest($update, $route);

        if (isset($route['callback'])) {
            return $this->prepareResponse(call_user_func($route['callback'], $botRequest));
        }

        $controller = '\\' . $route['controller'];
        $method     = $route['method'];

        if (!class_exists($controller)) {
            throw new NotExistsControllerException($route['controller']);
        }

        if (!method_exists($controller, $method)) {
            throw new NotExistMethodException($route['method'], $route['controller']);
        }

        return $this->prepareResponse(app($controller)->$method($botRequest));
    }

    protected function prepareRequest(array $update, array $route): BotRequest
    {
        return $this->request = (new RequestTransformer($this->type, $update))
            ->build($route, $this->station);
    }

    protected function prepareResponse(BotResponse|string|null $response): ?array
    {
        return (new ResponseTransformer())->getResponse($this->request, $response);
    }

    // -------------------------------------------------------------------------
    // Route-matching helpers
    // -------------------------------------------------------------------------

    private function matchesEvent(array $route): bool
    {
        return ($route['event'] ?? null) === $this->type;
    }

    private function matchesRole(array $route): bool
    {
        $roles = $route['roles'] ?? null;

        if (empty($roles)) {
            return true;
        }

        $user = app('laragram.auth')->user();

        if ($user === null) {
            return false;
        }

        return $user->hasRole($roles);
    }

    private function matchesContent(array $route, array $object, string $station): bool
    {
        $listener = $route['listener'] ?? null;

        if (!Arr::has($object, $listener)) {
            return false;
        }

        $data     = Arr::get($object, $listener);
        $from     = $route['from'] ?? null;  // string[]|null after RouteCollection normalises
        $contains = $route['contains'] ?? null;

        $noStationFilter = empty($from);
        $noContentFilter = empty($contains);
        $stationMatches  = !$noStationFilter && in_array($station, $from, true);

        if ($noStationFilter && $noContentFilter) {
            return true;
        }

        if ($stationMatches && $noContentFilter) {
            return true;
        }

        if (!is_array($contains)) {
            return false;
        }

        return $this->matchesPatterns($contains, $data, $stationMatches, $noStationFilter);
    }

    private function matchesPatterns(
        array $contains,
        mixed $data,
        bool  $stationMatches,
        bool  $noStationFilter,
    ): bool {
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
