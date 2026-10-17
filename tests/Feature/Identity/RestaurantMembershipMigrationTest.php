<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class RestaurantMembershipMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_10_17_000000_create_restaurant_memberships.php';

    public function test_migration_converts_legacy_administrators_and_staff_profiles_into_memberships(): void
    {
        $migration = require database_path(self::MIGRATION);
        $migration->down();
        $this->assertFalse(Schema::hasTable('restaurant_user'));

        $admin = $this->legacyUser(['is_admin' => true]);
        $adminWithProfile = $this->legacyUser(['is_admin' => true, 'staff_profile' => 'waiter']);
        $waiter = $this->legacyUser(['staff_profile' => 'waiter']);
        $cashier = $this->legacyUser(['staff_profile' => 'cashier']);
        $ordinary = $this->legacyUser([]);
        $before = DB::table('users')->orderBy('id')->get()->toJson();

        $migration->up();

        $memberships = DB::table('restaurant_user')->orderBy('user_id')->get(['restaurant_id', 'user_id', 'role', 'is_active'])
            ->map(fn (object $row) => [(int) $row->restaurant_id, (int) $row->user_id, $row->role, (bool) $row->is_active])
            ->all();

        $this->assertSame([
            [1, $admin->id, 'owner', true],
            [1, $adminWithProfile->id, 'owner', true],
            [1, $waiter->id, 'waiter', true],
            [1, $cashier->id, 'cashier', true],
        ], $memberships);
        $this->assertDatabaseMissing('restaurant_user', ['user_id' => $ordinary->id]);
        $this->assertSame($before, DB::table('users')->orderBy('id')->get()->toJson(), 'Legacy columns are kept untouched.');
    }

    public function test_memberships_are_unique_per_restaurant_and_role_is_constrained(): void
    {
        $user = $this->legacyUser([]);
        DB::table('restaurant_user')->insert(['user_id' => $user->id, 'role' => 'waiter']);
        $this->assertSame(1, (int) DB::table('restaurant_user')->where('user_id', $user->id)->value('restaurant_id'));

        foreach ([['user_id' => $user->id, 'role' => 'cashier'], ['user_id' => $this->legacyUser([])->id, 'role' => 'superuser']] as $row) {
            try {
                DB::transaction(fn () => DB::table('restaurant_user')->insert($row));
                $this->fail('The database must reject '.json_encode($row));
            } catch (QueryException) {
            }
        }

        $this->assertDatabaseCount('restaurant_user', 1);
    }

    /**
     * @param  array<string, mixed>  $legacy
     */
    private function legacyUser(array $legacy): User
    {
        $user = User::factory()->create();
        $user->forceFill($legacy)->save();

        return $user;
    }
}
