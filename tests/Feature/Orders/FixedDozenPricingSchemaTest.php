<?php

namespace Tests\Feature\Orders;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Actions\CreateOrder;
use App\Modules\Orders\Data\OrderCheckoutData;
use App\Modules\Orders\Enums\DeliveryType;
use App\Modules\Orders\Enums\OrderCondition;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Support\LegacyPricingSnapshots;
use Tests\TestCase;

final class FixedDozenPricingSchemaTest extends TestCase
{
    use LegacyPricingSnapshots;
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_10_19_000000_activate_fixed_dozen_pricing.php';

    private const COMBINED_MIGRATION = 'migrations/2026_10_22_000100_allow_combined_dozen_snapshots.php';

    private int $productId;

    public function test_v2_order_total_is_a_multiple_of_100_reconciled_through_exact_rounding(): void
    {
        $order = $this->order();

        // 3 × 1234 = 3702; ARS 100 half-up rounding gives 3700 with a -2 peso adjustment.
        DB::table('orders')->where('id', $order->id)->update($this->v2Totals(3702, 3700));
        self::assertSame(3700, (int) DB::table('orders')->where('id', $order->id)->value('total'));

        $this->assertRejected(fn () => DB::table('orders')->where('id', $order->id)->update($this->v2Totals(3702, 3702)), 'orders_total_matches_breakdown');
        $this->assertRejected(fn () => DB::table('orders')->where('id', $order->id)->update([...$this->v2Totals(3702, 3700), 'rounding_adjustment_numerator_1200' => 0]), 'orders_total_matches_breakdown');
        $this->assertRejected(fn () => DB::table('orders')->where('id', $order->id)->update($this->v2Totals(3702, 3800)), 'orders_total_matches_breakdown');
    }

    public function test_v1_orders_keep_their_exact_whole_peso_breakdown(): void
    {
        $order = $this->order();

        self::assertSame(1, (int) DB::table('orders')->where('id', $order->id)->value('pricing_snapshot_version'));
        self::assertSame(3702, (int) DB::table('orders')->where('id', $order->id)->value('total'));
        $this->assertRejected(fn () => DB::table('orders')->where('id', $order->id)->update(['total' => 3700]), 'orders_total_matches_breakdown');
    }

    public function test_v2_products_subtotals_equal_their_exact_numerators(): void
    {
        $order = $this->order();

        $this->assertRejected(fn () => DB::table('orders')->where('id', $order->id)->update($this->v2Totals(3702, 3700, productExact12: 3702 * 12 + 1)), 'orders_fixed_dozen_products_valid');
        $this->assertRejected(fn () => DB::table('orders')->where('id', $order->id)->update([...$this->v2Totals(3702, 3700), 'items_cooked_subtotal' => null, 'raw_discount' => null]), 'orders_fixed_dozen_products_valid');
    }

    public function test_v2_discounts_are_exact_over_1200_and_shipping_is_not_discounted(): void
    {
        $order = $this->order();
        // 10% of 3702 = 370.2 exact → amount 370; net 3331.8 → total 3300.
        $percentage = [
            ...$this->v2Totals(3702, 3300, discountExact: 370_2 * 120),
            'discount_type' => 'percentage', 'discount_value' => 10, 'discount_amount' => 370,
        ];
        DB::table('orders')->where('id', $order->id)->update($percentage);

        $this->assertRejected(fn () => DB::table('orders')->where('id', $order->id)->update([...$percentage, 'discount_amount' => 371]), 'orders_discount_valid');
        $this->assertRejected(fn () => DB::table('orders')->where('id', $order->id)->update([...$percentage, 'discount_value' => 20]), 'orders_discount_valid');

        $fixed = [
            ...$this->v2Totals(3702, 2700, discountExact: 1000 * 1200),
            'discount_type' => 'fixed_amount', 'discount_value' => 1000, 'discount_amount' => 1000,
        ];
        DB::table('orders')->where('id', $order->id)->update($fixed);
        $this->assertRejected(fn () => DB::table('orders')->where('id', $order->id)->update([...$this->v2Totals(3702, 2700, discountExact: 999 * 1200), 'discount_type' => 'fixed_amount', 'discount_value' => 1000, 'discount_amount' => 999]), 'orders_discount_valid');
    }

