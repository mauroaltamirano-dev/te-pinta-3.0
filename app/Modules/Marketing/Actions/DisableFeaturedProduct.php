<?php

namespace App\Modules\Marketing\Actions;

use App\Modules\Marketing\Models\FeaturedProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class DisableFeaturedProduct
{
    public function execute(FeaturedProduct $featuredProduct, int $adminId): FeaturedProduct
    {
        $disabled = DB::transaction(function () use ($featuredProduct): FeaturedProduct {
            $locked = FeaturedProduct::query()->whereKey($featuredProduct->id)->lockForUpdate()->firstOrFail();
            $locked->update(['is_enabled' => false]);

            return $locked;
        });

        Log::info('[Marketing] featured_product.disabled', [
            'module' => 'Marketing',
            'featured_product_id' => $disabled->id,
            'product_id' => $disabled->product_id,
            'admin_id' => $adminId,
        ]);

        return $disabled->refresh();
    }
}
