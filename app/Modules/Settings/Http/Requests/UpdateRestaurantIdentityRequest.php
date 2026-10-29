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
            'offerings' => ['array', 'max:6'],
            'offerings.*' => ['required', 'string', 'max:60'],
            'dine_in_items' => ['array', 'max:6'],
            'dine_in_items.*' => ['required', 'string', 'max:40'],
            'maps_url' => ['nullable', 'string', 'max:255', 'url:https'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Escribí el nombre del restaurante.',
            'highlights.max' => 'Cargá como máximo 3 frases destacadas.',
            'offerings.max' => 'Cargá como máximo 6 cosas en "Qué ofrecemos".',
            'dine_in_items.max' => 'Cargá como máximo 6 etiquetas del local.',
            'maps_url.url' => 'Pegá un enlace que empiece con https://.',
        ];
    }

    /**
     * @return array{name: string, eyebrow: ?string, headline: ?string, headline_highlight: ?string, intro: ?string, home_title: ?string, highlights: list<string>, footer_text: ?string, location: ?string, coming_soon_text: ?string, empty_cart_text: ?string, offerings: list<string>, dine_in_items: list<string>, maps_url: ?string}
     */
    public function identity(): array
    {
        $text = fn (string $field): ?string => is_string($value = $this->validated($field)) ? $value : null;
        /** @return list<string> */
        $list = fn (string $field): array => array_values($this->validated($field, []));

        return [
            'name' => (string) $this->validated('name'),
            'eyebrow' => $text('eyebrow'),
            'headline' => $text('headline'),
            'headline_highlight' => $text('headline_highlight'),
            'intro' => $text('intro'),
            'home_title' => $text('home_title'),
            'highlights' => $list('highlights'),
            'footer_text' => $text('footer_text'),
            'location' => $text('location'),
            'coming_soon_text' => $text('coming_soon_text'),
            'empty_cart_text' => $text('empty_cart_text'),
            'offerings' => $list('offerings'),
            'dine_in_items' => $list('dine_in_items'),
            'maps_url' => $text('maps_url'),
        ];
    }

    protected function prepareForValidation(): void
    {
        // Blank rows of the form are dropped instead of rejected.
        foreach (['highlights', 'offerings', 'dine_in_items'] as $field) {
            $items = $this->input($field);

            if (is_array($items)) {
                $this->merge([$field => array_values(array_filter($items, fn (mixed $item): bool => $item !== null && $item !== ''))]);
            }
        }
    }
}
