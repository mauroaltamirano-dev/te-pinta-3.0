<?php

namespace App\Modules\Products\Actions;

use App\Modules\Orders\Enums\OrderSettingKey;
use App\Modules\Orders\Models\OrderSetting;
use App\Modules\Products\Enums\ProductPricingFamily;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Pricing\EmpanadaUnitPrice;
use LogicException;

/** Stored empanada unit prices change only when a dozen price or the surcharge percentage changes. */
final class RecalculateEmpanadaUnitPrices
{
    /** Call inside the transaction that stores the unit price, before locking the product row. */
    public function forDozen(int $dozenPrice): int
    {
        // Shared lock: a concurrent percentage change waits for this product save, or this read waits for it.
        $percent = OrderSetting::query()->where('key', OrderSettingKey::EmpanadaUnitSurchargePercent->value)
            ->sharedLock()->value('integer_value');

        if ($percent === null) {
            throw new LogicException('Missing integer order setting '.OrderSettingKey::EmpanadaUnitSurchargePercent->value.'.');
        }

        return EmpanadaUnitPrice::fromDozen($dozenPrice, (int) $percent);
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
