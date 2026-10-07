<?php

namespace App\Modules\Management\Models;

use App\Modules\Inventory\Models\InventoryCostLayer;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryUnit;
use App\Modules\Inventory\Models\PurchasePresentation;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Management\Enums\PurchasePriceMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PurchaseLine extends Model
{
    protected $fillable = ['inventory_item_id', 'purchase_presentation_id', 'input_unit_id', 'quantity', 'base_quantity',
        'price_mode', 'price_amount', 'line_total', 'item_name', 'category_name', 'tracks_stock', 'base_unit_code',
        'input_unit_code', 'input_unit_factor', 'presentation_name', 'presentation_content_quantity',
        'presentation_unit_code', 'presentation_unit_factor', 'stock_movement_id', 'inventory_cost_layer_id'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:6', 'base_quantity' => 'decimal:6', 'price_mode' => PurchasePriceMode::class,
            'price_amount' => 'integer', 'line_total' => 'integer', 'tracks_stock' => 'boolean',
            'input_unit_factor' => 'decimal:9', 'presentation_content_quantity' => 'decimal:6',
            'presentation_unit_factor' => 'decimal:9'];
    }

    /** @return BelongsTo<Purchase, $this> */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /** @return BelongsTo<InventoryItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    /** @return BelongsTo<PurchasePresentation, $this> */
    public function presentation(): BelongsTo
    {
        return $this->belongsTo(PurchasePresentation::class, 'purchase_presentation_id');
    }

    /** @return BelongsTo<InventoryUnit, $this> */
    public function inputUnit(): BelongsTo
    {
        return $this->belongsTo(InventoryUnit::class, 'input_unit_id');
    }

    /** @return BelongsTo<StockMovement, $this> */
    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }

    /** @return BelongsTo<InventoryCostLayer, $this> */
    public function costLayer(): BelongsTo
    {
        return $this->belongsTo(InventoryCostLayer::class, 'inventory_cost_layer_id');
    }
}
