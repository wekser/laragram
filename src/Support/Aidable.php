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

use Illuminate\Container\Container;

trait Aidable
{
    /**
     * Get the application namespace.
     *
     * @return string
     */
    protected function getAppNamespace(): string
    {
        return Container::getInstance()->getNamespace();
    }

    /**
     * Get value from configuration.
     *
     * @param string $key
     * @param string|null $default
     * @return array|string|null
     */
    protected function config($key, $default = null)
    {
        return array_get(config('laragram'), $key, $default);
    }
}