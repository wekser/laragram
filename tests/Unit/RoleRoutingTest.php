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
use Wekser\Laragram\Models\User;
use Wekser\Laragram\Routing\Router;
use Wekser\Laragram\Tests\TestCase;

/**
 * Tests for role-based route matching in Router::findRoute().
 *
 * Uses fixture routes 4 and 5 (defined in tests/Fixtures/routes/laragram.php):
 *   Route 4: message + /admin command + role 'admin'
 *   Route 5: message + from 'admin_panel' + role 'admin'
 */
#[CoversClass(Router::class)]
class RoleRoutingTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Admin command route (Route 4)
    // -------------------------------------------------------------------------

    public function test_admin_route_matches_when_user_has_admin_role(): void
    {
        $this->bindAuthStub($this->makeUser('admin'));

        $route = $this->findRoute('start', 'message', $this->messageUpdate('/admin'));

        $this->assertNotNull($route);
        $this->assertSame(['admin'], $route['roles']);
        $this->assertTrue($route['contains'][0]['is_command']);
    }

    public function test_admin_route_does_not_match_when_user_has_user_role(): void
    {
        $this->bindAuthStub($this->makeUser('user'));

        $route = $this->findRoute('start', 'message', $this->messageUpdate('/admin'));

        $this->assertNull($route);
    }

    public function test_admin_route_does_not_match_when_user_is_null(): void
    {
        // bindAuthStub() default — user() returns null
        $route = $this->findRoute('start', 'message', $this->messageUpdate('/admin'));

        $this->assertNull($route);
    }

    // -------------------------------------------------------------------------
    // Admin station route (Route 5)
    // -------------------------------------------------------------------------

    public function test_admin_station_route_matches_when_user_has_admin_role(): void
    {
        $this->bindAuthStub($this->makeUser('admin'));

        $route = $this->findRoute('admin_panel', 'message', $this->messageUpdate('anything'));

        $this->assertNotNull($route);
        $this->assertSame(['admin_panel'], $route['from']);
        $this->assertSame(['admin'], $route['roles']);
    }

    public function test_admin_station_route_does_not_match_when_user_has_user_role(): void
    {
        $this->bindAuthStub($this->makeUser('user'));

        $route = $this->findRoute('admin_panel', 'message', $this->messageUpdate('anything'));

        $this->assertNull($route);
    }

    // -------------------------------------------------------------------------
    // Non-role routes still work regardless of role
    // -------------------------------------------------------------------------

    public function test_regular_route_matches_regardless_of_role(): void
    {
        $this->bindAuthStub($this->makeUser('admin'));

        $route = $this->findRoute('start', 'message', $this->messageUpdate('/start'));

        $this->assertNotNull($route);
        $this->assertArrayNotHasKey('roles', $route);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function findRoute(string $station, string $type, array $update): ?array
    {
        $router = new Router($station);

        $prop = new ReflectionProperty(Router::class, 'type');
        $prop->setAccessible(true);
        $prop->setValue($router, $type);

        return $router->findRoute($update, $station);
    }

    private function makeUser(string $role): User
    {
        $user       = new User();
        $user->role = $role;

        return $user;
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
}
