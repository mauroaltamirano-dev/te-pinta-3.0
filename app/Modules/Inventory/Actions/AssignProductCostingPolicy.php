<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\CostingPolicy;
use App\Modules\Products\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AssignProductCostingPolicy
{
    public function execute(int $productId, ?int $policyId): void
    {
        DB::transaction(function () use ($productId, $policyId): void {
            Product::query()->whereKey($productId)->lockForUpdate()->firstOrFail();
            if ($policyId !== null) {
                $p = CostingPolicy::query()->whereKey($policyId)->lockForUpdate()->first();
                if ($p === null || ! $p->is_active) {
                    throw ValidationException::withMessages(['costing_policy_id' => 'Elegí una política de costo activa.']);
                }
            }
            DB::table('inventory_product_costing_policies')->where('product_id', $productId)->delete();
            if ($policyId !== null) {
                DB::table('inventory_product_costing_policies')->insert(['product_id' => $productId, 'costing_policy_id' => $policyId, 'created_at' => now(), 'updated_at' => now()]);
            }
        });
    }
}
