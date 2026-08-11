<?php

namespace App\Modules\Identity\Console;

use App\Modules\Identity\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

final class CreateAdministrator extends Command
{
    protected $signature = 'admin:create {email?}';

    protected $description = 'Create an administrator without exposing public registration';

    public function handle(): int
    {
        $email = (string) ($this->argument('email') ?: $this->ask('Email'));
        $name = (string) $this->ask('Name');
        $password = (string) $this->secret('Password');

        validator(
            compact('email', 'name', 'password'),
            ['email' => ['required', 'email', 'unique:users,email'], 'name' => ['required', 'string', 'max:255'], 'password' => ['required', Password::defaults()]],
        )->validate();

        User::query()->create([
            'email' => $email,
            'name' => $name,
            'password' => Hash::make($password),
        ])->forceFill(['is_admin' => true, 'email_verified_at' => now()])->save();

        $this->info('Administrator created.');

        return self::SUCCESS;
    }
}
