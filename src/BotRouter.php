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

use Illuminate\Container\Container;
use Illuminate\Support\Str;
use Wekser\Laragram\Exceptions\NotExistMethodException;
use Wekser\Laragram\Exceptions\NotExistsControllerException;
use Wekser\Laragram\Exceptions\NotFoundRouteException;
use Wekser\Laragram\Support\FormRequest;
use Wekser\Laragram\Support\FormResponse;

class BotRouter
{
    /**
     * The currently user location.
     *
     * @var string
     */
    protected $location;

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
     * @param string|null $location
     * @return array
     */
    public function dispatch(array $request, ?string $location): array
    {
        $this->locatePath($location);

        return $this->runRoute($request, $this->findRoute($request, $this->state));
    }

    /**
     * Set a path for the current user.
     *
     * @param string|null $state
     * @return void
     */
    protected function locatePath(?string $state)
    {
        $this->location = $location ?? 'start';
    }

    /**
     * Run the route action and return the response.
     *
     * @param array $request
     * @param array $route
     * @return array
     * @throws NotExistsControllerException|NotExistMethodException
     */
    protected function runRoute(array $request, array $route): array
    {
        if (isset($route['callback'])) {
            return $this->prepareResponse(call_user_func($route['callback'], $this->prepareRequest($request, $route)));
        }

        $controller = '\\' . 'App\Http\Controllers\Laragram\HelloController';
        $method = $route['method'];

        if (!class_exists($controller)) {
            throw new NotExistsControllerException($route['controller']);
        }

        if (!method_exists($controller, $method)) {
            throw new NotExistMethodException($route['method'], $route['controller']);
        }

        return $this->prepareResponse((new $controller())->$method($this->prepareRequest($request, $route)));
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
    protected function prepareRequest(array $request, array $route): BotRequest
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
    public function findRoute(array $request, string $state): array
    {
        $type = collect($request)->search(function ($value, $key) {
            return is_array($value) && isset($value['from']);
        });

        $entity = $request[$type];
        $routes = (new BotRouteCollection())->collectRoutes();

        foreach ($routes as $route) {
            $event = $route['event'] ?? null;
            $listener = $route['listener'] ?? null;
            $from = $route['from'] ?? null;
            $contains = $route['contains'] ?? null;

            if ($event == $type && collect($entity)->has($listener)) {

                $input = $entity[$listener] ?? null;
                $command = Str::before($input, ' ');

                $A0 = empty($from);
                $A1 = empty($contains);
                $B0 = $from == $state;
                $B1 = $contains == $command;
                $B2 = $contains == $input;

                if (($A0 && $A1) || ($A0 && $B1) || ($B0 && $B2) || ($B0 && $A1)) {
                    return $route;
                }
            }
        }
        throw new NotFoundRouteException();
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
}