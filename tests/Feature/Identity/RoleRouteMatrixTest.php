<?php

namespace Tests\Feature\Identity;

use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Representative GET and mutating routes of every admin module, per role.
 * A mutating route denied to a role must answer 403 and persist nothing.
 */
final class RoleRouteMatrixTest extends TestCase
{
    use RefreshDatabase;

    private const ALL = ['owner', 'manager', 'cashier', 'waiter', 'kitchen', 'accountant'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function roles(): array
    {
        return array_combine(self::ALL, array_map(fn (string $role) => [$role], self::ALL));
    }

    #[DataProvider('roles')]
    public function test_each_role_reaches_exactly_its_permitted_routes(string $role): void
    {
        $user = User::factory()->withRole(Role::from($role))->create();
        $customer = Customer::query()->create(['name' => 'Existing customer']);
        $order = Order::query()->forceCreate(['public_id' => 'TP-000001', 'customer_id' => $customer->id, 'delivery_date' => now()->toDateString()]);
        $failures = [];

        foreach ($this->routes($order) as [$method, $uri, $payload, $allowed, $persisted]) {
            $status = $this->actingAs($user)->json($method, $uri, $payload)->status();
            $permitted = in_array($role, $allowed, true);

            if ($permitted && $status === 403) {
                $failures[] = "{$method} {$uri} should be allowed, got 403";
            }

            if (! $permitted && $status !== 403) {
                $failures[] = "{$method} {$uri} should be forbidden, got {$status}";
            }

            if (! $permitted && $persisted !== null) {
                [$table, $values] = $persisted;
                if ($this->getConnection()->table($table)->where($values)->exists()) {
                    $failures[] = "{$method} {$uri} persisted a {$table} row";
                }
            }
        }

        $this->assertSame([], $failures);
        $this->assertSame('waiting', $order->fresh()->status->value);
    }

    public function test_the_accountant_reads_finance_dashboard_inventory_and_orders_but_cannot_change_them(): void
    {
        $accountant = User::factory()->withRole(Role::Accountant)->create();

        foreach (['/admin/management', '/admin/management/audit', '/admin/management/purchases', '/admin/dashboard', '/admin/inventory', '/admin/inventory/costing', '/admin/orders'] as $page) {
            $this->actingAs($accountant)->get($page)->assertOk();
        }

        $this->postJson('/admin/management/wallets', ['name' => 'Caja contador'])->assertForbidden();
        $this->postJson('/admin/management/transactions', [])->assertForbidden();
        $this->postJson('/admin/inventory/categories', ['name' => 'Secos'])->assertForbidden();
        $this->postJson('/admin/orders', [])->assertForbidden();
        $this->assertDatabaseMissing('wallets', ['name' => 'Caja contador']);
        $this->assertDatabaseMissing('inventory_categories', ['name' => 'Secos']);
    }

    public function test_the_waiter_reaches_order_creation_and_reads_the_floor_without_changing_it(): void
    {
        $waiter = User::factory()->withRole(Role::Waiter)->create();

        $this->actingAs($waiter)->get('/admin/orders/create')->assertOk();
        $this->get('/admin/restaurant/floor')->assertOk();
        $this->postJson('/admin/restaurant/areas', ['name' => 'Patio', 'width' => 1600, 'height' => 1000, 'is_active' => true])->assertForbidden();
        $this->assertDatabaseMissing('dining_areas', ['name' => 'Patio']);
    }

    public function test_roles_without_order_management_land_on_the_order_list(): void
    {
        foreach ([Role::Kitchen, Role::Accountant] as $role) {
            $this->actingAs(User::factory()->withRole($role)->create())->get('/admin')->assertRedirect('/admin/orders');
        }

        $this->actingAs(User::factory()->withRole(Role::Waiter)->create())->get('/admin')->assertOk();
    }

    /**
     * @return list<array{0: string, 1: string, 2: array<string, mixed>, 3: list<string>, 4: array{0: string, 1: array<string, mixed>}|null}>
     */
    private function routes(Order $order): array
    {
        $sellers = ['owner', 'manager', 'cashier', 'waiter'];
        $managers = ['owner', 'manager'];

        return [
            ['GET', '/admin/dashboard', [], ['owner', 'manager', 'cashier', 'accountant'], null],
            ['GET', '/admin/analytics', [], ['owner', 'manager', 'cashier', 'accountant'], null],
            ['GET', '/admin/orders', [], self::ALL, null],
            ['GET', "/admin/orders/{$order->public_id}", [], self::ALL, null],
            ['GET', '/admin/orders/create', [], $sellers, null],
            ['POST', '/admin/orders', [], $sellers, null],
            ['POST', '/admin/orders/price-preview', ['items' => []], $sellers, null],
            ['PATCH', "/admin/orders/{$order->public_id}/status", ['status' => 'refunded'], $sellers, null],
            ['GET', '/admin/customers', [], ['owner', 'manager', 'cashier', 'waiter', 'accountant'], null],
            ['POST', '/admin/customers', ['name' => 'Nuevo cliente'], ['owner', 'manager', 'cashier'], ['customers', ['name' => 'Nuevo cliente']]],
            ['GET', '/admin/settings/products', [], self::ALL, null],
            ['POST', '/admin/settings/products', [], $managers, null],
            ['POST', '/admin/settings/tags', [], $managers, null],
            ['GET', '/admin/settings/general', [], $managers, null],
            ['PUT', '/admin/settings/general', [], $managers, null],
            ['GET', '/admin/inventory', [], ['owner', 'manager', 'kitchen', 'accountant'], null],
            ['GET', '/admin/inventory/recipes', [], ['owner', 'manager', 'kitchen', 'accountant'], null],
            ['GET', '/admin/inventory/costing', [], ['owner', 'manager', 'kitchen', 'accountant'], null],
            ['POST', '/admin/inventory/categories', ['name' => 'Congelados'], $managers, ['inventory_categories', ['name' => 'Congelados']]],
            ['POST', '/admin/inventory/adjustments', [], $managers, null],
            ['GET', '/admin/management', [], ['owner', 'manager', 'accountant'], null],
            ['GET', '/admin/management/audit', [], ['owner', 'manager', 'accountant'], null],
            ['GET', '/admin/management/purchases', [], ['owner', 'manager', 'accountant'], null],
            ['POST', '/admin/management/wallets', ['name' => 'Caja chica'], $managers, ['wallets', ['name' => 'Caja chica']]],
            ['POST', '/admin/management/outflows', [], $managers, null],
            ['POST', '/admin/management/purchases', [], $managers, null],
            ['GET', '/admin/restaurant/floor', [], $sellers, null],
            ['POST', '/admin/restaurant/areas', ['name' => 'Salón', 'width' => 1600, 'height' => 1000, 'is_active' => true], $managers, ['dining_areas', ['name' => 'Salón']]],
            ['GET', '/admin/marketing', [], $managers, null],
            ['POST', '/admin/marketing/featured-products', [], $managers, null],
            ['GET', '/admin/marketing/instagram', [], $managers, null],
            ['POST', '/admin/marketing/instagram/schedules', [], $managers, null],
            ['GET', '/admin/users', [], ['owner'], null],
            ['POST', '/admin/users', ['name' => 'Intruso', 'email' => 'intruso@example.com', 'password' => 'temporal-123', 'role' => 'owner'], ['owner'], ['users', ['email' => 'intruso@example.com']]],
        ];
    }
}
