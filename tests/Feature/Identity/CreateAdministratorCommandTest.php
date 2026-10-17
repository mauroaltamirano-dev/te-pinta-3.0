<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class CreateAdministratorCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_create_gives_the_new_user_an_owner_membership(): void
    {
        $this->artisan('admin:create', ['email' => 'duena@example.com'])
            ->expectsQuestion('Nombre', 'Dueña')
            ->expectsQuestion('Contraseña', 'Temporal-2026')
            ->assertSuccessful();

        $user = User::query()->where('email', 'duena@example.com')->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseHas('restaurant_user', ['restaurant_id' => 1, 'user_id' => $user->id, 'role' => 'owner', 'is_active' => true]);

        $this->post(route('login.store'), ['email' => 'duena@example.com', 'password' => 'Temporal-2026']);
        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_create_rejects_invalid_input_without_creating_anything(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->expectException(ValidationException::class);

        try {
            $this->artisan('admin:create', ['email' => 'taken@example.com'])
                ->expectsQuestion('Nombre', 'Otra')
                ->expectsQuestion('Contraseña', 'Temporal-2026')
                ->run();
        } finally {
            $this->assertDatabaseCount('users', 1);
            $this->assertDatabaseCount('restaurant_user', 0);
        }
    }
}
