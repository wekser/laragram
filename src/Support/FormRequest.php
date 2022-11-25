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
     * The type update object.
     *
     * @return string
     */
    protected $type;

    /**
     * The update object.
     *
     * @return array
     */
    protected $update;

    /**
     * FormRequest Constructor
     *
     * @param string $type
     * @param array $update
     */
    public function __construct(string $type, array $update)
    {
        $this->type = $type;
        $this->update = $update;
    }

    /**
     * Set the route request for action.
     *
     * @param array $route
     * @param string $station
     * @return \Wekser\Laragram\BotRequest
     */
    public function setRequest(array $route, string $station): BotRequest
    {
        $this->request['update']['id'] = $this->update['update_id'];
        $this->request['update']['object'] = $this->update[$this->type];
        $this->request['route']['event'] = $this->type;
        $this->request['route']['listener'] = $route['listener'];
        $this->request['route']['contains'] = $route['contains'] ?? null;
        $this->request['route']['uses'] = isset($route['controller']) ? $route['controller'] . '@' . $route['method'] : 'callback';
        $this->request['route']['form'] = $route['from'] ?? $station;
        $this->request['data']['query'] = collect($this->request['update']['object'])->get($route['listener']);
        $this->request['data']['all'] = $this->getDataAll($this->request['data']['query'], $this->request['route']['contains']);

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