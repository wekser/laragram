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

use Illuminate\Support\Arr;

class BotRequest
{
    /**
     * The current request.
     *
     * @var array
     */
    protected $request;

    /**
     * BotRequest Constructor
     *
     * @param array $request
     */
    public function __construct(array $request = [])
    {
        $this->request = $request;
    }

    /**
     * Returns the current route controller.
     *
     * @return string
     */
    public function controller(): string
    {
        return $this->getRequestData('controller');
    }

    /**
     * Get the data from current request.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function getRequestData(string $key, $default = null)
    {
        return Arr::get($this->getRequest(), $key, $default);
    }

    /**
     * Returns a parameter in object by name.
     *
     * @param string $key
     * @param mixed $default
     * @return array|string|null
     */
    public function get(string $key, $default = null): array|string|null
    {
        return $this->getRequestData('all.' . $key, $default);
    }

    /**
     * Get the current request.
     *
     * @return array
     */
    public function getRequest(): array
    {
        return $this->request;
    }

    /**
     * Returns the parameter keys.
     *
     * @return array
     */
    public function keys(): array
    {
        return array_keys($this->all());
    }

    /**
     * Returns the parameters.
     *
     * @return array
     */
    public function all(): array
    {
        return $this->getRequestData('all');
    }

    /**
     * Returns the current route event.
     *
     * @return string
     */
    public function event(): string
    {
        return $this->getRequestData('event');
    }

    /**
     * Returns the input text from callback.
     *
     * @return string|null
     */
    public function input(): ?string
    {
        return $this->getRequestData('input.text');
    }

    /**
     * Returns the input data from callback.
     *
     * @return array|null
     */
    public function data(): ?array
    {
        return $this->getRequestData('input.data');
    }

    /**
     * Returns the current route contains.
     *
     * @return string|null
     */
    public function contains(): ?string
    {
        return $this->getRequestData('contains');
    }

    /**
     * Returns the current route controller method.
     *
     * @return string
     */
    public function method(): string
    {
        return $this->getRequestData('method');
    }

    /**
     * Returns the current user location.
     *
     * @return string|null
     */
    public function location(): ?string
    {
        return $this->getRequestData('location');
    }

    /**
     * Returns the event listener of request.
     *
     * @return string
     */
    public function listener(): string
    {
        return $this->getRequestData('listener');
    }

    /**
     * Returns true if the parameter in object is defined.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return Arr::has($this->all(), $key);
    }
}