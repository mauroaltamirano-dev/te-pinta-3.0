<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class StaffAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_owner_keeps_every_capability_of_the_former_administrator(): void
    {
        $user = User::factory()->withRole(Role::Owner)->create();
        $this->assertTrue($user->canUsePos());
        $this->assertTrue($user->canOperateCashier());
        foreach (['use-pos', 'operate-cashier', ...array_map(fn (Permission $permission) => $permission->value, Permission::cases())] as $ability) {
            $this->assertTrue(Gate::forUser($user)->allows($ability), $ability);
        }
        foreach (['/admin/orders', '/admin/management', '/admin/settings/products', '/admin/inventory', '/admin/restaurant/floor', '/admin/marketing'] as $path) {
            $this->actingAs($user)->get($path)->assertOk();
        }
    }

    public function test_waiter_and_cashier_roles_keep_their_pos_capabilities_without_finance_access(): void
    {
        foreach (['waiter' => false, 'cashier' => true] as $role => $cashier) {
            $user = User::factory()->withRole(Role::from($role))->create();
            $this->assertTrue($user->canUsePos());
            $this->assertSame($cashier, $user->canOperateCashier());
            $this->assertTrue(Gate::forUser($user)->allows('use-pos'));
            $this->assertSame($cashier, Gate::forUser($user)->allows('operate-cashier'));
            $this->assertFalse(Gate::forUser($user)->allows('finance.view'));
            $this->actingAs($user)->getJson('/admin/orders')->assertOk();
            foreach (['/admin/management', '/admin/inventory', '/admin/settings/general'] as $path) {
                $this->actingAs($user)->getJson($path)->assertForbidden();
            }
        }
    }

    public function test_legacy_flags_alone_grant_no_access(): void
    {
        foreach ([['is_admin' => true], ['staff_profile' => 'waiter'], ['staff_profile' => 'cashier']] as $legacy) {
            $user = User::factory()->create();
            $user->forceFill($legacy)->save();
            $this->assertFalse($user->canUsePos());
            $this->assertFalse(Gate::forUser($user)->allows('orders.view'));
            $this->actingAs($user)->getJson('/admin/orders')->assertForbidden();
            $this->postJson(route('admin.orders.price-preview'), [])->assertForbidden();
        }
    }

    public function test_backend_can_middleware_enforces_capabilities_independent_of_frontend(): void
    {
        Route::middleware(['web', 'auth', 'can:use-pos'])->get('/test-pos-access', fn () => response()->noContent());
        Route::middleware(['web', 'auth', 'can:operate-cashier'])->get('/test-cashier-access', fn () => response()->noContent());
        $this->getJson('/test-pos-access')->assertUnauthorized();
        $ordinary = User::factory()->create();
        $this->actingAs($ordinary)->getJson('/test-pos-access')->assertForbidden();
        $waiter = User::factory()->withRole(Role::Waiter)->create();
        $this->actingAs($waiter)->getJson('/test-pos-access')->assertNoContent();
        $this->getJson('/test-cashier-access')->assertForbidden();
        $cashier = User::factory()->withRole(Role::Cashier)->create();
        $this->actingAs($cashier)->getJson('/test-cashier-access')->assertNoContent();
    }

    public function test_privileged_profile_cannot_be_mass_assigned(): void
    {
        $user = User::factory()->create();
        $user->fill(['staff_profile' => 'cashier', 'is_admin' => true])->save();
        $this->assertNull($user->fresh()->staff_profile);
        $this->assertFalse($user->fresh()->is_admin);
    }

    public function test_database_rejects_unknown_staff_profiles(): void
    {
        $this->expectException(QueryException::class);
        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update(['staff_profile' => 'superuser']);
    }

    public function test_staff_migration_refuses_to_discard_assigned_profiles(): void
    {
        User::factory()->create(['staff_profile' => 'waiter']);
        $migration = require database_path('migrations/2026_10_05_000100_add_fixed_staff_profiles.php');
        $this->expectException(\RuntimeException::class);
        $migration->down();
    }

    public function test_staff_migration_keeps_existing_users_and_admin_flags_unchanged(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $ordinary = User::factory()->create();
        $before = User::query()->orderBy('id')->get()->map(fn (User $user) => $user->getRawOriginal())->all();
        $migration = require database_path('migrations/2026_10_05_000100_add_fixed_staff_profiles.php');
        $migration->down();
        $migration->up();
        $after = User::query()->orderBy('id')->get()->map(fn (User $user) => $user->getRawOriginal())->all();
        $this->assertSame($before, $after);
        $this->assertTrue($admin->fresh()->is_admin);
        $this->assertFalse($ordinary->fresh()->is_admin);
    }
}
