<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property int $id
 * @property int|null $inventory_transaction_id */
final class OrderInventoryConsumptionReversal extends Model
{
    protected $table = 'order_inventory_consumption_reversals';

    protected $fillable = ['order_inventory_consumption_id', 'inventory_transaction_id', 'effective_date', 'created_by'];

    protected function casts(): array
    {
        return ['effective_date' => 'immutable_date'];
    }

    /** @return BelongsTo<InventoryTransaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(InventoryTransaction::class, 'inventory_transaction_id');
    }
}
