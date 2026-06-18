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
use Wekser\Laragram\Exceptions\NotFoundRouteFileException;
use Wekser\Laragram\Routing\RouteCollection;
use Wekser\Laragram\Exceptions\RouteActionInvalidException;
use Wekser\Laragram\Exceptions\RouteEventInvalidException;
use Wekser\Laragram\Tests\TestCase;

#[CoversClass(RouteCollection::class)]
class BotRouteCollectionTest extends TestCase
{
    // -------------------------------------------------------------------------
    // get() — event registration
    // -------------------------------------------------------------------------

    public function test_get_throws_on_unsupported_event(): void
    {
        $this->expectException(RouteEventInvalidException::class);

        (new RouteCollection())->get('unknown_event');
    }

    public function test_get_sets_default_listener_for_message(): void
    {
        $routes = $this->routeFor('message');

        $this->assertSame('text', $routes[0]['listener']);
    }

    public function test_get_sets_default_listener_for_callback_query(): void
    {
        $routes = $this->routeFor('callback_query');

        $this->assertSame('data', $routes[0]['listener']);
    }

    public function test_get_sets_default_listener_for_inline_query(): void
    {
        $routes = $this->routeFor('inline_query');

        $this->assertSame('query', $routes[0]['listener']);
    }

    public function test_get_sets_default_listener_for_channel_post(): void
    {
        $routes = $this->routeFor('channel_post');

        $this->assertSame('text', $routes[0]['listener']);
    }

    public function test_get_sets_default_listener_for_poll(): void
    {
        $routes = $this->routeFor('poll');

        $this->assertSame('question', $routes[0]['listener']);
    }

    public function test_get_sets_default_listener_for_poll_answer(): void
    {
        $routes = $this->routeFor('poll_answer');

        $this->assertSame('option_ids', $routes[0]['listener']);
    }

    public function test_get_sets_default_listener_for_my_chat_member(): void
    {
        $routes = $this->routeFor('my_chat_member');

        $this->assertSame('from', $routes[0]['listener']);
    }

    public function test_get_sets_default_listener_for_chat_join_request(): void
    {
        $routes = $this->routeFor('chat_join_request');

        $this->assertSame('from', $routes[0]['listener']);
    }

    public function test_get_accepts_custom_listener_override(): void
    {
        $collection = new RouteCollection();
        $collection->get('message', 'caption')->call(fn () => null);

        $this->assertSame('caption', $collection->getRoutes()[0]['listener']);
    }

    // -------------------------------------------------------------------------
    // from() — station filter
    // -------------------------------------------------------------------------

    public function test_from_sets_the_station(): void
    {
        $collection = new RouteCollection();
        $collection->get('message')->from('waiting_name')->call(fn () => null);

        $this->assertSame(['waiting_name'], $collection->getRoutes()[0]['from']);
    }

    // -------------------------------------------------------------------------
    // contains() — pattern matching
    // -------------------------------------------------------------------------

    public function test_contains_with_command_marks_is_command_true(): void
    {
        $collection = new RouteCollection();
        $collection->get('message')->contains('/start')->call(fn () => null);
        $pattern = $collection->getRoutes()[0]['contains'][0];

        $this->assertTrue($pattern['is_command']);
        $this->assertSame('/start', $pattern['pattern']);
    }

    public function test_contains_with_plain_text_marks_is_command_false(): void
    {
        $collection = new RouteCollection();
        $collection->get('message')->contains('Hello')->call(fn () => null);
        $pattern = $collection->getRoutes()[0]['contains'][0];

        $this->assertFalse($pattern['is_command']);
    }

    public function test_contains_with_plain_text_has_empty_params(): void
    {
        $collection = new RouteCollection();
        $collection->get('message')->contains('Hello World')->call(fn () => null);

        $this->assertSame([], $collection->getRoutes()[0]['contains'][0]['params']);
    }

