<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Inventory\Enums\UnitFamily;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $inventory_category_id
 * @property string $name
 * @property bool $tracks_stock
 * @property string|null $kind
 * @property bool $recipe_input_enabled
 * @property bool $production_output_enabled
 * @property bool $order_consumption_enabled
 * @property numeric-string|null $reference_mass_grams_per_unit
 * @property UnitFamily $unit_family
 * @property int $base_unit_id
 * @property int $default_input_unit_id
 * @property int $quantity_precision
 * @property bool $is_active
 * @property numeric-string $stock_quantity
 */
final class InventoryItem extends Model
{
    protected $fillable = [
        'inventory_category_id', 'name', 'tracks_stock', 'unit_family', 'base_unit_id',
        'default_input_unit_id', 'quantity_precision', 'is_active', 'kind',
        'recipe_input_enabled', 'production_output_enabled', 'order_consumption_enabled',
        'reference_mass_grams_per_unit',
    ];

    protected function casts(): array
    {
        return [
            'tracks_stock' => 'boolean',
            'unit_family' => UnitFamily::class,
            'quantity_precision' => 'integer',
            'is_active' => 'boolean',
            'recipe_input_enabled' => 'boolean',
            'production_output_enabled' => 'boolean',
            'order_consumption_enabled' => 'boolean',
        ];
    }

    /** @return BelongsTo<InventoryCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'inventory_category_id');
    }

    /** @return BelongsTo<InventoryUnit, $this> */
    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(InventoryUnit::class, 'base_unit_id');
    }

    /** @return BelongsTo<InventoryUnit, $this> */
    public function defaultInputUnit(): BelongsTo
    {
        return $this->belongsTo(InventoryUnit::class, 'default_input_unit_id');
    }

    /** @return HasMany<PurchasePresentation, $this> */
    public function presentations(): HasMany
    {
        return $this->hasMany(PurchasePresentation::class);
    }

    /** @return HasMany<StockMovement, $this> */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /** @return HasMany<InventoryCostLayer, $this> */
    public function costLayers(): HasMany
    {
        return $this->hasMany(InventoryCostLayer::class);
    }
}