    public function test_v2_lines_are_whole_peso_dozens_or_units_of_one_product(): void
    {
        $order = $this->order();
        $dozen = $this->v2Item($order, [
            'pricing_mode' => 'dozen', 'quantity' => 2, 'units_count' => 24,
            'unit_price' => 18000, 'line_total' => 36000, 'cooked_unit_price' => 20000, 'cooked_line_total' => 40000,
            'raw_discount' => 4000, 'regular_price_total' => 36000, 'savings' => 0,
            'applied_exact_numerator_12' => 36000 * 12, 'cooked_exact_numerator_12' => 40000 * 12,
            'raw_discount_exact_numerator_12' => 4000 * 12, 'surcharge_exact_numerator_12' => 0,
        ]);
        DB::table('order_items')->insert($dozen);
        self::assertSame(1, DB::table('order_items')->where('order_id', $order->id)->where('pricing_mode', 'dozen')->count());

        $this->assertRejected(fn () => DB::table('order_items')->insert([...$dozen, 'applied_exact_numerator_12' => 36000 * 12 + 1, 'cooked_exact_numerator_12' => 40000 * 12 + 1]), 'order_items_fixed_dozen_snapshot_valid');
        $this->assertRejected(fn () => DB::table('order_items')->insert([...$dozen, 'surcharge_exact_numerator_12' => 12]), 'order_items_fixed_dozen_snapshot_valid');
        $this->assertRejected(fn () => DB::table('order_items')->insert([...$dozen, 'pricing_mode' => 'half_dozen', 'units_count' => 12]), 'order_items_fixed_dozen_snapshot_valid');
        $this->assertRejected(fn () => DB::table('order_items')->insert([...$dozen, 'pricing_mode' => 'combined_dozen', 'product_id' => null]), 'order_items_fixed_dozen_snapshot_valid');
        $this->assertRejected(fn () => DB::table('order_items')->insert([...$dozen, 'cooked_unit_price' => null, 'cooked_line_total' => null, 'raw_discount' => null]), 'order_items_fixed_dozen_snapshot_valid');
    }

    public function test_v2_mixed_dozen_is_one_whole_peso_line_of_twelve_units_without_product(): void
    {
        $order = $this->order();
        $combined = $this->v2CombinedItem($order);
        DB::table('order_items')->insert($combined);
        self::assertSame(1, DB::table('order_items')->where('order_id', $order->id)->where('pricing_mode', 'combined_dozen')->count());

        $this->assertRejected(fn () => DB::table('order_items')->insert([...$combined, 'units_count' => 11]), 'order_items_fixed_dozen_snapshot_valid');
        $this->assertRejected(fn () => DB::table('order_items')->insert([...$combined, 'quantity' => 2, 'units_count' => 24, 'line_total' => 42240, 'regular_price_total' => 52800, 'savings' => 10560, 'cooked_line_total' => 42240, 'applied_exact_numerator_12' => 42240 * 12, 'cooked_exact_numerator_12' => 42240 * 12]), 'order_items_fixed_dozen_snapshot_valid');
        $this->assertRejected(fn () => DB::table('order_items')->insert([...$combined, 'applied_exact_numerator_12' => 21120 * 12 + 1, 'cooked_exact_numerator_12' => 21120 * 12 + 1]), 'order_items_fixed_dozen_snapshot_valid');
        $this->assertRejected(fn () => DB::table('order_items')->insert([...$combined, 'surcharge_exact_numerator_12' => 12]), 'order_items_fixed_dozen_snapshot_valid');
        $this->assertRejected(fn () => DB::table('order_items')->insert([...$combined, 'cooked_unit_price' => null, 'cooked_line_total' => null, 'raw_discount' => null]), 'order_items_fixed_dozen_snapshot_valid');
        $this->assertRejected(fn () => DB::table('order_items')->insert([...$combined, 'product_id' => $this->productId]), 'order_items_fixed_dozen_snapshot_valid');
    }

    public function test_combined_dozen_rollback_restores_the_narrow_check_and_refuses_with_combined_history(): void
    {
        $order = $this->order();
        $migration = require database_path(self::COMBINED_MIGRATION);
        $migration->down();
        $this->assertRejected(fn () => DB::table('order_items')->insert($this->v2CombinedItem($order)), 'order_items_fixed_dozen_snapshot_valid');
        DB::table('order_items')->insert($this->v2Item($order, [
            'pricing_mode' => 'unit', 'quantity' => 1, 'units_count' => 1, 'unit_price' => 1234, 'line_total' => 1234,
            'cooked_unit_price' => 1234, 'cooked_line_total' => 1234, 'raw_discount' => 0, 'regular_price_total' => 1234, 'savings' => 0,
            'applied_exact_numerator_12' => 1234 * 12, 'cooked_exact_numerator_12' => 1234 * 12,
            'raw_discount_exact_numerator_12' => 0, 'surcharge_exact_numerator_12' => 0,
        ]));

        // Version-2 unit and dozen history never blocks the widening or its rollback.
        $migration->up();
        $migration->down();
        $migration->up();
        DB::table('order_items')->insert($this->v2CombinedItem($order));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('combined dozen');
        $migration->down();
    }

