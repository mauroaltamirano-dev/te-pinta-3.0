<?php

namespace App\Modules\Settings\Http\Requests;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class UpdatePublicOrderingHoursRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasPermission(Permission::CatalogManage);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'hours' => ['required', 'array', 'size:7'],
            'hours.*' => ['required', 'array:weekday,opens_at,closes_at'],
            'hours.*.weekday' => ['required', 'integer', 'between:1,7', 'distinct'],
            'hours.*.opens_at' => ['nullable', 'required_with:hours.*.closes_at', 'date_format:H:i'],
            'hours.*.closes_at' => ['nullable', 'required_with:hours.*.opens_at', 'date_format:H:i'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'hours.*.opens_at.required_with' => 'Indicá la hora de apertura o marcá el día como cerrado.',
            'hours.*.closes_at.required_with' => 'Indicá la hora de cierre o marcá el día como cerrado.',
            'hours.*.opens_at.date_format' => 'Usá el formato HH:MM.',
            'hours.*.closes_at.date_format' => 'Usá el formato HH:MM.',
            'hours.*.weekday.distinct' => 'Cada día de la semana va una sola vez.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $hours = $this->input('hours');
        if (! is_array($hours)) {
            return;
        }
        $this->merge(['hours' => array_map(static function (mixed $hour): mixed {
            if (! is_array($hour)) {
                return $hour;
            }
            foreach (['opens_at', 'closes_at'] as $key) {
                $value = $hour[$key] ?? null;
                $hour[$key] = is_string($value) && trim($value) !== '' ? trim($value) : null;
            }

            return $hour;
        }, $hours)]);
    }
}
