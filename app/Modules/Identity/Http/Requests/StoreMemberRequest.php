<?php

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Enums\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rules\Unique;

final class StoreMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::UsersManage->value) === true;
    }

    /**
     * @return array<string, array<int, ValidationRule|Password|string|Enum|Unique>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', Password::default()],
            'role' => ['required', Rule::enum(Role::class)],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nombre', 'email' => 'email', 'password' => 'contraseña temporal', 'role' => 'rol'];
    }
}
