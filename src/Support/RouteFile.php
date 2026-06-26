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

namespace Wekser\Laragram\Support;

/**
 * Resolves the bot route / scenes files configured under `paths.route` and
 * `paths.scenes`, relative to the application's `routes/` directory.
 *
 * A configured name may include a subdirectory (e.g. `laragram/routes`), so the
 * default layout keeps both files together in `routes/laragram/`. Path traversal
 * (`..`), backslashes and absolute paths are rejected — the file always resolves
 * under `routes/`.
 */
final class RouteFile
{
    /**
     * Whether a configured file name is safe to resolve under `routes/`.
     * Subdirectories are allowed; traversal, backslashes, absolute paths and
     * empty names are not.
     */
    public static function isValidName(string $name): bool
    {
        return $name !== ''
            && !str_contains($name, '..')
            && !str_contains($name, '\\')
            && !str_starts_with($name, '/');
    }

    /**
     * Absolute path to a (validated) route/scenes file under `routes/`.
     */
    public static function path(string $name): string
    {
        return base_path("routes/{$name}.php");
    }
}
