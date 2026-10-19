<?php

namespace App\Modules\Products\Actions;

use App\Modules\Orders\Enums\OrderSettingKey;
use App\Modules\Orders\Models\OrderSetting;
use App\Modules\Products\Enums\ProductPricingFamily;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Pricing\EmpanadaUnitPrice;

/** Stored empanada unit prices change only when a dozen price or the surcharge percentage changes. */
final class RecalculateEmpanadaUnitPrices
{
    public function forDozen(int $dozenPrice): int
    {
        return EmpanadaUnitPrice::fromDozen($dozenPrice, OrderSetting::integer(OrderSettingKey::EmpanadaUnitSurchargePercent));
    }

    /** Recompute every empanada, soft-deleted ones included, after a percentage change. */
    public function execute(int $surchargePercent): void
    {
        Product::withTrashed()
            ->where('pricing_family', ProductPricingFamily::Empanada)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->each(fn (Product $product) => $product->update([
                'unit_price' => EmpanadaUnitPrice::fromDozen($product->dozen_price, $surchargePercent),
            ]));
    }
}
