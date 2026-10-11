<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Models\InventoryItem;
use Illuminate\Foundation\Http\FormRequest;

final class ReverseInventoryAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', InventoryItem::class) ?? false;
    }

    /** @return array<string,list<string>> */
    public function rules(): array
    {
        return ['effective_date' => ['required', 'date_format:Y-m-d'], 'note' => ['required', 'string', 'max:240']];
    }
}
