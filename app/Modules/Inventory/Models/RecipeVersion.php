<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $recipe_id
 * @property int $output_inventory_item_id
 * @property int $output_unit_id
 * @property numeric-string $expected_output_quantity
 * @property numeric-string $expected_output_base_quantity
 * @property bool $is_active
 * @property bool $is_sealed
 * @property Recipe $recipe
 * @property InventoryItem $outputItem
 * @property InventoryUnit $outputUnit
 */
final class RecipeVersion extends Model
{
    protected $table = 'inventory_recipe_versions';

    protected $hidden = ['creation_xid', 'fingerprint'];

    protected $fillable = ['recipe_id', 'version', 'recipe_name_snapshot', 'output_inventory_item_id', 'expected_output_quantity', 'output_unit_id', 'expected_output_base_quantity', 'is_active', 'is_sealed', 'created_by'];

    protected function casts(): array
    {
        return ['expected_output_quantity' => 'decimal:6', 'expected_output_base_quantity' => 'decimal:6', 'is_active' => 'boolean', 'is_sealed' => 'boolean'];
    }

    /** @return BelongsTo<Recipe, $this> */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /** @return BelongsTo<InventoryItem, $this> */
    public function outputItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'output_inventory_item_id');
    }

    /** @return BelongsTo<InventoryUnit, $this> */
    public function outputUnit(): BelongsTo
    {
        return $this->belongsTo(InventoryUnit::class, 'output_unit_id');
    }

    /** @return HasMany<RecipeIngredient, $this> */
    public function ingredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class);
    }
}
