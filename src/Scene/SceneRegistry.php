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

namespace Wekser\Laragram\Scene;

use Wekser\Laragram\Exceptions\NotFoundRouteFileException;
use Wekser\Laragram\Support\RouteFile;

/**
 * Holds the defined scenes and lazily loads them from the scenes file
 * (routes/{config laragram.paths.scenes}.php, default routes/laragram/scenes.php)
 * on first access.
 *
 * Mirrors RouteCollection's loader: the file is required once per process and
 * the result is cached in a static property; call flushCache() between tests.
 */
class SceneRegistry
{
    /** @var array<string, Scene> Defined scenes, keyed by name. */
    private static array $scenes = [];

    /** Whether the scenes file has been loaded this process. */
    private static bool $loaded = false;

    /**
     * Register a new scene definition and return it for fluent configuration.
     */
    public function define(string $name): Scene
    {
        return self::$scenes[$name] = new Scene($name);
    }

    /**
     * Resolve a scene by name, loading the scenes file if needed.
     */
    public function get(string $name): ?Scene
    {
        $this->load();

        return self::$scenes[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return $this->get($name) !== null;
    }

    /**
     * All defined scenes.
     *
     * @return array<string, Scene>
     */
    public function all(): array
    {
        $this->load();

        return self::$scenes;
    }

    /**
     * Clear the scene cache (useful in tests and after definition changes).
     */
    public static function flushCache(): void
    {
        self::$scenes = [];
        self::$loaded = false;
    }

    /**
     * Require the scenes file once. A missing file is not an error — an app may
     * simply not use scenes. An invalid configured name is rejected.
     *
     * @throws NotFoundRouteFileException when the configured name contains path separators.
     */
    private function load(): void
    {
        if (self::$loaded) {
            return;
        }

        // Set the flag before requiring: the file calls BotScene::define(), which
        // routes back into this registry — the guard prevents re-entrant loading.
        self::$loaded = true;

        $name = (string) config('laragram.paths.scenes', 'laragram/scenes');

        if (!RouteFile::isValidName($name)) {
            throw new NotFoundRouteFileException("Invalid scenes file name: {$name}");
        }

        $path = RouteFile::path($name);

        if (is_file($path)) {
            require $path;
        }
    }
}
