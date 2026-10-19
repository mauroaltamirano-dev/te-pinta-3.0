<?php

namespace Tests\Support;

use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\DB;

/** Turn a freshly priced order into a version-1 historical row, as written before fixed dozen pricing. */
trait LegacyPricingSnapshots
{
    private function asLegacyVersion1(Order $order): Order
    {
        DB::table('order_items')->where('order_id', $order->id)->update([
            'pricing_snapshot_version' => 1, 'applied_exact_numerator_12' => null, 'cooked_exact_numerator_12' => null,
            'raw_discount_exact_numerator_12' => null, 'surcharge_exact_numerator_12' => null,
        ]);
        DB::table('orders')->where('id', $order->id)->update([
            'pricing_snapshot_version' => 1, 'product_exact_numerator_12' => null, 'cooked_product_exact_numerator_12' => null,
            'raw_discount_exact_numerator_12' => null, 'discount_exact_numerator_1200' => null,
            'net_pre_round_exact_numerator_1200' => null, 'rounding_adjustment_numerator_1200' => null,
            'total' => DB::raw('items_subtotal + toppings_subtotal + shipping_fee_snapshot - discount_amount'),
        ]);

        return $order->refresh();
    }
}
