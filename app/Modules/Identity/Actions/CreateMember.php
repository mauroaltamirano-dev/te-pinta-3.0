<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates a staff user with a verified email and an active membership in the
 * current restaurant. Callers validate the input.
 */
final class CreateMember
{
    public function execute(string $name, string $email, string $password, Role $role): User
    {
        return DB::transaction(function () use ($name, $email, $password, $role): User {
            $user = User::query()->create(['name' => $name, 'email' => $email, 'password' => $password]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->memberships()->make()->forceFill(['role' => $role, 'is_active' => true])->save();

            return $user;
        });
    }
}
