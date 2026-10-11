<?php

namespace App\Modules\Inventory\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $production_batch_id
 * @property int $inventory_transaction_id
 * @property CarbonImmutable $effective_date
 * @property string $reason
 * @property ProductionBatch $batch
 * @property InventoryTransaction $inventoryTransaction
 */
final class ProductionReversal extends Model
{
    protected $table = 'inventory_production_reversals';

    protected $hidden = ['creation_xid', 'fingerprint'];

    protected $fillable = ['production_batch_id', 'inventory_transaction_id', 'effective_date', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['effective_date' => 'immutable_date'];
    }

    /** @return BelongsTo<ProductionBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductionBatch::class, 'production_batch_id');
    }

    /** @return BelongsTo<InventoryTransaction, $this> */
    public function inventoryTransaction(): BelongsTo
    {
        return $this->belongsTo(InventoryTransaction::class, 'inventory_transaction_id');
    }
}
