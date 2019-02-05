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

class BotRequest
{
    /**
     * The current request.
     *
     * @return array
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
     * Returns the parameters.
     *
     * @return array
     */
    public function all(): array
    {
        return array_get($this->request, 'all');
    }

    /**
     * Returns the current route controller.
     *
     * @return string
     */
    public function controller(): string
    {
        return array_get($this->request, 'controller');
    }

    /**
     * Returns the parameter keys.
     *
     * @return array
     */
    public function keys(): array
    {
        return array_keys(array_get($this->request, 'all'));
    }

    /**
     * Returns the current route event.
     *
     * @return string
     */
    public function event(): string
    {
        return array_get($this->request, 'event');
    }

    /**
     * Returns the query from callback.
     *
     * @return string|null
     */
    public function input(): ?string
    {
        return array_get($this->request, 'input');
    }

    /**
     * Returns the current route hook.
     *
     * @return string|null
     */
    public function hook(): ?string
    {
        return array_get($this->request, 'hook');
    }

    /**
     * Returns the current route controller method.
     *
     * @return string
     */
    public function method(): string
    {
        return array_get($this->request, 'method');
    }

    /**
     * Returns the current user location.
     *
     * @return string|null
     */
    public function location(): ?string
    {
        return array_get($this->request, 'location');
    }

    /**
     * Returns the event listener of request.
     *
     * @return string
     */
    public function listener(): string
    {
        return array_get($this->request, 'listener');
    }

    /**
     * Get the updated ID from current request.
     *
     * @return int
     */
    public function getUpdateId(): int
    {
        return array_get($this->request, 'update_id');
    }

    /**
     * Returns a parameter in object by name.
     *
     * @param string $key
     * @param mixed $default
     *
     * @return array|string|null
     */
    public function get($key, $default = null)
    {
        return array_get(array_get($this->request, 'all'), $key, $default);
    }

    /**
     * Returns true if the parameter in object is defined.
     *
     * @param string $key
     *
     * @return bool
     */
    public function has($key): bool
    {
        return array_has(array_get($this->request, 'all'), $key);
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
}