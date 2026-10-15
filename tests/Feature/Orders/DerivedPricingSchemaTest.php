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
use Tests\TestCase;

final class DerivedPricingSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_additive_schema_preserves_v1_and_existing_catalog_prices(): void
    {
        $order = $this->order();
        $before = DB::table('orders')->where('id', $order->id)->first();
        $catalog = DB::table('products')->get()->toJson();
        $migration = require database_path('migrations/2026_10_15_000000_prepare_derived_pricing_foundation.php');
        $migration->down();
        $migration->up();
        $after = DB::table('orders')->where('id', $order->id)->first();
        self::assertEquals($before, $after);
        self::assertSame($catalog, DB::table('products')->get()->toJson());
        self::assertSame(1, $after->pricing_snapshot_version);
        self::assertNull($after->product_exact_numerator_12);
        self::assertSame(3702, $order->total);
        $this->assertDatabaseCount('order_inventory_consumptions', 0);
        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_reserved_v2_requires_complete_exact_shape_and_blocks_destructive_rollback(): void
    {
        $order = $this->order();
        try {
            DB::transaction(fn () => DB::table('orders')->where('id', $order->id)->update(['pricing_snapshot_version' => 2]));
            self::fail('Incomplete snapshot accepted.');
        } catch (QueryException $exception) {
            self::assertSame('23514', $exception->errorInfo[0]);
        }
        DB::table('orders')->where('id', $order->id)->update([
            'pricing_snapshot_version' => 2, 'product_exact_numerator_12' => 3702 * 12,
            'cooked_product_exact_numerator_12' => 3702 * 12, 'raw_discount_exact_numerator_12' => 0,
            'discount_exact_numerator_1200' => 0, 'net_pre_round_exact_numerator_1200' => 3702 * 1200,
            'rounding_adjustment_numerator_1200' => 0,
        ]);
        self::assertSame(3702, $order->fresh()->total);
        $this->expectException(RuntimeException::class);
        (require database_path('migrations/2026_10_15_000000_prepare_derived_pricing_foundation.php'))->down();
    }

    private function order(): Order
    {
        $admin = User::factory()->administrator()->create();
        $category = Category::query()->create(['name' => 'Schema fixture', 'slug' => 'schema-fixture']);
        $product = Product::query()->create(['category_id' => $category->id, 'name' => 'Schema fixture', 'slug' => 'schema-fixture', 'pricing_family' => 'unit', 'unit_price' => 1234, 'half_dozen_price' => 1234, 'dozen_price' => 1234, 'is_available' => true]);

        return app(CreateOrder::class)->execute(new OrderCheckoutData(items: [['product_id' => $product->id, 'quantity' => 3]], addons: [], condition: OrderCondition::Cooked, deliveryType: DeliveryType::DineIn, deliveryPeriod: null, deliveryDate: null, deliveryTime: null, deliveryAddress: null, notes: null, customerId: null, customerName: null, customerPhone: null, customerPhoneNormalized: null, customerAddress: null, paymentStatus: PaymentStatus::Unpaid), OrderSource::Pos, $admin->id);
    }
}
