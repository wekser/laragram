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

        $this->request['all'] = $entity;
        $this->request['event'] = $type;
        $this->request['listener'] = $route['listener'];
        $this->request['input'] = collect($entity)->get($route['listener']);
        $this->request['contains'] = $route['contains'] ?? null;
        $this->request['uses'] = isset($route['controller']) ? $route['controller'] . '@' . $route['method'] : 'callback';
        $this->request['location'] = $route['from'] ?? $location;
        $this->request['update_id'] = $request['update_id'];

        return new BotRequest($this->request);
    }
}