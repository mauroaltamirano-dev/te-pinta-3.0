<?php

namespace Tests\Feature\Printing;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** The shop PC prints from any admin page, so every page carries the name printed on tickets. */
final class RestaurantNameSharedPropTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_pages_share_the_restaurant_name_with_users_who_print(): void
    {
        $cashier = User::factory()->withRole(Role::Cashier)->create();

        $this->actingAs($cashier)->get('/admin/orders')->assertInertia(fn (Assert $page) => $page
            ->where('restaurantName', 'Te Pinta'));
    }

    public function test_users_who_cannot_print_get_no_restaurant_name(): void
    {
        $accountant = User::factory()->withRole(Role::Accountant)->create();

        $this->actingAs($accountant)->get('/admin/orders')->assertInertia(fn (Assert $page) => $page
            ->where('restaurantName', null));
    }
}