    public function test_rollback_restores_legacy_checks_and_refuses_with_v2_history(): void
    {
        $order = $this->order();
        $migration = require database_path(self::MIGRATION);
        $migration->down();
        self::assertSame(0, DB::table('pg_constraint')->where('conname', 'orders_fixed_dozen_products_valid')->count());
        self::assertSame(0, DB::table('pg_constraint')->where('conname', 'order_items_fixed_dozen_snapshot_valid')->count());
        $this->assertRejected(fn () => DB::table('orders')->where('id', $order->id)->update($this->v2Totals(3702, 3700)), 'orders_total_matches_breakdown');

        $migration->up();
        self::assertSame(1, DB::table('pg_constraint')->where('conname', 'orders_fixed_dozen_products_valid')->count());
        self::assertSame(3702, (int) DB::table('orders')->where('id', $order->id)->value('total'));

        DB::table('orders')->where('id', $order->id)->update($this->v2Totals(3702, 3700));
        $this->expectException(RuntimeException::class);
        $migration->down();
    }

    public function test_activation_refuses_to_reinterpret_pre_existing_version_2_rows(): void
    {
        $order = $this->order();
        $migration = require database_path(self::MIGRATION);
        $migration->down();
        DB::table('orders')->where('id', $order->id)->update([...$this->v2Totals(3702, 3702), 'rounding_adjustment_numerator_1200' => 0]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('version-2');
        $migration->up();
    }

    private function order(): Order
    {
        $admin = User::factory()->administrator()->create();
        $category = Category::query()->create(['name' => 'Schema fixture', 'slug' => 'schema-fixture']);
        $product = Product::query()->create(['category_id' => $category->id, 'name' => 'Schema fixture', 'slug' => 'schema-fixture', 'pricing_family' => 'unit', 'unit_price' => 1234, 'half_dozen_price' => 1234, 'dozen_price' => 1234, 'is_available' => true]);
        $this->productId = $product->id;

        // Fixtures stand for orders written before fixed dozen pricing (version 1).
        return $this->asLegacyVersion1(app(CreateOrder::class)->execute(new OrderCheckoutData(items: [['product_id' => $product->id, 'quantity' => 3]], addons: [], condition: OrderCondition::Cooked, deliveryType: DeliveryType::DineIn, deliveryPeriod: null, deliveryDate: null, deliveryTime: null, deliveryAddress: null, notes: null, customerId: null, customerName: null, customerPhone: null, customerPhoneNormalized: null, customerAddress: null, paymentStatus: PaymentStatus::Unpaid), OrderSource::Pos, $admin->id));
    }

    /** @return array<string, int> */
    private function v2Totals(int $products, int $total, int $discountExact = 0, ?int $productExact12 = null): array
    {
        $productExact12 ??= $products * 12;
        $net = $productExact12 * 100 - $discountExact;

        return [
            'pricing_snapshot_version' => 2, 'items_subtotal' => $products, 'items_cooked_subtotal' => $products, 'raw_discount' => 0,
            'product_exact_numerator_12' => $productExact12, 'cooked_product_exact_numerator_12' => $productExact12,
            'raw_discount_exact_numerator_12' => 0, 'discount_exact_numerator_1200' => $discountExact,
            'net_pre_round_exact_numerator_1200' => $net, 'rounding_adjustment_numerator_1200' => $total * 1200 - $net, 'total' => $total,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function v2Item(Order $order, array $overrides): array
    {
        $base = (array) DB::table('order_items')->where('order_id', $order->id)->first();
        unset($base['id']);

        return [...$base, 'product_id' => $this->productId, 'pricing_snapshot_version' => 2, ...$overrides];
    }

    /**
     * 6 dulce (U 2100) + 6 JyQ (U 2300) cooked at a 20% combined discount: regular 26400, line 21120.
     *
     * @return array<string, mixed>
     */
    private function v2CombinedItem(Order $order): array
    {
        return $this->v2Item($order, [
            'product_id' => null, 'product_name' => 'Docena combinada', 'pricing_mode' => 'combined_dozen', 'quantity' => 1, 'units_count' => 12,
            'unit_price' => 21120, 'line_total' => 21120, 'cooked_unit_price' => 21120, 'cooked_line_total' => 21120,
            'raw_discount' => 0, 'regular_price_total' => 26400, 'savings' => 5280,
            'applied_exact_numerator_12' => 21120 * 12, 'cooked_exact_numerator_12' => 21120 * 12,
            'raw_discount_exact_numerator_12' => 0, 'surcharge_exact_numerator_12' => 0,
        ]);
    }

    private function assertRejected(callable $write, string $constraint): void
    {
        try {
            DB::transaction($write);
            self::fail("The database accepted a snapshot that {$constraint} must reject.");
        } catch (QueryException $exception) {
            self::assertSame('23514', $exception->errorInfo[0], $exception->getMessage());
            self::assertStringContainsString("\"{$constraint}\"", $exception->getMessage());
        }
    }
}
