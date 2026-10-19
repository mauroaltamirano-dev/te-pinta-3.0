<?php

namespace Tests\Unit\Orders;

use App\Modules\Orders\Models\Order;
use Tests\TestCase;

final class OrderRoundingAdjustmentTest extends TestCase
{
    public function test_fixed_dozen_snapshots_report_their_rounding_adjustment(): void
    {
        $order = new Order([
            'pricing_snapshot_version' => 2,
            'items_subtotal' => 19370,
            'toppings_subtotal' => 0,
            'shipping_fee_snapshot' => 0,
            'discount_amount' => 0,
            'total' => 19400,
        ]);

        self::assertSame(30, $order->roundingAdjustment());
    }

    public function test_version_one_history_never_reports_a_rounding_adjustment(): void
    {
        // Older shapes may not satisfy total = items + toppings + shipping − discount; they were never rounded.
        $order = new Order([
            'pricing_snapshot_version' => 1,
            'items_subtotal' => 19370,
            'toppings_subtotal' => 0,
            'shipping_fee_snapshot' => 0,
            'discount_amount' => 0,
            'total' => 19400,
        ]);

        self::assertSame(0, $order->roundingAdjustment());
    }
}
