<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Data\ProductionBatchData;
use App\Modules\Inventory\Enums\InventorySourceType;
use App\Modules\Inventory\Enums\InventoryTransactionType;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryTransaction;
use App\Modules\Inventory\Models\InventoryUnit;
use App\Modules\Inventory\Models\ProductionBatch;
use App\Modules\Inventory\Models\ProductionCostAllocation;
use App\Modules\Inventory\Models\Recipe;
use App\Modules\Inventory\Models\RecipeVersion;
use App\Modules\Inventory\Support\DecimalMath;
use App\Modules\Inventory\Support\MovingWeightedAverageCostAllocator;
use App\Modules\Inventory\Support\ProductionCostProvenance;
use App\Modules\Inventory\Support\UnitConverter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CreateProductionBatch
{
    public function __construct(private readonly UnitConverter $converter, private readonly MovingWeightedAverageCostAllocator $allocator, private readonly ProductionCostProvenance $provenance) {}

    public function execute(ProductionBatchData $data, int $adminId): ProductionBatch
    {
        if (! Str::isUuid($data->idempotencyKey)) {
            throw ValidationException::withMessages(['idempotency_key' => 'A valid UUID is required.']);
        }
        $key = strtolower($data->idempotencyKey);
        $quantity = DecimalMath::normalize($data->actualOutputQuantity, 6);
        $fingerprint = hash_hmac('sha256', json_encode([$data->recipeVersionId, $data->outputUnitId, $quantity, $data->effectiveDate->toDateString(), $data->notes], JSON_THROW_ON_ERROR), config('app.key'));

        return DB::transaction(function () use ($data, $adminId, $key, $quantity, $fingerprint): ProductionBatch {
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?,0))', ['inventory:production:'.$key]);
            $existing = ProductionBatch::query()->where('idempotency_key', $key)->first();
            if ($existing !== null) {
                if (! hash_equals($existing->fingerprint, $fingerprint)) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This key belongs to a different production payload.']);
                }

                return $existing->load('transaction.movements.costLayer', 'allocations');
            }
            $recipeId = RecipeVersion::query()->whereKey($data->recipeVersionId)->value('recipe_id');
            $recipe = Recipe::query()->lockForUpdate()->findOrFail((int) $recipeId);
            $v = RecipeVersion::query()->with('ingredients')->lockForUpdate()->findOrFail($data->recipeVersionId);
            if (! $recipe->is_active || ! $v->is_active || ! $v->is_sealed) {
                throw ValidationException::withMessages(['recipe_version_id' => 'Use the current sealed recipe version.']);
            }
            $ids = $v->ingredients->pluck('inventory_item_id')->push($v->output_inventory_item_id)->unique()->sort()->values();
            $items = InventoryItem::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($items->contains(fn (InventoryItem $i): bool => ! $i->is_active || ! $i->tracks_stock)) {
                throw ValidationException::withMessages(['recipe_version_id' => 'All production items must be active and stock-tracked.']);
            }
            $output = $items->get($v->output_inventory_item_id);
            $unit = InventoryUnit::query()->findOrFail($data->outputUnitId);
            $base = $this->converter->toBase($output, $unit, $quantity);
            $b = ProductionBatch::query()->create(['recipe_version_id' => $v->id, 'output_inventory_item_id' => $output->id, 'expected_output_base_quantity' => $v->expected_output_base_quantity, 'actual_output_quantity' => $quantity, 'output_unit_id' => $unit->id, 'actual_output_base_quantity' => $base, 'effective_date' => $data->effectiveDate, 'created_by' => $adminId, 'notes' => $data->notes, 'idempotency_key' => $key, 'fingerprint' => $fingerprint]);
            $tx = InventoryTransaction::query()->create(['type' => InventoryTransactionType::Production, 'effective_date' => $data->effectiveDate, 'source_type' => InventorySourceType::Production, 'source_id' => (string) $b->id, 'created_by' => $adminId]);
            $itemIds = array_values($ids->map(static fn ($id): int => (int) $id)->all());
            $valuations = $this->allocator->valuationsFor($itemIds);
            $stocks = DB::table('inventory_stock_movements')->whereIn('inventory_item_id', $ids)->selectRaw('inventory_item_id,SUM(quantity)::text AS quantity')->groupBy('inventory_item_id')->get()->keyBy('inventory_item_id');
            $unreliable = $this->provenance->unreliableItems($itemIds);
            $sum = 0;
            $known = 0;
            $warnings = [];
            foreach ($v->ingredients as $ingredient) {
                $id = $ingredient->inventory_item_id;
                $basis = $valuations[$id];
                [$basisQuantity,$basisCost,$cost,$fallback] = $this->allocator->allocate($ingredient->base_quantity, $basis);
                $stock = (string) ($stocks->get($id)->quantity ?? '0');
                if (DecimalMath::compare($stock, '0') > 0 && DecimalMath::compare($stock, $basis['basis_quantity']) !== 0) {
                    $cost = null;
                    $fallback = 'partial_valuation';
                } elseif ($fallback === 'no_cost_history') {
                    $cost = null;
                } elseif (isset($unreliable[$id])) {
                    $fallback ??= 'unreliable_input';
                }
                if ($cost !== null && $fallback === null && DecimalMath::compare($stock, $ingredient->base_quantity) < 0) {
                    $fallback = 'insufficient_stock';
                }
                $m = $tx->movements()->create(['inventory_item_id' => $id, 'quantity' => '-'.$ingredient->base_quantity, 'unit_id' => $items->get($id)->base_unit_id]);
                $l = $cost === null ? null : $tx->costLayers()->create(['inventory_item_id' => $id, 'stock_movement_id' => $m->id, 'quantity' => '-'.$ingredient->base_quantity, 'total_cost' => -$cost, 'effective_date' => $data->effectiveDate, 'valuation_warning' => $fallback]);
                ProductionCostAllocation::query()->create(['production_batch_id' => $b->id, 'stock_movement_id' => $m->id, 'inventory_cost_layer_id' => $l?->id, 'basis_quantity' => $basisQuantity, 'basis_total_cost' => $basisCost, 'allocated_quantity' => $ingredient->base_quantity, 'allocated_cost' => $cost, 'method' => 'moving_weighted_average', 'fallback' => $fallback]);
                if ($cost !== null) {
                    $sum += $cost;
                    $known++;
                }
                if ($fallback !== null) {
                    $warnings[] = ['inventory_item_id' => $id, 'code' => $fallback];
                }
            }
            $coverage = $known === $v->ingredients->count() ? ($warnings === [] ? 'complete' : 'unreliable') : ($known === 0 ? 'unknown' : 'partial');
            $total = $known === $v->ingredients->count() ? $sum : null;
            $m = $tx->movements()->create(['inventory_item_id' => $output->id, 'quantity' => $base, 'unit_id' => $output->base_unit_id]);
            if ($total !== null) {
                $tx->costLayers()->create(['inventory_item_id' => $output->id, 'stock_movement_id' => $m->id, 'quantity' => $base, 'total_cost' => $total, 'effective_date' => $data->effectiveDate, 'valuation_warning' => $coverage === 'unreliable' ? 'unreliable_input' : null]);
            }
            $b->update(['inventory_transaction_id' => $tx->id, 'total_cost' => $total, 'cost_coverage' => $coverage, 'operational_warnings' => $warnings]);

            return $b->fresh()->load('recipeVersion.ingredients.item.baseUnit', 'outputItem.baseUnit', 'transaction.movements.costLayer', 'allocations');
        });
    }
}
