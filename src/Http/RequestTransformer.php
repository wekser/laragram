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

namespace Wekser\Laragram\Http;

use Illuminate\Support\Arr;
use Wekser\Laragram\BotRequest;

/**
 * Transforms a raw Telegram update array and a matched route into a BotRequest.
 */
class RequestTransformer
{
    public function __construct(
        protected readonly string $type,
        protected readonly array  $update,
    ) {}

    /**
     * Build and return a BotRequest from the matched route and current station.
     */
    public function build(array $route, string $station): BotRequest
    {
        $updateObject = $this->update[$this->type] ?? [];
        $listener     = $route['listener'];
        $contains     = $route['contains'] ?? null;
        $query        = Arr::get($updateObject, $listener);

        $data = [
            'update' => [
                'id'     => $this->update['update_id'],
                'object' => $updateObject,
            ],
            'route'  => [
                'event'    => $this->type,
                'listener' => $listener,
                'contains' => $contains,
                'uses'     => isset($route['controller'])
                    ? $route['controller'] . '@' . $route['method']
                    : 'callback',
                'form'     => $station,
            ],
            'data'   => [
                'query' => $query,
                'all'   => $this->extractNamedParams($query, $contains),
            ],
        ];

        return new BotRequest($data);
    }

    /**
     * Map positional command arguments to named route parameters.
     *
     * @param  string|null $query    The matched listener field value (e.g. "/start john").
     * @param  array|null  $contains The parsed contains entries from RouteCollection.
     * @return array<string, string|null>
     */
    private function extractNamedParams(mixed $query, ?array $contains): array
    {
        if (empty($contains) || empty($contains[0]['params'])) {
            return [];
        }

        $parts = preg_split('/\s+/', trim((string) $query)) ?: [];
        $args  = $contains[0]['is_command']
            ? array_values(Arr::except($parts, 0)) // drop the command word itself
            : $parts;

        if (empty($args)) {
            return [];
        }

        $result = [];

        foreach ($contains[0]['params'] as $index => $name) {
            $result[$name] = $args[$index] ?? null;
        }

        return $result;
    }
}
