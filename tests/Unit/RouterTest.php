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

namespace Wekser\Laragram\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionProperty;
use Wekser\Laragram\Routing\Router;
use Wekser\Laragram\Tests\TestCase;

/**
 * Tests for Router::findRoute() and Router::getType().
 *
 * findRoute() is tested by:
 *   1. Manually setting the protected $type via reflection (avoids running
 *      the full dispatch() pipeline including FormRequest/FormResponse).
 *   2. Calling the public findRoute() method directly.
 *
 * The test fixture at tests/Fixtures/routes/laragram.php defines 4 routes
 * (indexed 0-3) used to verify matching conditions.
 */
#[CoversClass(Router::class)]
class RouterTest extends TestCase
{
    // -------------------------------------------------------------------------
    // getType()
    // -------------------------------------------------------------------------

    public function test_get_type_detects_message_event(): void
    {
        $router = new Router('start');
        $update = $this->messageUpdate('/start');

        // getType() is protected; invoke dispatch() which calls it, then read $type.
        // We can read the protected property via reflection after dispatch().
        // But dispatch() runs runRoute() which needs more setup.
        // Instead, use reflection to call getType() directly.
        $method = new \ReflectionMethod(Router::class, 'getType');
        $method->setAccessible(true);
        $method->invoke($router, $update);

        $prop = new ReflectionProperty(Router::class, 'type');
        $prop->setAccessible(true);

        $this->assertSame('message', $prop->getValue($router));
    }

    public function test_get_type_detects_callback_query_event(): void
    {
        $router = new Router('start');
        $update = $this->callbackUpdate('button_1');

        $method = new \ReflectionMethod(Router::class, 'getType');
        $method->setAccessible(true);
        $method->invoke($router, $update);

        $prop = new ReflectionProperty(Router::class, 'type');
        $prop->setAccessible(true);

        $this->assertSame('callback_query', $prop->getValue($router));
    }

    // -------------------------------------------------------------------------
    // findRoute() — matching conditions
    // -------------------------------------------------------------------------

    public function test_find_route_matches_command_without_station_requirement(): void
    {
        // Fixture route 0: message + /start (no from)
        // Matching condition: noStationFilter=true AND commandMatchResults=true (/start command match)
        $route = $this->findRoute('start', 'message', $this->messageUpdate('/start'));

        $this->assertNotNull($route);
        $this->assertSame('message', $route['event']);
        $this->assertTrue($route['contains'][0]['is_command']);
    }

    public function test_find_route_matches_station_only_route_when_station_is_correct(): void
    {
        // Fixture route 1: message + from='waiting_input' (no contains)
        // Matching condition: stationMatches=true AND noContentFilter=true
        $route = $this->findRoute('waiting_input', 'message', $this->messageUpdate('anything'));

        $this->assertNotNull($route);
        $this->assertSame(['waiting_input'], $route['from']);
        $this->assertArrayNotHasKey('contains', $route);
    }

    public function test_find_route_does_not_match_station_only_route_when_station_is_wrong(): void
    {
        // Route 1 requires station 'waiting_input', so it should NOT match 'start'
        // Route 0 requires /start command, so it should NOT match plain text
        $route = $this->findRoute('start', 'message', $this->messageUpdate('anything'));

        $this->assertNull($route);
    }

    public function test_find_route_matches_exact_text_with_station(): void
    {
        // Fixture route 2: message + from='menu_shown' + contains='Hello'
        // Matching condition: stationMatches=true AND exactMatchResults=true (exact text match)
        $route = $this->findRoute('menu_shown', 'message', $this->messageUpdate('Hello'));

        $this->assertNotNull($route);
        $this->assertSame('Hello', $route['contains'][0]['pattern']);
    }

    public function test_find_route_does_not_match_exact_text_when_station_is_wrong(): void
    {
        // Route 2 requires station 'menu_shown' AND text 'Hello'
        $route = $this->findRoute('start', 'message', $this->messageUpdate('Hello'));

        $this->assertNull($route);
    }

    public function test_find_route_matches_callback_query_without_restrictions(): void
    {
        // Fixture route 3: callback_query (no from, no contains)
        // Matching condition: noStationFilter=true AND noContentFilter=true
        $route = $this->findRoute('start', 'callback_query', $this->callbackUpdate('any_data'));

        $this->assertNotNull($route);
        $this->assertSame('callback_query', $route['event']);
    }

    public function test_find_route_returns_null_when_event_type_has_no_matching_route(): void
    {
        $route = $this->findRoute('start', 'inline_query', [
            'update_id'    => 1,
            'inline_query' => [
                'from'  => ['id' => 1, 'first_name' => 'Test'],
                'query' => 'search text',
            ],
        ]);

        $this->assertNull($route);
    }

    public function test_find_route_returns_first_matching_route(): void
    {
        // station='waiting_input' + any text → Route 1 (from='waiting_input', no contains)
        // must win because it appears before any other route matching this station.
        $route = $this->findRoute('waiting_input', 'message', $this->messageUpdate('some text'));

        $this->assertSame(['waiting_input'], $route['from'] ?? null);
        $this->assertArrayNotHasKey('contains', $route);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build a router with the given station, set $type via reflection,
     * then call findRoute() with the update.
     */
    private function findRoute(string $station, string $type, array $update): ?array
    {
        $router = new Router($station);

        $prop = new ReflectionProperty(Router::class, 'type');
        $prop->setAccessible(true);
        $prop->setValue($router, $type);

        return $router->findRoute($update, $station);
    }

    private function messageUpdate(string $text): array
    {
        return [
            'update_id' => 1,
            'message'   => [
                'from' => ['id' => 1, 'first_name' => 'Test', 'is_bot' => false],
                'chat' => ['id' => 1],
                'text' => $text,
            ],
        ];
    }

    private function callbackUpdate(string $data): array
    {
        return [
            'update_id'      => 1,
            'callback_query' => [
                'from' => ['id' => 1, 'first_name' => 'Test', 'is_bot' => false],
                'data' => $data,
            ],
        ];
    }
}
