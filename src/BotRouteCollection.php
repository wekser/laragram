<?php

/*
 * This file is part of Laragram.
 *
 * (c) Sergey Lapin <me@wekser.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Wekser\Laragram;

use Illuminate\Support\Str;
use Wekser\Laragram\Exceptions\NotFoundRouteFileException;
use Wekser\Laragram\Exceptions\RouteActionInvalidException;
use Wekser\Laragram\Exceptions\RouteEventInvalidException;

class BotRouteCollection
{
    /**
     * Default request events.
     *
     * @var array
     */
    protected $defaultEvents = ['message', 'edited_message', 'inline_query', 'chosen_inline_result', 'callback_query', 'shipping_query', 'pre_checkout_query'];

    /**
     * Default listener of inbound events.
     *
     * @var array
     */
    protected $defaultListeners = [
        'message' => 'text',
        'edited_message' => 'text',
        'inline_query' => 'query',
        'chosen_inline_result' => 'result_id',
        'callback_query' => 'data',
        'shipping_query' => 'invoice_payload',
        'pre_checkout_query' => 'invoice_payload'
    ];

    /**
     * The router instance used by the route.
     *
     * @var array
     */
    protected $route = [];

    /**
     * The route collection instance.
     *
     * @var array
     */
    protected $routes = [];

    /**
     * Register a new webhook event route with the router.
     *
     * @param string $event
     * @param string|null $listener
     * @return $this
     * @throws RouteEventInvalidException
     */
    public function get(string $event, ?string $listener = null): self
    {
        if (!in_array($event, $this->defaultEvents)) {
            throw new RouteEventInvalidException();
        }

        if (empty($listener)) {
            $listener = $this->defaultListeners[$event] ?? null;
        }

        $this->route['event'] = $event;
        $this->route['listener'] = $listener;

        return $this;
    }

    /**
     * Bind the from the route.
     *
     * @param string $name
     * @return $this
     */
    public function from(string $name): self
    {
        $this->route['from'] = $name;

        return $this;
    }

    /**
     * Bind the contains of listener to a route.
     *
     * @param string|array $pattern
     * @return $this
     */
    public function contains(string|array $pattern): self
    {
        if (is_array($pattern)) {
            foreach ($pattern as $key => $value) {
                if (!empty($value)) $this->fillContains($value, $key);
            }
        } else {
            $this->fillContains($pattern);
        }

        return $this;
    }

    /**
     * Fill the route contains.
     *
     * @param string $pattern
     * @param int $key
     * @return void
     */
    protected function fillContains(string $value, int $key = 0)
    {
        $is_command = Str::startsWith($value, '/');

        $this->route['contains'][$key]['pattern'] = $value;
        $this->route['contains'][$key]['params'] = !$is_command ?: $this->getContainParams($value);
        $this->route['contains'][$key]['is_command'] = $is_command;
    }

    /**
     * Get parameters from the contains.
     *
     * @param string $pattern
     * @return array
     */
    protected function getContainParams(string $pattern): array
    {
        preg_match_all('/{(.*?)}/', $pattern, $matches);

        return array_map(fn($m) => trim($m, '?'), $matches[1]);
    }

    /**
     * Set the callback for the route.
     *
     * @param array|callable $action
     * @return void
     * @throws RouteActionInvalidException
     */
    public function call(array|callable $action)
    {
        if (is_array($action)) {
            if (count($action) != 2) {
                throw new RouteActionInvalidException();
            }

            $controller = $action[0];
            $method = $action[1];

            if (empty($controller) && empty($method)) {
                throw new RouteActionInvalidException();
            }

            $this->route['controller'] = $controller;
            $this->route['method'] = $method;
        }

        if (is_callable($action)) {
            $this->route['callback'] = $action;
        }

        $this->add();
    }

    /**
     * Add a Route instance to the collection.
     *
     * @return void
     */
    protected function add()
    {
        $this->addToCollections();

        $this->refreshRoute();
    }

    /**
     * Add the given route to the arrays of routes.
     *
     * @return void
     */
    protected function addToCollections()
    {
        $this->routes[] = $this->route;
    }

    /**
     * Refresh the current route.
     *
     * @return void
     */
    protected function refreshRoute()
    {
        $this->route = [];
    }

    /**
     * Gather and get collection of routes.
     *
     * @return array
     * @throws NotFoundRouteFileException
     */
    public function collectRoutes(): array
    {
        $file = base_path('routes/' . config('laragram.paths.route') . '.php');

        if (!file_exists($file)) {
            throw new NotFoundRouteFileException($file);
        }

        return call_user_func(function ($bot) use ($file) {
            require $file;
            return $bot->routes;
        }, $this);
    }
}