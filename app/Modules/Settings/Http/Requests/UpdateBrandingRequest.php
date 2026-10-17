<?php

namespace App\Modules\Settings\Http\Requests;

use App\Core\Branding\AccentPalette;
use App\Core\Branding\InvalidAccentColor;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateBrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasPermission(Permission::CatalogManage);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'accent_color' => ['bail', 'required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                try {
                    AccentPalette::fromHex($value);
                } catch (InvalidAccentColor $error) {
                    $fail($error->getMessage());
                }
            }],
        ];
    }

    protected function prepareForValidation(): void
    {
        $accent = $this->input('accent_color');

        if (is_string($accent)) {
            $this->merge(['accent_color' => strtolower(trim($accent))]);
        }
    }
}
