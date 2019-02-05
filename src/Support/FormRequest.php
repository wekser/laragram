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
    public function setRequest(array $request, array $route, ?string $state)
    {
        $this->request['all'] = array_get($request, array_get(array_keys($request), 1));
        $this->request['controller'] = array_get($route, 'controller');
        $this->request['event'] = array_get(array_keys($request), 1);
        $this->request['input'] = array_get(array_get($request, array_get(array_keys($request), 1)), $route['listener']);
        $this->request['hook'] = array_get($route, 'hook');
        $this->request['listener'] = array_get($route, 'listener');
        $this->request['method'] = array_get($route, 'method');
        $this->request['state'] = array_get($route, 'alias') ?? $state;
        $this->request['update_id'] = array_get($request, 'update_id');

        return new BotRequest($this->request);
    }
}