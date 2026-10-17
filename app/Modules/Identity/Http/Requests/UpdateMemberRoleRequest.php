<?php

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class UpdateMemberRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::UsersManage->value) === true;
    }

    /**
     * @return array<string, array<int, Enum|string>>
     */
    public function rules(): array
    {
        return ['role' => ['required', Rule::enum(Role::class)]];
    }

    public function role(): Role
    {
        return Role::from($this->string('role')->toString());
    }

    public function attributes(): array
    {
        return ['role' => 'rol'];
    }
}
