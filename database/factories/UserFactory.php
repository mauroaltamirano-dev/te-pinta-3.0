<?php

namespace Database\Factories;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /** @var class-string<User> */
    protected $model = User::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /**
     * Give the user a membership with this role in the current restaurant.
     */
    public function withRole(Role $role, bool $active = true): static
    {
        return $this->afterCreating(function (User $user) use ($role, $active): void {
            $membership = $user->memberships()->make()->forceFill(['role' => $role, 'is_active' => $active]);
            $membership->save();
            // Like a freshly authenticated request: the membership is already known.
            $user->setRelation('currentMembership', $membership->fresh());
        });
    }

    /**
     * An active owner of the current restaurant (full access).
     */
    public function administrator(): static
    {
        return $this->withRole(Role::Owner);
    }
}
