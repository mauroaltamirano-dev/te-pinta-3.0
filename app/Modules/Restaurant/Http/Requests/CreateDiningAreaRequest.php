<?php

namespace App\Modules\Restaurant\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateDiningAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('administrator') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:100'], 'width' => ['required', 'integer', 'between:100,10000'], 'height' => ['required', 'integer', 'between:100,10000'], 'is_active' => ['required', 'boolean']];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }
}
