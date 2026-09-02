<?php

namespace App\Modules\Management\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class DeleteManagementAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('delete', $this->route('managementAttachment')) === true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:3', 'max:240']];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['reason' => is_string($this->input('reason')) ? trim($this->input('reason')) : $this->input('reason')]);
    }
}
