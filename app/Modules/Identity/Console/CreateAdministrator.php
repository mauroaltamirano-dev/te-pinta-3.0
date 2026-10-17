<?php

namespace App\Modules\Identity\Console;

use App\Modules\Identity\Actions\CreateMember;
use App\Modules\Identity\Enums\Role;
use Illuminate\Console\Command;
use Illuminate\Validation\Rules\Password;

final class CreateAdministrator extends Command
{
    protected $signature = 'admin:create {email?}';

    protected $description = 'Crear un usuario Dueño del restaurante actual sin habilitar el registro público';

    public function handle(CreateMember $members): int
    {
        $email = (string) ($this->argument('email') ?: $this->ask('Email'));
        $name = (string) $this->ask('Nombre');
        $password = (string) $this->secret('Contraseña');

        validator(
            compact('email', 'name', 'password'),
            ['email' => ['required', 'email', 'unique:users,email'], 'name' => ['required', 'string', 'max:255'], 'password' => ['required', Password::defaults()]],
        )->validate();

        $members->execute($name, $email, $password, Role::Owner);

        $this->info('Administrador creado con rol Dueño.');

        return self::SUCCESS;
    }
}
