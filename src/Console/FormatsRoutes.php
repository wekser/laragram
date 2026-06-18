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

namespace Wekser\Laragram\Console;

trait FormatsRoutes
{
    protected function formatStation(mixed $from, string $empty = '*'): string
    {
        if (empty($from)) {
            return $empty;
        }
        return implode(', ', (array) $from);
    }

    protected function formatContains(?array $contains, string $empty = '*'): string
    {
        if (empty($contains)) {
            return $empty;
        }
        return implode(', ', array_map(static fn (array $c): string => $c['pattern'], $contains));
    }

    protected function formatRoles(?array $roles, string $empty = '*'): string
    {
        if (empty($roles)) {
            return $empty;
        }
        return implode(', ', $roles);
    }

    protected function formatHandler(array $route): string
    {
        if (isset($route['controller'], $route['method'])) {
            $parts = explode('\\', $route['controller']);
            return end($parts) . '@' . $route['method'];
        }

        if (isset($route['callback'])) {
            return '{closure}';
        }

        return '—';
    }
}
