<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $inventory_transaction_id
 * @property int $inventory_item_id
 * @property numeric-string $quantity
 * @property int $unit_id
 */
final class StockMovement extends Model
{
    protected $table = 'inventory_stock_movements';

    protected $fillable = ['inventory_transaction_id', 'inventory_item_id', 'quantity', 'unit_id'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:6'];
    }

    /** @return BelongsTo<InventoryTransaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(InventoryTransaction::class, 'inventory_transaction_id');
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

    /** @return HasOne<InventoryCostLayer, $this> */
    public function costLayer(): HasOne
    {
        return $this->hasOne(InventoryCostLayer::class, 'stock_movement_id');
    }
}
