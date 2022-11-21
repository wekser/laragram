<?php

/*
 * This file is part of Laragram.
 *
 * (c) Sergey Lapin <me@wekser.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Wekser\Laragram\Support;

use Illuminate\Support\Arr;
use Wekser\Laragram\BotRequest;

class FormRequest
{
    /**
     * The new formed request.
     *
     * @return array
     */
    protected $request;

    /**
     * Set the route request for action.
     *
     * @param array $request
     * @param array $route
     * @param string|null $location
     * @return \Wekser\Laragram\BotRequest
     */
    public function setRequest(array $request, array $route, ?string $location = null): BotRequest
    {
        $type = collect($request)->search(function ($value, $key) {
            return is_array($value) && isset($value['from']);
        });

        $entity = $request[$type];

        $this->request['uid'] = $request['update_id'];
        $this->request['update'] = $entity;
        $this->request['data']['query'] = collect($entity)->get($route['listener']);
        $this->request['data']['all'] = $this->getDataAll($this->request['data']['query'], $this->request['contains']);
        $this->request['route']['event'] = $type;
        $this->request['route']['listener'] = $route['listener'];
        $this->request['route']['contains'] = $route['contains'] ?? null;
        $this->request['route']['uses'] = isset($route['controller']) ? $route['controller'] . '@' . $route['method'] : 'callback';
        $this->request['route']['location'] = $route['from'] ?? $location;

        return new BotRequest($this->request);
    }

    /**
     * Get all data from query.
     *
     * @param string $query
     * @param array|null $contains
     * @return array
     */
    protected function getDataAll(string $query, ?array $contains): array
    {
        if (empty($contains) || empty($contains['params'])) return [];

        $mix = explode(' ', $query);
        $args = $contains['is_command'] ? array_values(Arr::except($mix, 0)) : $mix;

        if (empty($args)) return [];

        $data = [];

        foreach ($contains['params'] as $key => $param) {
            $data[$param] = $args[$key] ?? null;
        }

        return $data;
    }
}