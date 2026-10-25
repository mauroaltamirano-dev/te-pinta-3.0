<?php

namespace App\Modules\Settings\Http\Requests;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateRestaurantIdentityRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:60'],
            'eyebrow' => ['nullable', 'string', 'max:60'],
            'headline' => ['nullable', 'string', 'max:80'],
            'headline_highlight' => ['nullable', 'string', 'max:30', function (string $attribute, mixed $value, Closure $fail): void {
                $headline = $this->input('headline');

                if (! is_string($headline) || ! str_contains($headline, (string) $value)) {
                    $fail('La parte destacada tiene que aparecer tal cual en el título.');
                }
            }],
            'intro' => ['nullable', 'string', 'max:200'],
            'home_title' => ['nullable', 'string', 'max:60'],
            'highlights' => ['array', 'max:3'],
            'highlights.*' => ['required', 'string', 'max:60'],
            'footer_text' => ['nullable', 'string', 'max:200'],
            'location' => ['nullable', 'string', 'max:60'],
            'coming_soon_text' => ['nullable', 'string', 'max:200'],
            'empty_cart_text' => ['nullable', 'string', 'max:200'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Escribí el nombre del restaurante.',
            'highlights.max' => 'Cargá como máximo 3 frases destacadas.',
        ];
    }

    /**
     * @return array{name: string, eyebrow: ?string, headline: ?string, headline_highlight: ?string, intro: ?string, home_title: ?string, highlights: list<string>, footer_text: ?string, location: ?string, coming_soon_text: ?string, empty_cart_text: ?string}
     */
    public function identity(): array
    {
        $text = fn (string $field): ?string => is_string($value = $this->validated($field)) ? $value : null;
        /** @var list<string> $highlights */
        $highlights = array_values($this->validated('highlights', []));

        return [
            'name' => (string) $this->validated('name'),
            'eyebrow' => $text('eyebrow'),
            'headline' => $text('headline'),
            'headline_highlight' => $text('headline_highlight'),
            'intro' => $text('intro'),
            'home_title' => $text('home_title'),
            'highlights' => $highlights,
            'footer_text' => $text('footer_text'),
            'location' => $text('location'),
            'coming_soon_text' => $text('coming_soon_text'),
            'empty_cart_text' => $text('empty_cart_text'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $highlights = $this->input('highlights');

        // Blank rows of the form are dropped instead of rejected.
        if (is_array($highlights)) {
            $this->merge(['highlights' => array_values(array_filter($highlights, fn (mixed $highlight): bool => $highlight !== null && $highlight !== ''))]);
        }
    }
}
