<?php

/*
 * This file is part of Laragram.
 *
 * (c) Sergey Lapin <hello@wekser.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Wekser\Laragram;

use Illuminate\Container\Container;
use Wekser\Laragram\Exceptions\NotExistMethodException;
use Wekser\Laragram\Exceptions\NotExistsControllerException;
use Wekser\Laragram\Exceptions\NotFoundRouteException;
use Wekser\Laragram\Support\FormRequest;
use Wekser\Laragram\Support\FormResponse;

class BotRouter
{
    /**
     * The currently user state.
     *
     * @var string
     */
    protected $state;

    /**
     * The currently dispatched route instance.
     *
     * @var array
     */
    protected $current;

    /**
     * The request currently being dispatched.
     *
     * @var \Wekser\Laragram\BotRequest
     */
    protected $request;

    /**
     * Dispatch the request to a route and return the response.
     *
     * @param array $request
     * @param string|null $state
     * @return array
     */
    public function dispatch($request, $state): array
    {
        $this->locatePath($state);

        return $this->runRoute($request, $this->findRoute($request, $this->state));
    }

    /**
     * Set a path for the current user.
     *
     * @param string|null $state
     * @return void
     */
    protected function locatePath($state)
    {
        $this->state = $state ?? 'start';
    }

    /**
     * Run the route action and return the response.
     *
     * @param array $request
     * @param array $route
     * @return array
     * @throws NotExistsControllerException|NotExistMethodException
     */
    protected function runRoute($request, $route): array
    {
        $directory = '\\' . $this->getAppNamespace() . 'Http\Controllers';
        $namespace = $directory . chr(92) . $route['controller'];

        if (!class_exists($namespace)) {
            throw new NotExistsControllerException($route['controller']);
        }

        $controller = new $namespace();
        $method = $route['method'];

        if (!method_exists($controller, $method)) {
            throw new NotExistMethodException($route['method'], $route['controller']);
        }

        return $this->prepareResponse($controller->{$method}($this->prepareRequest($request, $route)));
    }

    /**
     * Get the application namespace.
     *
     * @return string
     */
    protected function getAppNamespace(): string
    {
        return Container::getInstance()->getNamespace();
    }

    /**
     * Prepare a response for return to back.
     *
     * @param \Wekser\Laragram\BotResponse|string $response
     * @return array
     */
    protected function prepareResponse($response): array
    {
        return (new FormResponse())->getResponse($this->request, $response);
    }

    /**
     * Prepare a response for return to back.
     *
     * @param array $request
     * @param array $route
     * @return \Wekser\Laragram\BotRequest
     */
    protected function prepareRequest($request, $route)
    {
        return $this->request = (new FormRequest())->setRequest($request, $route, $this->state);
    }

    /**
     * Find the first route matching a given request.
     *
     * @param array $request
     * @param string $state
     * @return array
     * @throws NotFoundRouteException
     */
    public function findRoute(array $request, $state)
    {
        $type = collect($request)->search(function ($value, $key) {
            return is_array($value) && isset($value['from']);
        });

        $entity = $request[$type];
        $routes = (new BotRouteCollection())->collectRoutes();

        foreach ($routes as $route) {

            $event = $route['event'] ?? null;
            $listener = $route['listener'] ?? null;
            $alias = $route['alias'] ?? null;
            $hook = $route['hook'] ?? null;

            if ($event == $type && collect($entity)->has($listener)) {

                $query = $entity[$listener] ?? null;
                $command = str_before($query, ' ');

                $A0 = empty($alias);
                $A1 = empty($hook);
                $B0 = $alias == $state;
                $B1 = $hook == $command;
                $B2 = $hook == $query;

                if (($A0 && $A1) || ($A0 && $B1) || ($B0 && $B2) || ($B0 && $A1)) {
                    return $route;
                }
            }
        }
        throw new NotFoundRouteException();
    }
}