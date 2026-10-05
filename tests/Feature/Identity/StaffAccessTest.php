<?php

namespace Tests\Feature\Identity;

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

    public function test_legacy_admin_keeps_all_capabilities_without_a_staff_backfill(): void
    {
        $user = User::factory()->administrator()->create();
        $this->assertNull($user->staff_profile);
        $this->assertTrue($user->isAdministrator());
        $this->assertTrue($user->canUsePos());
        $this->assertTrue($user->canOperateCashier());
        foreach (['use-pos', 'operate-cashier', 'administrator'] as $ability) {
            $this->assertTrue(Gate::forUser($user)->allows($ability));
        }
        $this->actingAs($user)->get(route('admin.orders.index'))->assertOk();
    }

    public function test_fixed_waiter_and_cashier_capabilities_do_not_grant_administrator_access(): void
    {
        foreach (['waiter' => false, 'cashier' => true] as $profile => $cashier) {
            $user = User::factory()->create(['staff_profile' => $profile]);
            $this->assertTrue($user->canUsePos());
            $this->assertSame($cashier, $user->canOperateCashier());
            $this->assertFalse($user->isAdministrator());
            $this->assertTrue(Gate::forUser($user)->allows('use-pos'));
            $this->assertSame($cashier, Gate::forUser($user)->allows('operate-cashier'));
            $this->assertFalse(Gate::forUser($user)->allows('administrator'));
            foreach (['/admin/orders', '/admin/management', '/admin/settings/products', '/admin/settings'] as $path) {
                $this->actingAs($user)->getJson($path)->assertForbidden();
            }
            $this->postJson(route('admin.orders.store'), [])->assertForbidden();
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
        $waiter = User::factory()->create(['staff_profile' => 'waiter']);
        $this->actingAs($waiter)->getJson('/test-pos-access')->assertNoContent();
        $this->getJson('/test-cashier-access')->assertForbidden();
        $cashier = User::factory()->create(['staff_profile' => 'cashier']);
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
        $admin = User::factory()->administrator()->create();
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
