<?php

namespace App\Modules\Orders\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RestoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('restore', $this->route('order')) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [];
    }
}
