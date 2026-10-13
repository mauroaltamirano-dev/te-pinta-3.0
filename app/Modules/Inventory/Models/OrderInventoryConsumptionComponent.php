<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;

/** @property int $id
 * @property string $consumed_base_quantity
 * @property int|null $allocated_cost
 * @property string|null $fallback
 * @property string $product_name_snapshot */
final class OrderInventoryConsumptionComponent extends Model
{
    protected $table = 'order_inventory_consumption_components';

    protected $fillable = ['order_inventory_consumption_id', 'product_id', 'product_name_snapshot', 'consumption_version_id', 'definition_component_id', 'inventory_item_id', 'inventory_item_name_snapshot', 'quantity', 'unit_id', 'unit_code_snapshot', 'base_unit_code_snapshot', 'component_base_quantity', 'commercial_quantity', 'consumed_base_quantity', 'stock_movement_id', 'inventory_cost_layer_id', 'basis_quantity', 'basis_total_cost', 'allocated_cost', 'method', 'fallback'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:6', 'component_base_quantity' => 'decimal:6', 'consumed_base_quantity' => 'decimal:6', 'basis_quantity' => 'decimal:6', 'allocated_cost' => 'integer', 'basis_total_cost' => 'integer'];
    }
}
