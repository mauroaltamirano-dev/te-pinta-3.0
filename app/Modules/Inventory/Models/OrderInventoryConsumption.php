<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** @property int $id
 * @property int $order_id
 * @property int|null $inventory_transaction_id
 * @property int|null $total_cost
 * @property string $cost_coverage */
final class OrderInventoryConsumption extends Model
{
    protected $table = 'order_inventory_consumptions';

    protected $fillable = ['order_id', 'inventory_transaction_id', 'effective_date', 'created_by', 'total_cost', 'cost_coverage', 'warnings', 'products_snapshot'];

    protected function casts(): array
    {
        return ['total_cost' => 'integer', 'effective_date' => 'immutable_date', 'warnings' => 'array', 'products_snapshot' => 'array'];
    }

    /** @return HasMany<OrderInventoryConsumptionComponent, $this> */
    public function components(): HasMany
    {
        return $this->hasMany(OrderInventoryConsumptionComponent::class);
    }

    /** @return BelongsTo<InventoryTransaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(InventoryTransaction::class, 'inventory_transaction_id');
    }

    /** @return HasOne<OrderInventoryConsumptionReversal, $this> */
    public function reversal(): HasOne
    {
        return $this->hasOne(OrderInventoryConsumptionReversal::class);
    }
}
