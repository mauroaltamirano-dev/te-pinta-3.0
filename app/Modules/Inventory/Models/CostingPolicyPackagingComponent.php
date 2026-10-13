<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property int $inventory_item_id
 * @property int $unit_id
 * @property string $quantity
 * @property string $base_quantity
 */
final class CostingPolicyPackagingComponent extends Model
{
    protected $table = 'inventory_costing_policy_packaging_components';

    protected $fillable = ['inventory_item_id', 'quantity', 'unit_id', 'base_quantity'];

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
        return $this->belongsTo(InventoryUnit::class, 'unit_id');
    }
}
