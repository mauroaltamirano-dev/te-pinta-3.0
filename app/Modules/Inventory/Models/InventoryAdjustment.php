<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $inventory_item_id
 * @property int $unit_id
 * @property string $type
 * @property numeric-string $quantity
 * @property numeric-string $delta
 * @property numeric-string $stock_before
 * @property numeric-string|null $physical_count
 * @property int|null $cost_total
 * @property numeric-string|null $cost_basis_quantity
 * @property int|null $cost_basis_total
 * @property string $cost_status
 * @property string $cost_coverage
 * @property string|null $cost_method
 * @property string|null $cost_fallback
 * @property array<int,string> $warnings
 * @property int|null $inventory_transaction_id
 * @property int|null $reversal_of_id
 * @property InventoryTransaction|null $transaction
 */
final class InventoryAdjustment extends Model
{
    protected $table = 'inventory_adjustments';

    protected $hidden = ['creation_xid', 'request_fingerprint'];

    protected $fillable = ['inventory_item_id', 'unit_id', 'type', 'quantity', 'delta', 'stock_before', 'physical_count', 'note', 'effective_date', 'created_by', 'inventory_transaction_id', 'cost_total', 'cost_status', 'cost_coverage', 'cost_basis_quantity', 'cost_basis_total', 'cost_method', 'cost_fallback', 'warnings', 'idempotency_key', 'request_fingerprint', 'reversal_of_id'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:6', 'delta' => 'decimal:6', 'stock_before' => 'decimal:6', 'physical_count' => 'decimal:6', 'cost_basis_quantity' => 'decimal:6', 'cost_total' => 'integer', 'cost_basis_total' => 'integer', 'warnings' => 'array', 'effective_date' => 'immutable_date'];
    }

    /** @return BelongsTo<InventoryItem,$this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    /** @return BelongsTo<InventoryUnit,$this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(InventoryUnit::class);
    }

    /** @return BelongsTo<InventoryTransaction,$this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(InventoryTransaction::class, 'inventory_transaction_id');
    }

    /** @return BelongsTo<User,$this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasOne<self,$this> */
    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reversal_of_id');
    }

    /** @return BelongsTo<self,$this> */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }
}
