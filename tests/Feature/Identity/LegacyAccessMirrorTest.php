<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Enums\StaffProfile;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The legacy `is_admin` / `staff_profile` columns are never read, but they
 * mirror memberships for one release so a rollback to the previous release
 * keeps the same people in and the same people out.
 */
final class LegacyAccessMirrorTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->withRole(Role::Owner)->create();
    }

    public function test_created_members_get_the_matching_legacy_access(): void
    {
        foreach (['owner', 'manager', 'cashier', 'waiter', 'kitchen', 'accountant'] as $role) {
            $this->actingAs($this->owner)->post('/admin/users', [
                'name' => ucfirst($role),
                'email' => "{$role}@example.com",
                'password' => 'Temporal-2026',
                'role' => $role,
            ])->assertSessionHasNoErrors();
        }

        $this->assertLegacy('owner@example.com', true, null);
        $this->assertLegacy('cashier@example.com', false, StaffProfile::Cashier);
        $this->assertLegacy('waiter@example.com', false, StaffProfile::Waiter);
        foreach (['manager', 'kitchen', 'accountant'] as $role) {
            $this->assertLegacy("{$role}@example.com", false, null);
        }
    }

    public function test_role_changes_and_deactivation_update_the_legacy_access(): void
    {
        $member = User::factory()->withRole(Role::Owner)->create(['email' => 'socia@example.com']);
        $this->assertLegacy('socia@example.com', true, null);

        $this->actingAs($this->owner)->patch("/admin/users/{$member->id}/role", ['role' => 'waiter'])->assertSessionHasNoErrors();
        $this->assertLegacy('socia@example.com', false, StaffProfile::Waiter);

        $this->actingAs($this->owner)->patch("/admin/users/{$member->id}/role", ['role' => 'owner'])->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post("/admin/users/{$member->id}/deactivate")->assertSessionHasNoErrors();
        $this->assertLegacy('socia@example.com', false, null);

        $this->actingAs($this->owner)->post("/admin/users/{$member->id}/activate")->assertSessionHasNoErrors();
        $this->assertLegacy('socia@example.com', true, null);
    }

    public function test_admin_create_gives_legacy_administrator_access(): void
    {
        $this->artisan('admin:create', ['email' => 'nueva@example.com'])
            ->expectsQuestion('Nombre', 'Nueva')
            ->expectsQuestion('Contraseña', 'Temporal-2026-Segura')
            ->assertSuccessful();

        $this->assertLegacy('nueva@example.com', true, null);
    }

    private function assertLegacy(string $email, bool $isAdmin, ?StaffProfile $profile): void
    {
        $user = User::query()->where('email', $email)->firstOrFail();
        $this->assertSame($isAdmin, $user->is_admin, "is_admin of {$email}");
        $this->assertSame($profile, $user->staff_profile, "staff_profile of {$email}");
    }
}
