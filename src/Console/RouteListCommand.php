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

use Illuminate\Console\Command;
use Wekser\Laragram\Exceptions\NotFoundRouteFileException;
use Wekser\Laragram\Routing\RouteCollection;

class RouteListCommand extends Command
{
    use FormatsRoutes;

    protected $signature = 'laragram:route:list';

    protected $description = 'List all registered Laragram bot routes';

    public function handle(): int
    {
        try {
            $routes = (new RouteCollection())->collectRoutes();
        } catch (NotFoundRouteFileException $e) {
            $this->error('Route file not found: ' . $e->getMessage());
            return self::FAILURE;
        }

        if (empty($routes)) {
            $this->info('No routes registered.');
            return self::SUCCESS;
        }

        $rows = [];

        foreach ($routes as $index => $route) {
            $isFallback = !empty($route['fallback']);

            $name    = $route['name'] ?? '';
            $event   = $isFallback ? '[fallback]' : ($route['event'] ?? '—');
            $station = $isFallback ? '*' : $this->formatStation($route['from'] ?? null);
            $content = $isFallback ? '*' : $this->formatContains($route['contains'] ?? null);
            $roles   = $this->formatRoles($route['roles'] ?? null);
            $handler = $this->formatHandler($route);

            $rows[] = [$index + 1, $name, $event, $station, $content, $roles, $handler];
        }

        $this->table(['#', 'Name', 'Event', 'Station', 'Contains', 'Roles', 'Handler'], $rows);

        return self::SUCCESS;
    }

}
