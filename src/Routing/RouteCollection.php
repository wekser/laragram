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

use Illuminate\Support\Str;
use Wekser\Laragram\Exceptions\NotFoundRouteFileException;
use Wekser\Laragram\Exceptions\RouteActionInvalidException;
use Wekser\Laragram\Exceptions\RouteEventInvalidException;
use Wekser\Laragram\Facades\BotRoute;

/**
 * Fluent route builder used inside the bot's routes file.
 *
 * Usage (in routes/laragram.php):
 *
 *   $collection->get('message')
 *              ->from('start')
 *              ->contains('/start')
 *              ->call([StartController::class, 'index']);
 */
class RouteCollection
{
    /**
     * All Telegram update types supported by the Bot API.
     */
    private const DEFAULT_EVENTS = [
        'message',
        'edited_message',
        'channel_post',
        'edited_channel_post',
        'inline_query',
        'chosen_inline_result',
        'callback_query',
        'shipping_query',
        'pre_checkout_query',
        'poll',
        'poll_answer',
        'my_chat_member',
        'chat_member',
        'chat_join_request',
    ];

    /**
     * Default field within each update type that carries the matchable content.
     */
    public const DEFAULT_LISTENERS = [
        'message'              => 'text',
        'edited_message'       => 'text',
        'channel_post'         => 'text',
        'edited_channel_post'  => 'text',
        'inline_query'         => 'query',
        'chosen_inline_result' => 'result_id',
        'callback_query'       => 'data',
        'shipping_query'       => 'invoice_payload',
        'pre_checkout_query'   => 'invoice_payload',
        'poll'                 => 'question',
        'poll_answer'          => 'option_ids',
        'my_chat_member'       => 'from',
        'chat_member'          => 'from',
        'chat_join_request'    => 'from',
    ];

    /**
     * Regex used to extract {param} placeholders from a pattern.
     */
    private const PARAM_PATTERN = '/{(.*?)}/';

    /** @var array<string, mixed>  Route being assembled by the fluent chain. */
    private array $currentRoute = [];

    /** @var array<int, array<string, mixed>>  All completed routes. */
    private array $routes = [];

    /** @var array<string, mixed>  Defaults inherited by routes registered inside a group(). */
    private array $groupDefaults = [];

    // -------------------------------------------------------------------------
    // Fluent builder API
    // -------------------------------------------------------------------------

    /**
     * Register a route for the given Telegram event type.
     *
     * @param string      $event    One of the supported Telegram update types.
     * @param string|null $listener Override the default listener field.
     *
     * @throws RouteEventInvalidException
     */
    public function get(string $event, ?string $listener = null): static
    {
        if (!in_array($event, self::DEFAULT_EVENTS, true)) {
            throw new RouteEventInvalidException("Unsupported event: {$event}");
        }

        $this->currentRoute = array_merge($this->groupDefaults, [
            'event'    => $event,
            'listener' => $listener ?? self::DEFAULT_LISTENERS[$event] ?? null,
        ]);

        return $this;
    }

    /**
     * Restrict the route to users whose current station equals $name.
     * Accepts a single station string or an array of stations.
     *
     * @param string|string[] $name
     */
    public function from(string|array $name): static
    {
        $this->currentRoute['from'] = (array) $name;
        return $this;
    }

    /**
     * Assign a name to this route for display and introspection purposes.
     */
    public function name(string $name): static
    {
        $this->currentRoute['name'] = $name;
        return $this;
    }

    /**
     * Restrict the route to users with the given role(s).
     * Accepts a single role string or an array of roles.
     *
     * @param string|string[] $roles
     */
    public function role(string|array $roles): static
    {
        $this->currentRoute['roles'] = (array) $roles;
        return $this;
    }

