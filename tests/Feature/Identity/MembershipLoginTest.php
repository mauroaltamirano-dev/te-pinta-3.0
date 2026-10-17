<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Passkeys;
use Tests\TestCase;

final class MembershipLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_role_with_an_active_membership_can_log_in(): void
    {
        foreach (Role::cases() as $role) {
            $user = User::factory()->withRole($role)->create();

            $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
                ->assertRedirect(route('admin.home', absolute: false));
            $this->assertAuthenticatedAs($user);
            $this->post(route('logout'));
        }
    }

    public function test_users_without_an_active_membership_get_the_wrong_password_failure(): void
    {
        $owner = User::factory()->withRole(Role::Owner)->create();
        $this->from('/login')->post(route('login.store'), ['email' => $owner->email, 'password' => 'wrong-password'])->assertRedirect('/login');
        $expected = session('errors');
        $this->assertGuest();
        $this->assertNotEmpty($expected);

        DB::table('restaurants')->insert(['id' => 2, 'name' => 'Otro', 'slug' => 'otro', 'created_at' => now(), 'updated_at' => now()]);
        $otherRestaurant = User::factory()->create();
        DB::table('restaurant_user')->insert(['restaurant_id' => 2, 'user_id' => $otherRestaurant->id, 'role' => 'owner', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

        $legacy = User::factory()->create();
        $legacy->forceFill(['is_admin' => true, 'staff_profile' => 'cashier'])->save();

        $candidates = [
            'no membership' => User::factory()->create(),
            'inactive membership' => User::factory()->withRole(Role::Owner, active: false)->create(),
            'legacy administrator flag only' => $legacy,
            'other restaurant' => $otherRestaurant,
        ];

        foreach ($candidates as $case => $user) {
            $response = $this->from('/login')->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

            $response->assertRedirect('/login');
            $this->assertGuest();
            $this->assertEquals($expected, session('errors'), $case);
        }
    }

    public function test_a_deactivated_member_loses_access_to_the_admin_on_the_next_request(): void
    {
        $manager = User::factory()->withRole(Role::Manager)->create();
        $this->actingAs($manager)->get('/admin/orders')->assertOk();

        DB::table('restaurant_user')->where('user_id', $manager->id)->update(['is_active' => false]);

        $this->actingAs($manager->fresh())->get('/admin/orders')->assertForbidden();
    }

    public function test_passkey_login_is_refused_without_an_active_membership(): void
    {
        $passkeyFor = fn (User $user) => tap(new Passkey, fn (Passkey $passkey) => $passkey->setRelation('user', $user));
        $request = Request::create('/passkeys/login', 'POST');

        $this->assertTrue(Passkeys::allowsLogin($request, $passkeyFor(User::factory()->withRole(Role::Waiter)->create())));
        $this->assertFalse(Passkeys::allowsLogin($request, $passkeyFor(User::factory()->withRole(Role::Owner, active: false)->create())));
        $this->assertFalse(Passkeys::allowsLogin($request, $passkeyFor(User::factory()->create())));
    }
}
