<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Tests\TestCase;

final class ValidationMessageTranslationTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_framework_validation_message_has_a_spanish_translation(): void
    {
        $this->assertSame('es', app()->getLocale());
        $english = require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');
        $keys = array_keys(Arr::dot(Arr::except($english, ['custom', 'attributes'])));
        $this->assertNotEmpty($keys);

        $missing = array_values(array_filter($keys, fn (string $key): bool => trans('validation.'.$key) === 'validation.'.$key));

        $this->assertSame([], $missing, 'Validation keys without a Spanish message are shown raw to the user.');
    }

    public function test_production_password_rules_explain_each_requirement_in_spanish(): void
    {
        $validator = Validator::make(['password' => 'aaaaaaaaaaaa'], ['password' => [Password::min(12)->mixedCase()->letters()->numbers()->symbols()]], [], ['password' => 'contraseña temporal']);
        $messages = $validator->errors()->get('password');

        $this->assertSame([
            'La contraseña temporal debe tener al menos una mayúscula y una minúscula.',
            'La contraseña temporal debe tener al menos un símbolo.',
            'La contraseña temporal debe tener al menos un número.',
        ], $messages);
    }

    public function test_member_creation_never_shows_a_raw_validation_key(): void
    {
        Password::defaults(fn (): Password => Password::min(12)->mixedCase()->letters()->numbers()->symbols());
        $owner = User::factory()->withRole(Role::Owner)->create();

        $response = $this->actingAs($owner)->postJson('/admin/users', ['name' => 'Nueva', 'email' => 'nueva@example.com', 'password' => 'temporal', 'role' => 'waiter'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $messages = Arr::flatten($response->json('errors'));
        $this->assertNotEmpty($messages);
        foreach ($messages as $message) {
            $this->assertStringNotContainsString('validation.', $message);
        }
        $this->assertContains('La contraseña temporal debe tener al menos un símbolo.', $messages);
        $this->assertDatabaseMissing('users', ['email' => 'nueva@example.com']);
    }
}
