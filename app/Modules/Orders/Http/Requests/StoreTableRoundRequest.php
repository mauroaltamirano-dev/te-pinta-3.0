<?php

namespace App\Modules\Orders\Http\Requests;

use App\Modules\Orders\Enums\TableRoundKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Shape of one round from the waiter screen; ConfirmTableRound owns the table, session and role rules. */
final class StoreTableRoundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canUsePos() === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'expected_session_id' => ['nullable', 'integer'],
            'kind' => ['required', Rule::enum(TableRoundKind::class)],
            'items' => ['sometimes', 'array', 'max:60'],
            'items.*.product_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:120'],
            'addons' => ['sometimes', 'array', 'max:30'],
            'addons.*.addon_id' => ['required', 'integer', 'distinct'],
            'addons.*.quantity' => ['required', 'integer', 'min:1', 'max:120'],
            'note' => ['nullable', 'string', 'max:300'],
            'round_key' => ['required', 'uuid'],
        ];
    }

    /** @return array<int, array{product_id: int, quantity: int}> */
    public function items(): array
    {
        return array_map(fn (array $line): array => ['product_id' => (int) $line['product_id'], 'quantity' => (int) $line['quantity']], $this->validated('items', []));
    }

    /** @return array<int, array{addon_id: int, quantity: int}> */
    public function addons(): array
    {
        return array_map(fn (array $line): array => ['addon_id' => (int) $line['addon_id'], 'quantity' => (int) $line['quantity']], $this->validated('addons', []));
    }
}
