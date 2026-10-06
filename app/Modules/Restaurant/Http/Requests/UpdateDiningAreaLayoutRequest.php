<?php

namespace App\Modules\Restaurant\Http\Requests;

use App\Modules\Restaurant\Support\FloorGeometry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateDiningAreaLayoutRequest extends FormRequest
{
    private const DECIMAL = '/^(?:0(?:\.\d{1,4})?|1(?:\.0{1,4})?)$/';

    private const TABLE_KEYS = ['id', 'code', 'name', 'shape', 'position_x', 'position_y', 'width', 'height', 'rotation', 'capacity', 'is_active'];

    private const OBSTACLE_KEYS = ['type', 'x', 'y', 'width', 'height', 'label'];

    public function authorize(): bool
    {
        return $this->user()?->can('administrator') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $coordinate = ['required', 'regex:'.self::DECIMAL];

        return [
            'name' => ['required', 'string', 'max:100'], 'width' => ['required', 'integer', 'between:100,10000'], 'height' => ['required', 'integer', 'between:100,10000'], 'is_active' => ['required', 'boolean'],
            'expected_version' => ['required', 'integer', 'min:0', 'max:2147483646'],
            'tables' => ['present', 'array', 'list', 'max:200'], 'tables.*' => ['array:'.implode(',', self::TABLE_KEYS)],
            'tables.*.id' => ['sometimes', 'integer', 'min:1', 'distinct'], 'tables.*.code' => ['required', 'string', 'max:40', 'distinct:ignore_case'], 'tables.*.name' => ['nullable', 'string', 'max:100'],
            'tables.*.shape' => ['required', Rule::in(['rectangle', 'circle'])], 'tables.*.position_x' => $coordinate, 'tables.*.position_y' => $coordinate, 'tables.*.width' => $coordinate, 'tables.*.height' => $coordinate,
            'tables.*.rotation' => ['required', 'integer', Rule::in([0, 45, 90, 135, 180, 225, 270, 315])], 'tables.*.capacity' => ['nullable', 'integer', 'between:1,100'], 'tables.*.is_active' => ['required', 'boolean'],
            'obstacles' => ['present', 'array', 'list', 'max:100'], 'obstacles.*' => ['array:'.implode(',', self::OBSTACLE_KEYS)], 'obstacles.*.type' => ['required', Rule::in(['rectangle', 'circle'])],
            'obstacles.*.x' => $coordinate, 'obstacles.*.y' => $coordinate, 'obstacles.*.width' => $coordinate, 'obstacles.*.height' => $coordinate, 'obstacles.*.label' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $changes = [];
        if (is_string($this->input('name'))) {
            $changes['name'] = trim($this->input('name'));
        }
        $tables = $this->input('tables');
        if (is_array($tables)) {
            foreach ($tables as &$table) {
                if (is_array($table) && is_string($table['code'] ?? null)) {
                    $table['code'] = mb_strtoupper(trim($table['code']));
                }
            }
            $changes['tables'] = $tables;
        }
        $this->merge($changes);
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (['tables' => self::TABLE_KEYS, 'obstacles' => self::OBSTACLE_KEYS] as $collection => $allowed) {
                foreach ((array) $this->input($collection, []) as $index => $element) {
                    if (is_array($element)) {
                        foreach (array_diff(array_keys($element), $allowed) as $key) {
                            $validator->errors()->add("{$collection}.{$index}.{$key}", 'Campo no permitido.');
                        }
                    }
                }
            }
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            foreach (['tables' => false, 'obstacles' => true] as $collection => $obstacle) {
                foreach ($this->input($collection) as $index => $element) {
                    foreach (FloorGeometry::errors($element, (int) $this->input('width'), (int) $this->input('height'), $obstacle) as $field => $error) {
                        $validator->errors()->add("{$collection}.{$index}.{$field}", $error);
                    }
                }
            }
        }];
    }
}
