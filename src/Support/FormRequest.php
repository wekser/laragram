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
     * @param string|null $state
     * @return \Wekser\Laragram\BotRequest
     */
    public function setRequest(array $request, array $route, $state = null)
    {
        $type = collect($request)->search(function ($value, $key) {
            return is_array($value) && isset($value['from']);
        });

        $entity = $request[$type];

        $this->request['all'] = $entity;
        $this->request['controller'] = $route['controller'];
        $this->request['event'] = $type;
        $this->request['query'] = collect($entity)->get($route['listener']);
        $this->request['hook'] = $route['hook'] ?? null;
        $this->request['listener'] = $route['listener'];
        $this->request['method'] = $route['method'];
        $this->request['state'] = $route['alias'] ?? $state;
        $this->request['update_id'] = $request['update_id'];

        return new BotRequest($this->request);
    }
}