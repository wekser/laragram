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
use Wekser\Laragram\Exceptions\NotExistMethodException;
use Wekser\Laragram\Exceptions\NotExistsControllerException;
use Wekser\Laragram\Exceptions\NotFoundRouteException;
use Wekser\Laragram\Support\FormRequest;
use Wekser\Laragram\Support\FormResponse;

class BotRouter
{
    /**
     * The currently user station.
     *
     * @var string
     */
    protected $station;

    /**
     * The current update event.
     *
     * @var string
     */
    protected $type;

    /**
     * The request currently being dispatched.
     *
     * @var \Wekser\Laragram\BotRequest
     */
    protected $request;

    public function __construct(string $station)
    {
        $this->station = $station;
    }

    /**
     * Dispatch the update to a route and return the response.
     *
     * @param array $update
     * @return array
     */
    public function dispatch(array $update): array
    {
        $this->getType($update);

        return $this->runRoute($update, $this->findRoute($update, $this->station));
    }

    /**
     * Get type of update object.
     *
     * @param array $update
     * @return void
     */
    protected function getType(array $update)
    {
        $this->type = collect($update)->search(function ($value, $key) {
            return is_array($value) && isset($value['from']);
        });
    }

    /**
     * Run the route action and return the response.
     *
     * @param array $update
     * @param array $route
     * @return array
     * @throws NotExistsControllerException|NotExistMethodException
     */
    protected function runRoute(array $update, array $route): array
    {
        if (isset($route['callback'])) {
            return $this->prepareResponse(call_user_func($route['callback'], $this->prepareRequest($update, $route)));
        }

        $controller = '\\' . $route['controller'];
        $method = $route['method'];

        if (!class_exists($controller)) {
            throw new NotExistsControllerException($route['controller']);
        }

        if (!method_exists($controller, $method)) {
            throw new NotExistMethodException($route['method'], $route['controller']);
        }

        return $this->prepareResponse((new $controller())->$method($this->prepareRequest($update, $route)));
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
     * @param array $update
     * @param array $route
     * @return \Wekser\Laragram\BotRequest
     */
    protected function prepareRequest(array $update, array $route): BotRequest
    {
        return $this->request = (new FormRequest($this->type, $update))->setRequest($route, $this->station);
    }

    /**
     * Find the first route matching a given request.
     *
     * @param array $update
     * @param string $station
     * @return array
     * @throws NotFoundRouteException
     */
    public function findRoute(array $update, string $station): array
    {
        $object = $update[$this->type];
        $routes = (new BotRouteCollection())->collectRoutes();

        foreach ($routes as $route) {
            $event = $route['event'] ?? null;
            $listener = $route['listener'] ?? null;
            $from = $route['from'] ?? null;
            $contains = $route['contains'] ?? null;

            if ($event == $this->type && collect($object)->has($listener)) {
                $data = $object[$listener] ?? null;

                $EM = empty($from);
                $EC = empty($contains);
                $FEL = $from == $station;

                if (is_array($contains)) {
                    foreach ($contains as $contain) {
                        $CD[] = ($contain['is_command'] && Str::startsWith($data, '/')) && (Str::before($contain['pattern'], ' ') == Str::before($data, ' '));
                        $PD[] = Str::startsWith($contain['pattern'], '{') && !empty($contain['params']);
                        $PEI[] = $contain['pattern'] == $data;
                    }
                } else {
                    $CD[] = $PD[] = $PEI[] = false;
                }

                if (($EM && $EC) || ($EM && in_array(true, $CD)) || ($FEL && in_array(true, $PD)) || ($FEL && in_array(true, $PEI)) || ($FEL && $EC)) return $route;
            }
        }
        throw new NotFoundRouteException();
    }
}