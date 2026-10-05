<?php

namespace Tests\Support;

use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Management\Models\Wallet;
use App\Modules\Marketing\Models\FeaturedProduct;
use App\Modules\Marketing\Support\MarketingClock;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Orders\Support\OrderBusinessDate;
use App\Modules\Products\Models\Product;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Seeders\InstagramStoryAutomationSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class PlaywrightDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->administrator()->create([
            'name' => 'Playwright Administrator',
            'email' => 'playwright-admin@example.test',
            'password' => Hash::make('playwright-password'),
        ]);

        $this->call(ProductCatalogSeeder::class);
        $unitFixtureCategoryId = Product::query()->where('slug', 'saltena')->value('category_id');
        Product::query()->firstOrCreate(
            ['slug' => 'playwright-unit-beverage'],
            [
                'category_id' => $unitFixtureCategoryId,
                'pricing_family' => 'unit',
                'name' => 'Bebida individual',
                'unit_price' => 2_500,
                'half_dozen_price' => 2_500,
                'dozen_price' => 2_500,
                'is_available' => true,
                'sort_order' => 999,
            ],
        );
        $this->call(InstagramStoryAutomationSeeder::class);
        Wallet::query()->create([
            'name' => 'Caja Playwright',
            'is_order_payment_default' => true,
        ]);
        $this->seedMarketingFeaturedProducts();

        $habitualCustomer = Customer::query()->create([
            'name' => 'Cliente habitual',
            'phone' => '11 5555-0101',
            'phone_normalized' => '1155550101',
            'address' => 'Av. Siempre Viva 123',
        ]);

        foreach (range(1, 30) as $number) {
            $suffix = str_pad((string) $number, 2, '0', STR_PAD_LEFT);
            Customer::query()->create([
                'name' => "Cliente paginado {$suffix}",
                'phone' => "11 4400-00{$suffix}",
                'phone_normalized' => "11440000{$suffix}",
                'address' => null,
            ]);
        }

        $dashboardCustomer = Customer::query()->create([
            'name' => 'Cliente Dashboard',
            'phone' => '11 5555-0202',
            'phone_normalized' => '1155550202',
            'address' => 'Calle Operativa 202',
        ]);

        $this->seedDashboardOrders($dashboardCustomer, $habitualCustomer);

        DB::table('order_settings')->where('key', 'whatsapp_order_phone')->update(['string_value' => '5491100000000']);
    }

    private function seedMarketingFeaturedProducts(): void
    {
        $now = app(MarketingClock::class)->now();
        $products = Product::query()
            ->whereIn('slug', ['saltena', 'caprese'])
            ->get()
            ->keyBy('slug');

        /** @var Product $saltena */
        $saltena = $products->get('saltena');
        /** @var Product $caprese */
        $caprese = $products->get('caprese');

        FeaturedProduct::query()->create([
            'product_id' => $saltena->id,
            'headline' => 'Nuestra recomendación de hoy',
            'short_copy' => 'Carne cortada a cuchillo y el sabor clásico del norte.',
            'starts_at' => $now->subDay(),
            'ends_at' => $now->addDay(),
            'is_enabled' => true,
        ]);

        FeaturedProduct::query()->create([
            'product_id' => $caprese->id,
            'headline' => 'Una opción fresca para mañana',
            'short_copy' => 'Tomate, albahaca y queso en una combinación simple.',
            'starts_at' => $now->addDays(2),
            'ends_at' => $now->addDays(3),
            'is_enabled' => true,
        ]);
    }

    private function seedDashboardOrders(Customer $dashboardCustomer, Customer $habitualCustomer): void
    {
        $today = app(OrderBusinessDate::class)->today();
        $weekStart = $today->startOfWeek(CarbonInterface::MONDAY)->startOfDay();
        $weekEnd = $today->endOfWeek(CarbonInterface::SUNDAY)->startOfDay();
        $weekDate = $today->isSameDay($weekStart)
            ? $today->addDay()->startOfDay()
            : $weekStart;
        $monthDate = $today->startOfMonth()->startOfDay();

        if ($monthDate->greaterThanOrEqualTo($weekStart) && $monthDate->lessThanOrEqualTo($weekEnd)) {
            $monthDate = $today->endOfMonth()->startOfDay();
        }

        $products = Product::query()
            ->whereIn('slug', [
                'saltena',
                'humita-tucumana',
                'criolla-salada',
                'caprese',
            ])
            ->get()
            ->keyBy('slug');

        /** @var Product $saltena */
        $saltena = $products->get('saltena');
        /** @var Product $humita */
        $humita = $products->get('humita-tucumana');
        /** @var Product $criolla */
        $criolla = $products->get('criolla-salada');
        /** @var Product $caprese */
        $caprese = $products->get('caprese');

        $pendingExact = $this->dashboardOrder(
            $dashboardCustomer,
            'TP-009001',
            $today,
            'pending_confirmation',
            'unpaid',
            'raw',
            'pickup',
            'midday',
            '11:00',
            16000,
        );
        $this->directItem($pendingExact, $saltena, 'dozen', 1, 12, 16000);

        $this->dashboardOrder(
            $habitualCustomer,
            'TP-009002',
            $today,
            'pending_confirmation',
            'unpaid',
            'raw',
            'delivery',
            'afternoon',
            null,
            15000,
        );
        $waitingExact = $this->dashboardOrder(
            $dashboardCustomer,
            'TP-009003',
            $today,
            'waiting',
            'unpaid',
            'cooked',
            'delivery',
            'midday',
            '13:30',
            18500,
        );
        $this->directItem($waitingExact, $saltena, 'dozen', 1, 12, 18500);

        $preparedBand = $this->dashboardOrder(
            $habitualCustomer,
            'TP-009004',
            $today,
            'prepared',
            'paid',
            'raw',
            'pickup',
            'night',
            null,
            9000,
        );
        $this->directItem($preparedBand, $humita, 'half_dozen', 1, 6, 9000);

        $weekPickup = $this->dashboardOrder(
            $dashboardCustomer,
            'TP-009005',
            $weekDate,
            'waiting',
            'paid',
            'raw',
            'pickup',
            'midday',
            null,
            16000,
        );
        $this->directItem($weekPickup, $saltena, 'dozen', 1, 12, 16000);

        $weekDelivery = $this->dashboardOrder(
            $habitualCustomer,
            'TP-009006',
            $weekDate,
            'delivered',
            'unpaid',
            'cooked',
            'delivery',
            'night',
            '20:00',
            17500,
        );
        $combined = $weekDelivery->items()->create([
            'product_id' => null,
            'product_name' => 'Docena combinada',
            'pricing_mode' => 'combined_dozen',
            'quantity' => 1,
            'units_count' => 12,
            'unit_price' => 17500,
            'line_total' => 17500,
            'regular_price_total' => 17500,
            'savings' => 0,
        ]);
        $combined->compositions()->createMany([
            [
                'product_id' => $humita->id,
                'product_name' => $humita->name,
                'quantity' => 5,
            ],
            [
                'product_id' => $criolla->id,
                'product_name' => $criolla->name,
                'quantity' => 7,
            ],
        ]);

        $monthDelivery = $this->dashboardOrder(
            $dashboardCustomer,
            'TP-009007',
            $monthDate,
            'delivered',
            'paid',
            'cooked',
            'delivery',
            'afternoon',
            null,
            8000,
        );
        $this->directItem($monthDelivery, $caprese, 'half_dozen', 1, 6, 8000);
    }

    private function dashboardOrder(
        Customer $customer,
        string $publicId,
        CarbonImmutable $deliveryDate,
        string $status,
        string $paymentStatus,
        string $condition,
        string $deliveryType,
        string $deliveryPeriod,
        ?string $deliveryTime,
        int $total,
    ): Order {
        return Order::query()->forceCreate([
            'public_id' => $publicId,
            'customer_id' => $customer->id,
            'source' => 'admin',
            'customer_name_snapshot' => $customer->name,
            'customer_phone_snapshot' => $customer->phone,
            'status' => $status,
            'payment_status' => $paymentStatus,
            'payment_method' => $paymentStatus === 'paid' ? 'cash' : null,
            'condition' => $condition,
            'delivery_type' => $deliveryType,
            'delivery_period' => $deliveryPeriod,
            'delivery_date' => $deliveryDate->toDateString(),
            'delivery_time' => $deliveryTime,
            'delivery_address_snapshot' => $deliveryType === 'delivery'
                ? ($customer->address ?? 'Calle Operativa 202')
                : null,
            'notes' => null,
            'items_base_subtotal' => null,
            'cooking_surcharge' => null,
            'items_subtotal' => $total,
            'toppings_subtotal' => 0,
            'shipping_fee_snapshot' => 0,
            'combined_dozen_savings' => 0,
            'discount_type' => null,
            'discount_value' => null,
            'discount_amount' => 0,
            'total' => $total,
        ]);
    }

    private function directItem(
        Order $order,
        Product $product,
        string $pricingMode,
        int $quantity,
        int $unitsCount,
        int $lineTotal,
    ): OrderItem {
        return $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'pricing_mode' => $pricingMode,
            'quantity' => $quantity,
            'units_count' => $unitsCount,
            'unit_price' => $lineTotal,
            'line_total' => $lineTotal,
            'regular_price_total' => $lineTotal,
            'savings' => 0,
        ]);
    }
}
