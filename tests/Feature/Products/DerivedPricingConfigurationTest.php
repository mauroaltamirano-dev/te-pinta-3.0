<?php

namespace Tests\Feature\Products;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\OrderSettingKey;
use App\Modules\Orders\Models\OrderAddon;
use App\Modules\Orders\Models\OrderSetting;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\RawPriceDiscount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
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

    public function test_products_no_longer_accept_a_homogeneous_surcharge(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $category = Category::query()->create(['name' => 'Fixture', 'slug' => 'fixture', 'is_active' => true]);
        $payload = ['name' => 'Fixture Matambre', 'category_id' => $category->id, 'pricing_family' => 'empanada', 'dozen_price' => 22000, 'homogeneous_half_dozen_surcharge' => '2000', 'is_available' => true, 'sort_order' => 1];
        // The empanada half dozen is six derived units, so the P0 surcharge input is retired.
        $this->postJson(route('admin.products.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('homogeneous_half_dozen_surcharge');
        $this->assertDatabaseCount('products', 0);
    }

    public function test_the_retired_half_dozen_preview_and_surcharge_field_are_gone(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        self::assertFalse(Route::has('admin.products.half-dozen-preview'));
        // The old path now only matches the product resource route, which accepts no POST.
        $this->postJson('/admin/settings/products/half-dozen-preview', ['dozen_price' => 22000, 'homogeneous_half_dozen_surcharge' => '2000'])->assertMethodNotAllowed();
        $category = Category::query()->create(['name' => 'Fixture', 'slug' => 'fixture', 'is_active' => true]);
        Product::query()->create(['category_id' => $category->id, 'name' => 'Fixture Matambre', 'slug' => 'fixture-matambre', 'pricing_family' => 'empanada', 'unit_price' => 2000, 'half_dozen_price' => 12000, 'dozen_price' => 22000, 'is_available' => true]);

        $this->get(route('admin.products.index'))->assertInertia(fn (Assert $page) => $page
            ->where('products.0.half_dozen_price', 12000)
            ->missing('products.0.homogeneous_half_dozen_surcharge'));
    }

    public function test_settings_updates_the_surcharge_and_legacy_clients_preserve_it(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $payload = ['empanada_unit_surcharge_percent' => 10, 'combined_dozen_discount_percent' => 20, 'combined_half_dozen_surcharge' => 1200, 'delivery_fee' => 987, 'whatsapp_order_phone' => '', 'raw_discount_percent' => 5, 'addons' => []];
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
        $payload = ['name' => 'Legacy renamed', 'category_id' => $category->id, 'pricing_family' => 'empanada', 'dozen_price' => 27000, 'is_available' => false, 'sort_order' => 0];
        // The half dozen is never entered; an edit stores six units at the kept unit price.
        $this->putJson(route('admin.products.update', $product), [...$payload, 'half_dozen_price' => 13000])->assertUnprocessable()->assertJsonValidationErrors('half_dozen_price');
        self::assertSame(14000, $product->fresh()->half_dozen_price);
        $this->putJson(route('admin.products.update', $product), $payload)->assertRedirect();
        self::assertSame([2200, 13200], [$product->fresh()->unit_price, $product->fresh()->half_dozen_price]);
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
