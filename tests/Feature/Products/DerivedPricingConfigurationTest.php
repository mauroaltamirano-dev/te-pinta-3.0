<?php

namespace Tests\Feature\Products;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\OrderSettingKey;
use App\Modules\Orders\Models\OrderAddon;
use App\Modules\Orders\Models\OrderSetting;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\RawPriceDiscount;
use App\Modules\Products\Pricing\HomogeneousHalfDozenPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DerivedPricingConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['unit' => 200, 'half_dozen' => 1000, 'dozen' => 2000] as $mode => $amount) {
            RawPriceDiscount::query()->create(['pricing_mode' => $mode, 'amount' => $amount]);
        }
    }

    public function test_surcharge_is_derived_without_repricing_and_can_be_edited_through_products(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $category = Category::query()->create(['name' => 'Fixture', 'slug' => 'fixture', 'is_active' => true]);
        $payload = ['name' => 'Fixture Matambre', 'category_id' => $category->id, 'pricing_family' => 'empanada', 'unit_price' => 2200, 'dozen_price' => 22000, 'homogeneous_half_dozen_surcharge' => '2000', 'is_available' => true, 'sort_order' => 1];
        $this->postJson(route('admin.products.store'), $payload)->assertRedirect();
        $product = Product::query()->sole();
        self::assertSame(13000, $product->half_dozen_price);
        self::assertSame('2000', HomogeneousHalfDozenPrice::surcharge($product->half_dozen_price, $product->dozen_price));
        $this->putJson(route('admin.products.update', $product), [...$payload, 'homogeneous_half_dozen_surcharge' => '1500'])->assertRedirect();
        self::assertSame(12500, $product->fresh()->half_dozen_price);
        $this->assertDatabaseCount('products', 1);
    }

    public function test_legacy_half_price_writes_still_work_without_a_second_source_of_truth(): void
    {
        self::assertSame('1000', HomogeneousHalfDozenPrice::surcharge(10500, 19000));
        self::assertSame('999.5', HomogeneousHalfDozenPrice::surcharge(10500, 19001));
        self::assertSame(10500, HomogeneousHalfDozenPrice::fromSurcharge(19001, '999.5'));
    }

    public function test_preview_is_authorized_exact_and_read_only(): void
    {
        $url = '/admin/settings/products/half-dozen-preview';
        $this->postJson($url, ['dozen_price' => 22000, 'homogeneous_half_dozen_surcharge' => '2000'])->assertUnauthorized();
        $this->actingAs(User::factory()->create())->postJson($url, ['dozen_price' => 22000, 'homogeneous_half_dozen_surcharge' => '2000'])->assertForbidden();
        $this->actingAs(User::factory()->administrator()->create())->postJson($url, ['dozen_price' => 22000, 'homogeneous_half_dozen_surcharge' => '2000'])->assertOk()->assertJsonPath('half_dozen_price', 13000);
        $this->postJson($url, ['dozen_price' => 22001, 'homogeneous_half_dozen_surcharge' => '2000'])->assertUnprocessable();
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('financial_transactions', 0);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_settings_updates_the_surcharge_and_legacy_clients_preserve_it(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $payload = ['combined_dozen_raw_price' => 16000, 'combined_dozen_cooked_price' => 17500, 'combined_half_dozen_surcharge' => 1200, 'delivery_fee' => 987, 'whatsapp_order_phone' => '', 'raw_price_discounts' => ['unit' => 200, 'half_dozen' => 1000, 'dozen' => 2000], 'addons' => []];
        $payload['addons'] = OrderAddon::query()->get(['id', 'name', 'price', 'is_available', 'sort_order'])->toArray();
        $this->putJson(route('admin.settings.general.update'), $payload)->assertRedirect();
        self::assertSame(1200, OrderSetting::integer(OrderSettingKey::CombinedHalfDozenSurcharge));
        $category = Category::query()->create(['name' => 'Guard fixture', 'slug' => 'guard-fixture', 'is_active' => true]);
        Product::query()->create(['category_id' => $category->id, 'name' => 'Guard Matambre', 'slug' => 'guard-matambre', 'pricing_family' => 'empanada', 'unit_price' => 2200, 'half_dozen_price' => 13000, 'dozen_price' => 22000, 'is_available' => true]);
        $this->putJson(route('admin.settings.general.update'), [...$payload, 'combined_half_dozen_surcharge' => 3000])->assertUnprocessable()->assertJsonValidationErrors('combined_half_dozen_surcharge');
        self::assertSame(1200, OrderSetting::integer(OrderSettingKey::CombinedHalfDozenSurcharge));
        $this->putJson(route('admin.settings.general.update'), [...$payload, 'combined_half_dozen_surcharge' => -1])->assertUnprocessable();
        unset($payload['combined_half_dozen_surcharge']);
        $this->putJson(route('admin.settings.general.update'), $payload)->assertRedirect();
        self::assertSame(1200, OrderSetting::integer(OrderSettingKey::CombinedHalfDozenSurcharge));
    }

    public function test_invalid_price_changes_are_rejected_without_blocking_legacy_metadata(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $category = Category::query()->create(['name' => 'Legacy', 'slug' => 'legacy']);
        $product = Product::query()->create(['category_id' => $category->id, 'name' => 'Legacy', 'slug' => 'legacy', 'pricing_family' => 'empanada', 'unit_price' => 2200, 'half_dozen_price' => 14000, 'dozen_price' => 27000, 'is_available' => false]);
        $payload = ['name' => 'Legacy renamed', 'category_id' => $category->id, 'pricing_family' => 'empanada', 'unit_price' => 2200, 'half_dozen_price' => 14000, 'dozen_price' => 27000, 'is_available' => false, 'sort_order' => 0];
        $this->putJson(route('admin.products.update', $product), $payload)->assertRedirect();
        $this->putJson(route('admin.products.update', $product), [...$payload, 'half_dozen_price' => 15000])->assertUnprocessable();
        self::assertSame(14000, $product->fresh()->half_dozen_price);
        unset($payload['half_dozen_price']);
        $this->putJson(route('admin.products.update', $product), [...$payload, 'pricing_family' => 'unit', 'homogeneous_half_dozen_surcharge' => '1000'])->assertUnprocessable();
    }

    public function test_combined_surcharge_is_persisted_configuration_not_an_algorithm_constant(): void
    {
        self::assertSame(1500, OrderSetting::integer(OrderSettingKey::CombinedHalfDozenSurcharge));
        OrderSetting::query()->where('key', OrderSettingKey::CombinedHalfDozenSurcharge->value)->update(['integer_value' => 1200]);
        $this->actingAs(User::factory()->administrator()->create())->getJson(route('admin.settings.general.index'))->assertOk();
        self::assertSame(1200, OrderSetting::integer(OrderSettingKey::CombinedHalfDozenSurcharge));
    }
}