    /**
     * Group routes sharing common constraints.
     *
     * Supports:
     *   - from:  pre-set station(s) for all routes in the group
     *   - roles: pre-set role(s) for all routes in the group
     *
     * Individual route calls to from() / role() still override the group defaults.
     *
     * @param callable             $callback Routes registered inside this closure inherit group defaults.
     * @param string|string[]|null $from     Station(s) to apply to every route in the group.
     * @param string|string[]|null $roles    Role(s) to apply to every route in the group.
     */
    public function group(callable $callback, string|array|null $from = null, string|array|null $roles = null): static
    {
        $saved = $this->groupDefaults;

        if ($from !== null) {
            $this->groupDefaults['from'] = (array) $from;
        }

        if ($roles !== null) {
            $this->groupDefaults['roles'] = (array) $roles;
        }

        $callback($this);

        $this->groupDefaults = $saved;

        return $this;
    }

    /**
     * Register a catch-all fallback route invoked when no other route matches.
     */
    public function fallback(): static
    {
        $this->currentRoute = [
            'event'    => '__fallback__',
            'listener' => '__none__',
            'fallback' => true,
        ];
        return $this;
    }

    /**
     * Match the route only when the incoming content matches the given pattern(s).
     *
     * @param string|string[] $pattern
     */
    public function contains(string|array $pattern): static
    {
        if (is_array($pattern)) {
            foreach ($pattern as $key => $value) {
                if (!empty($value)) {
                    $this->addPattern($value, $key);
                }
            }
        } else {
            $this->addPattern($pattern);
        }

        return $this;
    }

    /**
     * Attach the handler and finalise the route.
     *
     * @param array|callable $action  [ControllerClass::class, 'method'] or a Closure.
     *
     * @throws RouteActionInvalidException
     */
    public function call(array|callable $action): void
    {
        if (is_array($action)) {
            if (count($action) !== 2 || empty($action[0]) || empty($action[1])) {
                throw new RouteActionInvalidException(
                    'Controller action must be [ClassName::class, \'method\']'
                );
            }

            [$controller, $method] = $action;
            $this->currentRoute['controller'] = $controller;
            $this->currentRoute['method']     = $method;
        } elseif (is_callable($action)) {
            $this->currentRoute['callback'] = $action;
        } else {
            throw new RouteActionInvalidException('Route action must be a [class, method] array or a callable');
        }

        $this->routes[]     = $this->currentRoute;
        $this->currentRoute = [];
    }

    // -------------------------------------------------------------------------
    // Loading
    // -------------------------------------------------------------------------

    /**
     * Require the routes file and return all registered routes as plain arrays.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws NotFoundRouteFileException
     */
    public function collectRoutes(): array
    {
        $routeName = (string) config('laragram.paths.route');

        if (str_contains($routeName, '..') || str_contains($routeName, '/') || str_contains($routeName, '\\')) {
            throw new NotFoundRouteFileException("Invalid route name: {$routeName}");
        }

        $path = base_path("routes/{$routeName}.php");

        if (!is_file($path)) {
            throw new NotFoundRouteFileException($path);
        }

        $collection = $this;
        BotRoute::setInstance($this);

        try {
            require $path;
        } finally {
            BotRoute::clearInstance();
        }

        return $this->routes;
    }

    /**
     * Return all currently registered routes.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Clear all registered routes (useful in tests).
     */
    public function clearRoutes(): void
    {
        $this->routes        = [];
        $this->currentRoute  = [];
        $this->groupDefaults = [];
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private function addPattern(string $pattern, int $key = 0): void
    {
        $isCommand = Str::startsWith($pattern, '/');

        $this->currentRoute['contains'][$key] = [
            'pattern'    => $pattern,
            'params'     => $isCommand ? $this->extractParameters($pattern) : [],
            'is_command' => $isCommand,
        ];
    }

    private function extractParameters(string $pattern): array
    {
        preg_match_all(self::PARAM_PATTERN, $pattern, $matches);

        return array_map(
            static fn (string $match): string => trim($match, '?'),
            $matches[1] ?? []
        );
    }
}