    public function test_contains_extracts_named_parameters_from_command(): void
    {
        $collection = new RouteCollection();
        $collection->get('message')->contains('/send {recipient} {text}')->call(fn () => null);
        $pattern = $collection->getRoutes()[0]['contains'][0];

        $this->assertSame(['recipient', 'text'], $pattern['params']);
    }

    public function test_contains_accepts_array_of_patterns(): void
    {
        $collection = new RouteCollection();
        $collection->get('message')->contains(['Yes', 'No'])->call(fn () => null);
        $contains = $collection->getRoutes()[0]['contains'];

        $this->assertCount(2, $contains);
        $this->assertSame('Yes', $contains[0]['pattern']);
        $this->assertSame('No', $contains[1]['pattern']);
    }

    // -------------------------------------------------------------------------
    // call() — action assignment
    // -------------------------------------------------------------------------

    public function test_call_with_callable_sets_callback(): void
    {
        $fn         = fn () => 'ok';
        $collection = new RouteCollection();
        $collection->get('message')->call($fn);

        $this->assertSame($fn, $collection->getRoutes()[0]['callback']);
    }

    public function test_call_with_array_sets_controller_and_method(): void
    {
        $collection = new RouteCollection();
        $collection->get('message')->call(['App\Bot\Controllers\StartController', 'handle']);
        $route = $collection->getRoutes()[0];

        $this->assertSame('App\Bot\Controllers\StartController', $route['controller']);
        $this->assertSame('handle', $route['method']);
    }

    public function test_call_throws_when_array_has_only_one_element(): void
    {
        $this->expectException(RouteActionInvalidException::class);

        (new RouteCollection())->get('message')->call(['OnlyOneElement']);
    }

    public function test_call_throws_when_array_has_three_elements(): void
    {
        $this->expectException(RouteActionInvalidException::class);

        (new RouteCollection())->get('message')->call(['A', 'B', 'C']);
    }

    public function test_call_throws_when_controller_is_empty_string(): void
    {
        $this->expectException(RouteActionInvalidException::class);

        (new RouteCollection())->get('message')->call(['', 'method']);
    }

    public function test_call_throws_when_method_is_empty_string(): void
    {
        $this->expectException(RouteActionInvalidException::class);

        (new RouteCollection())->get('message')->call(['Controller', '']);
    }

    // -------------------------------------------------------------------------
    // Route accumulation and clearing
    // -------------------------------------------------------------------------

    public function test_multiple_routes_accumulate_in_collection(): void
    {
        $collection = new RouteCollection();
        $collection->get('message')->call(fn () => null);
        $collection->get('callback_query')->call(fn () => null);

        $this->assertCount(2, $collection->getRoutes());
    }

    public function test_clear_routes_empties_collection(): void
    {
        $collection = new RouteCollection();
        $collection->get('message')->call(fn () => null);
        $collection->clearRoutes();

        $this->assertEmpty($collection->getRoutes());
    }

    // -------------------------------------------------------------------------
    // collectRoutes() — file loading
    // -------------------------------------------------------------------------

    public function test_collect_routes_throws_when_file_does_not_exist(): void
    {
        $this->app['config']->set('laragram.paths.route', 'nonexistent_file_xyz');

        $this->expectException(NotFoundRouteFileException::class);

        (new RouteCollection())->collectRoutes();
    }

    public function test_collect_routes_loads_routes_from_fixture_file(): void
    {
        // base_path('routes/laragram.php') → tests/Fixtures/routes/laragram.php
        $routes = (new RouteCollection())->collectRoutes();

        $this->assertNotEmpty($routes);
        $this->assertSame('message', $routes[0]['event']);
    }

    public function test_collect_routes_returns_all_routes_from_fixture(): void
    {
        $routes = (new RouteCollection())->collectRoutes();

        // Fixture defines 6 routes (see tests/Fixtures/routes/laragram.php)
        $this->assertCount(6, $routes);
    }

    // -------------------------------------------------------------------------
    // helpers
    // -------------------------------------------------------------------------

    private function routeFor(string $event): array
    {
        $collection = new RouteCollection();
        $collection->get($event)->call(fn () => null);

        return $collection->getRoutes();
    }
}
