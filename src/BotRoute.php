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

use Wekser\Laragram\Exceptions\NotExistMethodException;
use Wekser\Laragram\Exceptions\NotExistsControllerException;
use Wekser\Laragram\Support\Aidable;
use Wekser\Laragram\Support\FormRequest;
use Wekser\Laragram\Support\FormResponse;
use Wekser\Laragram\Support\RouteCollection;

class BotRoute
{
    use Aidable;

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

        return $this->runRoute($request, $this->findRoute($request));
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
     * @throws Exception
     */
    protected function runRoute($request, $route): array
    {
        $directory = '\\' . $this->getAppNamespace() . 'Http\Controllers';
        $namespace = $directory . chr(92) . $route['controller'];

        if (! class_exists($namespace)) {
            throw new NotExistsControllerException($route['controller']);
        }

        $controller = new $namespace();
        $method = $route['method'];

        if (! method_exists($controller, $method)) {
            throw new NotExistMethodException($route['method'], $route['controller']);
        }

        return $this->prepareResponse($controller->{$method}($this->prepareRequest($request, $route)));
    }

    /**
     * Prepare a response for return to back.
     *
     * @param \Wekser\Laragram\BotResponse $response
     * @return array
     */
    protected function prepareResponse(BotResponse $response): array
    {
        return (new FormResponse())->getResponse($response, $this->request);
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
     * Find the route matching a given request.
     *
     * @param array $request
     * @return array
     */
    protected function findRoute($request): array
    {
        $this->current = $route = (new RouteCollection())->match($request, $this->state);

        return $route;
    }
}