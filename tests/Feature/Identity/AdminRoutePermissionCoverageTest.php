<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Enums\Permission;
use Illuminate\Routing\Route as RegisteredRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class AdminRoutePermissionCoverageTest extends TestCase
{
    public function test_every_admin_route_requires_a_known_permission(): void
    {
        $permissions = array_map(fn (Permission $permission) => "can:{$permission->value}", Permission::cases());
        $adminRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RegisteredRoute $route) => $route->uri() === 'admin' || str_starts_with($route->uri(), 'admin/'));

        $this->assertGreaterThan(100, $adminRoutes->count());

        $uncovered = $adminRoutes
            ->reject(fn (RegisteredRoute $route) => array_intersect($route->gatherMiddleware(), $permissions) !== [])
            ->map(fn (RegisteredRoute $route) => implode('|', $route->methods()).' '.$route->uri())
            ->values()
            ->all();

        $this->assertSame([], $uncovered, 'Admin routes without a can:<permission> middleware.');
    }

    public function test_every_admin_route_requires_an_active_restaurant_membership(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RegisteredRoute $route) => $route->uri() === 'admin' || str_starts_with($route->uri(), 'admin/'))
            ->reject(fn (RegisteredRoute $route) => in_array('restaurant.member', $route->gatherMiddleware(), true))
            ->map(fn (RegisteredRoute $route) => $route->uri())
            ->values()
            ->all();

        $this->assertSame([], $routes);
    }
}
