<?php

/*
 * This file is part of Laragram.
 *
 * (c) Sergey Lapin <hello@wekser.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Wekser\Laragram\Support;

use Exception;

class RouteCollection
{
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
     * @param string $listener
     * @return $this
     */
    public function bind(string $event, string $listener)
    {
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
     * Set the handler for the route.
     *
     * @param string $action
     * @return void
     */
    public function call(string $action)
    {
        $this->route['controller'] = str_before($action, '@');
        $this->route['method'] = str_after($action, '@');

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
     * Find the first route matching a given request.
     *
     * @param array $request
     * @param string $state
     * @return array
     * @throws Exception
     */
    public function match($request, $state)
    {
        $type = array_get(array_keys($request), 1);
        $object = array_get($request, array_get(array_keys($request), 1));
        $routes = $this->collectRoutes();

        foreach ($routes as $route) {

            $event = array_get($route, 'event');
            $listener = array_get($route, 'listener');
            $alias = array_get($route, 'alias');
            $hook = array_get($route, 'hook');

            if ($event == $type && array_has($object, $listener)) {

                $input = array_get($object, $listener);
                $command = str_before($input, ' ');

                $A0 = empty($alias);
                $A1 = empty($hook);
                $B0 = $alias == $state;
                $B1 = $hook == $command;
                $B2 = $hook == $input;

                if (($A0 && $A1) || ($A0 && $B1) || ($B0 && $B2) || ($B0 && $A1)) {
                    return $route;
                }
            }
        }
        throw new Exception('Not Found', 404);
    }

    /**
     * Gather and get collection of routes.
     *
     * @return array
     * @throws Exception
     */
    protected function collectRoutes()
    {
        $file = base_path('routes/laragram.php');

        if (!file_exists($file)) {
            throw new Exception('File laragram.php don\'t exists in routes path.', 500);
        }

        return call_user_func(function ($bot) use ($file) {
            require $file;
            return $bot->routes;
        }, $this);
    }
}