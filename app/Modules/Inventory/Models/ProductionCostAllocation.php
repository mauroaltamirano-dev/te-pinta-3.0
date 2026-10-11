<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $production_batch_id
 * @property int $stock_movement_id
 * @property int|null $inventory_cost_layer_id
 * @property numeric-string $basis_quantity
 * @property int $basis_total_cost
 * @property numeric-string $allocated_quantity
 * @property int|null $allocated_cost
 * @property string $method
 * @property string|null $fallback
 * @property int|null $reversal_of_id
 */
final class ProductionCostAllocation extends Model
{
    protected $table = 'inventory_production_cost_allocations';

    protected $fillable = ['production_batch_id', 'stock_movement_id', 'inventory_cost_layer_id', 'basis_quantity', 'basis_total_cost', 'allocated_quantity', 'allocated_cost', 'method', 'fallback', 'reversal_of_id'];

    protected function casts(): array
    {
        return ['basis_quantity' => 'decimal:6', 'allocated_quantity' => 'decimal:6', 'basis_total_cost' => 'integer', 'allocated_cost' => 'integer'];
    }
}
