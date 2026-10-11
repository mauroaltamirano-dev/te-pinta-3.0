<?php

namespace App\Modules\Inventory\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $recipe_version_id
 * @property int $output_inventory_item_id
 * @property numeric-string $expected_output_base_quantity
 * @property numeric-string $actual_output_quantity
 * @property int $output_unit_id
 * @property numeric-string $actual_output_base_quantity
 * @property CarbonImmutable $effective_date
 * @property int|null $total_cost
 * @property array<int, array{inventory_item_id:int, code:string}> $operational_warnings
 * @property int|null $inventory_transaction_id
 * @property RecipeVersion $recipeVersion
 * @property InventoryItem $outputItem
 * @property InventoryTransaction|null $transaction
 */
final class ProductionBatch extends Model
{
    protected $table = 'inventory_production_batches';

    protected $hidden = ['creation_xid', 'fingerprint'];

    protected $fillable = ['recipe_version_id', 'output_inventory_item_id', 'expected_output_base_quantity', 'actual_output_quantity', 'output_unit_id', 'actual_output_base_quantity', 'effective_date', 'created_by', 'notes', 'inventory_transaction_id', 'total_cost', 'idempotency_key', 'fingerprint', 'operational_warnings', 'cost_coverage'];

    protected function casts(): array
    {
        return ['expected_output_base_quantity' => 'decimal:6', 'actual_output_quantity' => 'decimal:6', 'actual_output_base_quantity' => 'decimal:6', 'effective_date' => 'immutable_date', 'total_cost' => 'integer', 'operational_warnings' => 'array'];
    }

    /** @return BelongsTo<RecipeVersion, $this> */
    public function recipeVersion(): BelongsTo
    {
        return $this->belongsTo(RecipeVersion::class);
    }

    /** @return BelongsTo<InventoryItem, $this> */
    public function outputItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'output_inventory_item_id');
    }

    /** @return BelongsTo<InventoryTransaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(InventoryTransaction::class, 'inventory_transaction_id');
    }

    /** @return HasMany<ProductionCostAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(ProductionCostAllocation::class);
    }

    /** @return HasOne<ProductionReversal, $this> */
    public function reversal(): HasOne
    {
        return $this->hasOne(ProductionReversal::class);
    }
}
