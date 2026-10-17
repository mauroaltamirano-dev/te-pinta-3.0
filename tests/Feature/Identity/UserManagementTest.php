<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Actions\ChangeMemberRole;
use App\Modules\Identity\Actions\SetMembershipActive;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->owner = User::factory()->withRole(Role::Owner)->create(['name' => 'Dueña']);
    }

    public function test_owner_lists_only_the_members_of_the_current_restaurant(): void
    {
        $waiter = User::factory()->withRole(Role::Waiter)->create(['name' => 'Mozo']);
        $inactive = User::factory()->withRole(Role::Kitchen, active: false)->create(['name' => 'Cocinero']);
        User::factory()->create(['name' => 'Sin membresía']);

        $this->actingAs($this->owner)->get('/admin/users')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/index')
            ->has('members', 3)
            ->where('members.0.id', $inactive->id)
            ->where('members.0.role', 'kitchen')
            ->where('members.0.is_active', false)
            ->where('members.2.id', $waiter->id)
            ->where('members.2.role_label', 'Mozo')
            ->where('currentUserId', $this->owner->id)
            ->has('roles', 6)
            ->missing('members.0.password'));
    }

    public function test_owner_creates_a_member_who_can_log_in_with_the_temporary_password(): void
    {
        $this->actingAs($this->owner)->post('/admin/users', [
            'name' => 'Contadora',
            'email' => 'contadora@example.com',
            'password' => 'Temporal-2026',
            'role' => 'accountant',
        ])->assertRedirect('/admin/users')->assertSessionHasNoErrors();

        $created = User::query()->where('email', 'contadora@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('Temporal-2026', $created->password));
        $this->assertNotNull($created->email_verified_at);
        $this->assertFalse($created->is_admin);
        $this->assertNull($created->staff_profile);
        $this->assertDatabaseHas('restaurant_user', ['restaurant_id' => 1, 'user_id' => $created->id, 'role' => 'accountant', 'is_active' => true]);

        $this->post(route('logout'));
        $this->post(route('login.store'), ['email' => 'contadora@example.com', 'password' => 'Temporal-2026']);
        $this->assertAuthenticatedAs($created);
    }

    public function test_member_creation_is_validated_by_the_backend(): void
    {
        $this->actingAs($this->owner)->postJson('/admin/users', ['name' => 'X', 'email' => $this->owner->email, 'password' => 'Temporal-2026', 'role' => 'owner'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/admin/users', ['name' => 'X', 'email' => 'x@example.com', 'password' => 'Temporal-2026', 'role' => 'superuser'])
            ->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->postJson('/admin/users', ['name' => '', 'email' => 'not-an-email', 'password' => '', 'role' => 'waiter'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'password']);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('restaurant_user', 1);
    }

    public function test_owner_changes_roles_and_deactivates_and_reactivates_members(): void
    {
        $member = User::factory()->withRole(Role::Waiter)->create();

        $this->actingAs($this->owner)->patch("/admin/users/{$member->id}/role", ['role' => 'cashier'])->assertRedirect('/admin/users')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('restaurant_user', ['user_id' => $member->id, 'role' => 'cashier', 'is_active' => true]);

        $this->post("/admin/users/{$member->id}/deactivate")->assertRedirect('/admin/users')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('restaurant_user', ['user_id' => $member->id, 'is_active' => false]);
        $this->actingAs($member->fresh())->get('/admin/orders')->assertForbidden();

        $this->actingAs($this->owner)->post("/admin/users/{$member->id}/activate")->assertRedirect('/admin/users')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('restaurant_user', ['user_id' => $member->id, 'role' => 'cashier', 'is_active' => true]);
        $this->actingAs($member->fresh())->get('/admin/orders')->assertOk();
    }

    public function test_nobody_can_demote_or_deactivate_themselves(): void
    {
        $this->actingAs($this->owner)->patchJson("/admin/users/{$this->owner->id}/role", ['role' => 'manager'])
            ->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->postJson("/admin/users/{$this->owner->id}/deactivate")
            ->assertUnprocessable()->assertJsonValidationErrors('membership');

        $this->assertDatabaseHas('restaurant_user', ['user_id' => $this->owner->id, 'role' => 'owner', 'is_active' => true]);
    }

    public function test_another_owner_can_be_demoted_while_an_active_owner_remains(): void
    {
        $partner = User::factory()->withRole(Role::Owner)->create();

        $this->actingAs($this->owner)->patch("/admin/users/{$partner->id}/role", ['role' => 'manager'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('restaurant_user', ['user_id' => $partner->id, 'role' => 'manager']);
    }

    public function test_the_last_active_owner_cannot_be_demoted_or_deactivated(): void
    {
        $partner = User::factory()->withRole(Role::Owner)->create();
        // A concurrent request already deactivated the acting owner: the partner is now the last active owner.
        DB::table('restaurant_user')->where('user_id', $this->owner->id)->update(['is_active' => false]);

        foreach ([
            fn () => app(ChangeMemberRole::class)->execute($this->owner, $partner, Role::Manager),
            fn () => app(SetMembershipActive::class)->execute($this->owner, $partner, false),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('The last active owner must be kept.');
            } catch (ValidationException) {
            }
        }

        $this->assertDatabaseHas('restaurant_user', ['user_id' => $partner->id, 'role' => 'owner', 'is_active' => true]);
    }

    public function test_members_of_other_restaurants_and_unknown_users_are_not_found(): void
    {
        $outsider = User::factory()->create();

        $this->actingAs($this->owner)->patchJson("/admin/users/{$outsider->id}/role", ['role' => 'waiter'])->assertNotFound();
        $this->postJson('/admin/users/999999/deactivate')->assertNotFound();
        $this->assertDatabaseMissing('restaurant_user', ['user_id' => $outsider->id]);
    }

    public function test_only_users_manage_can_change_members(): void
    {
        $manager = User::factory()->withRole(Role::Manager)->create();
        $waiter = User::factory()->withRole(Role::Waiter)->create();

        $this->actingAs($manager)->patchJson("/admin/users/{$waiter->id}/role", ['role' => 'owner'])->assertForbidden();
        $this->postJson("/admin/users/{$waiter->id}/deactivate")->assertForbidden();
        $this->assertDatabaseHas('restaurant_user', ['user_id' => $waiter->id, 'role' => 'waiter', 'is_active' => true]);
    }

    public function test_inertia_shares_the_current_role_and_permissions(): void
    {
        $waiter = User::factory()->withRole(Role::Waiter)->create();

        $this->actingAs($waiter)->get('/admin/orders')->assertInertia(fn (Assert $page) => $page
            ->where('auth.role', 'waiter')
            ->where('auth.permissions', ['orders.view', 'orders.manage', 'customers.view', 'catalog.view']));

        $this->actingAs($this->owner)->get('/admin/orders')->assertInertia(fn (Assert $page) => $page
            ->where('auth.role', 'owner')
            ->has('auth.permissions', 15));
    }
}
