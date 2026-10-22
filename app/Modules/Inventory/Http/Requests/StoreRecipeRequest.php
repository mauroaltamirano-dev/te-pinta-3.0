<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Models\InventoryItem;
use Illuminate\Foundation\Http\FormRequest;

final class StoreRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', InventoryItem::class) ?? false;
    }

    /** @return array<string,list<string>> */
    public function rules(): array
    {
        // Name uniqueness (case-insensitive, active recipes only) is enforced by CreateRecipe under its lock.
        return ['name' => ['required', 'string', 'max:160']] + (new StoreRecipeVersionRequest)->rules();
    }
}
