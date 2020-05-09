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
    public function bind(string $event, ?string $listener = null)
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
     * Bind the alias to a route.
     *
     * @param string $alias
     * @return $this
     */
    public function alias(string $alias)
    {
        $this->route['alias'] = $alias;

        return $this;
    }

    /**
     * Bind the hook of listener to a route.
     *
     * @param string $catch
     * @return $this
     */
    public function catch(string $hook)
    {
        $this->route['hook'] = $hook;

        return $this;
    }

    /**
     * Set the action for the route.
     *
     * @param string $action
     * @return void
     * @throws RouteActionInvalidException
     */
    public function call(string $action)
    {
        if (!Str::contains($action, '@')) {
            throw new RouteActionInvalidException();
        }

        $controller = Str::before($action, '@');
        $method = Str::after($action, '@');

        if (empty($controller) && empty($method)) {
            throw new RouteActionInvalidException();
        }

        $this->route['controller'] = $controller;
        $this->route['method'] = $method;

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
        array_push($this->routes, $this->route);
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
    public function collectRoutes()
    {
        $file = base_path('routes/laragram.php');

        if (!file_exists($file)) {
            throw new NotFoundRouteFileException($file);
        }

        return call_user_func(function ($bot) use ($file) {
            require $file;
            return $bot->routes;
        }, $this);
    }
}