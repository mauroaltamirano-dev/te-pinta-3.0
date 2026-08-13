<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('admin.dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_administrators_can_visit_the_dashboard_section()
    {
        $user = User::factory()->administrator()->create();
        $this->actingAs($user);

        $response = $this->get(route('admin.dashboard'));
        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/dashboard'));
    }

    public function test_authenticated_non_administrators_are_forbidden()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }
}
