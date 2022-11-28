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
     * @return array|null
     */
    public function dispatch(array $update): ?array
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
     * @param array|null $route
     * @return array|null
     * @throws NotExistsControllerException|NotExistMethodException
     */
    protected function runRoute(array $update, ?array $route): ?array
    {
        if (isset($route['callback'])) {
            return $this->prepareResponse(call_user_func($route['callback'], $this->prepareRequest($update, $route)));
        } elseif (isset($route['controller']) && isset($route['method'])) {
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

        return null;
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
     * @return array|null
     * @throws NotFoundRouteException
     */
    public function findRoute(array $update, string $station): ?array
    {
        $object = $update[$this->type];
        $routes = (new BotRouteCollection())->collectRoutes();

        foreach ($routes as $route) {
            $event = $route['event'] ?? null;
            $listener = $route['listener'] ?? null;

            if ($event == $this->type && collect($object)->has($listener)) {
                $data = $object[$listener] ?? null;
                $from = $route['from'] ?? null;
                $contains = $route['contains'] ?? null;

                $EF = $EC = $FS = $CD = $PD = $PEI = null;

                $EF = empty($from);
                $EC = empty($contains);
                $FS = $from == $station;

                if (is_array($contains)) {
                    foreach ($contains as $key => $contain) {
                        $CD[$key] = ($contain['is_command'] && Str::startsWith($data, '/'))
                            && (Str::before($contain['pattern'], ' ') == Str::before($data, ' '));
                        $PD[$key] = Str::startsWith($contain['pattern'], '{') && !empty($contain['params']);
                        $PEI[$key] = $contain['pattern'] == $data;
                    }
                } else $CD = $PD = $PEI = [false];

                if (($EF && $EC) ||
                    ($EF && in_array(true, $CD)) ||
                    ($FS && in_array(true, $PD)) ||
                    ($FS && in_array(true, $PEI)) ||
                    ($EF && in_array(true, $PEI) && $contains[0]['is_command']) ||
                    ($FS && $EC)) return $route;
            }
        }
        return null;
    }
}