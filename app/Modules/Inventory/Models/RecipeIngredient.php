<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $recipe_version_id
 * @property int $inventory_item_id
 * @property int $unit_id
 * @property numeric-string $quantity
 * @property numeric-string $base_quantity
 * @property InventoryItem $item
 * @property InventoryUnit $unit
 */
final class RecipeIngredient extends Model
{
    protected $table = 'inventory_recipe_ingredients';

    protected $fillable = ['recipe_version_id', 'inventory_item_id', 'quantity', 'unit_id', 'base_quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:6', 'base_quantity' => 'decimal:6'];
    }

    /** @return BelongsTo<InventoryItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    /** @return BelongsTo<InventoryUnit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(InventoryUnit::class);
    }
}
