<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Inventory\Support\DecimalMath;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $inventory_item_id
 * @property int $inventory_transaction_id
 * @property int $stock_movement_id
 * @property numeric-string $quantity
 * @property int $total_cost
 * @property CarbonImmutable $effective_date
 */
final class InventoryCostLayer extends Model
{
    protected $fillable = [
        'inventory_item_id', 'inventory_transaction_id', 'stock_movement_id',
        'quantity', 'total_cost', 'effective_date',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:6', 'total_cost' => 'integer', 'effective_date' => 'immutable_date'];
    }

    /** @return BelongsTo<InventoryItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    /** @return BelongsTo<InventoryTransaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(InventoryTransaction::class, 'inventory_transaction_id');
    }

    /** @return BelongsTo<StockMovement, $this> */
    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }

    public function unitCost(int $scale = 9): string
    {
        return DecimalMath::divideIntegerByDecimal($this->total_cost, $this->quantity, $scale);
    }
}
