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
     * Returns the current route.
     *
     * @param ?string $key
     * @return array|string|null
     */
    public function route(?string $key): array|string|null
    {
        return $key ? $this->getRequestData('route.' . $key) : $this->getRequestData('route');
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
     * @param ?string $default
     * @return array|string|null
     */
    public function get(string $key, ?string $default): array|string|null
    {
        return $this->getRequestData('update.' . $key, $default);
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
     * Returns the data query from callback.
     *
     * @return array|null
     */
    public function all(): ?array
    {
        return $this->getRequestData('data.all');
    }

    /**
     * Returns all callback data.
     *
     * @return array
     */
    public function update(): array
    {
        return $this->getRequestData('update');
    }

    /**
     * Returns the query from callback.
     *
     * @return string|null
     */
    public function query(): ?string
    {
        return $this->getRequestData('data.query');
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