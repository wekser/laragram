<?php

declare(strict_types=1);

/*
 * This file is part of Laragram.
 *
 * (c) Sergey Lapin <me@wekser.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Wekser\Laragram\Facades;

use Wekser\Laragram\Routing\Route;
use Wekser\Laragram\Routing\RouteCollection;

/**
 * Static façade for registering bot routes inside routes/laragram.php.
 *
 * Equivalent to using the injected $collection variable:
 *
 *   // Both lines are identical:
 *   $collection->get('message')->contains('/start')->call([StartController::class, 'index']);
 *   BotRoute::get('message')->contains('/start')->call([StartController::class, 'index']);
 *
 * Available methods mirror RouteCollection's fluent DSL:
 *
 * @method static RouteCollection get(string $event, ?string $listener = null)
 * @method static RouteCollection fallback()
 * @method static RouteCollection group(callable $callback, string|array|null $from = null, string|array|null $roles = null)
 *
 * @see \Wekser\Laragram\Routing\RouteCollection
 */
class BotRoute
{
    /** The RouteCollection instance active during the current collectRoutes() call. */
    private static ?RouteCollection $instance = null;

    /**
     * Set the active RouteCollection instance.
     * Called internally by RouteCollection::collectRoutes() before requiring the routes file.
     */
    public static function setInstance(RouteCollection $collection): void
    {
        self::$instance = $collection;
    }

    /**
     * Clear the active instance after the routes file has been loaded.
     */
    public static function clearInstance(): void
    {
        self::$instance = null;
    }

    /**
     * Proxy all static calls to the active RouteCollection instance.
     *
     * @throws \RuntimeException when called outside a collectRoutes() context.
     */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        if (self::$instance === null) {
            throw new \RuntimeException(
                'BotRoute facade can only be used inside the bot routes file (routes/laragram.php).'
            );
        }

        return self::$instance->$method(...$arguments);
    }
}
