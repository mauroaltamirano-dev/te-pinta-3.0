<?php

namespace Tests\Feature\Settings;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\OrderSettingKey;
use App\Modules\Orders\Models\OrderAddon;
use App\Modules\Orders\Models\OrderSetting;
use App\Modules\Products\Enums\PriceTier;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\RawPriceDiscount;
use App\Modules\Products\Pricing\EmpanadaUnitPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class OrderingSettingsManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        RawPriceDiscount::query()->insert([
            ['pricing_mode' => PriceTier::Unit->value, 'amount' => 200],
            ['pricing_mode' => PriceTier::HalfDozen->value, 'amount' => 1000],
            ['pricing_mode' => PriceTier::Dozen->value, 'amount' => 2000],
        ]);
    }

    public function test_guest_cannot_view_or_update_ordering_settings(): void
    {
        $this->get(route('admin.settings.general.index'))->assertRedirect(route('login'));
        $this->put(route('admin.settings.general.update'), $this->validPayload())->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_view_or_update_ordering_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.settings.general.index'))->assertForbidden();
        $this->actingAs($user)->put(route('admin.settings.general.update'), $this->validPayload())->assertForbidden();
    }

    public function test_legacy_settings_entry_redirects_to_product_management(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertRedirect(route('admin.products.index'));
    }

    public function test_admin_receives_typed_ordering_configuration_props(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)
            ->get(route('admin.settings.general.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('admin/settings/general', false)
                ->where('orderingSettings.empanada_unit_surcharge_percent', 10)
                ->where('orderingSettings.delivery_fee', 0)
                ->where('orderingSettings.whatsapp_order_phone', '')
                ->where('orderingSettings.raw_price_discounts.dozen', 2000)
                ->missing('orderingSettings.raw_price_discounts.unit')
                ->missing('orderingSettings.raw_price_discounts.half_dozen')
                ->missing('orderingSettings.combined_dozen_raw_price')
                ->missing('orderingSettings.combined_dozen_cooked_price')
                ->missing('orderingSettings.combined_half_dozen_surcharge')
                ->has('orderingSettings.addons', 2)
                ->where('orderingSettings.addons.0.name', 'Lactonesa')
                ->where('orderingSettings.addons.0.price', 500)
                ->where('orderingSettings.addons.1.name', 'Yasgua')
                ->where('orderingSettings.addons.1.price', 200));
    }

    public function test_missing_dozen_raw_discount_is_shown_empty_and_validated_normally(): void
    {
        RawPriceDiscount::query()->where('pricing_mode', PriceTier::Dozen)->delete();
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)
            ->get(route('admin.settings.general.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('admin/settings/general', false)
                ->where('orderingSettings.raw_price_discounts.dozen', null));

        $payload = $this->validPayload();
        $payload['raw_price_discounts']['dozen'] = null;
        $payload['delivery_fee'] = 900;

        $this->actingAs($admin)
            ->from(route('admin.settings.general.index'))
            ->put(route('admin.settings.general.update'), $payload)
            ->assertRedirect(route('admin.settings.general.index'))
            ->assertSessionHasErrors('raw_price_discounts.dozen');

        $this->assertSame(0, OrderSetting::integer(OrderSettingKey::DeliveryFee));
        $this->assertDatabaseMissing('raw_price_discounts', ['pricing_mode' => PriceTier::Dozen->value]);

        $payload['raw_price_discounts']['dozen'] = 2000;

        $this->actingAs($admin)
            ->put(route('admin.settings.general.update'), $payload)
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('raw_price_discounts', ['pricing_mode' => PriceTier::Dozen->value, 'amount' => 2000]);
        $this->assertSame(900, OrderSetting::integer(OrderSettingKey::DeliveryFee));
    }

    public function test_admin_updates_all_ordering_configuration_atomically(): void
    {
        $admin = User::factory()->administrator()->create();
        $addons = OrderAddon::query()->orderBy('sort_order')->get();

        $this->actingAs($admin)
            ->put(route('admin.settings.general.update'), [
                'empanada_unit_surcharge_percent' => 10,
                'delivery_fee' => 2500,
                'whatsapp_order_phone' => '+54 9 387 555-0101',
                'raw_price_discounts' => ['dozen' => 2200],
                'addons' => [
                    [
                        'id' => $addons[0]->id,
                        'name' => 'Lactonesa grande',
                        'price' => 650,
                        'is_available' => true,
                        'sort_order' => 2,
                    ],
                    [
                        'id' => $addons[1]->id,
                        'name' => 'Yasgua',
                        'price' => 300,
                        'is_available' => false,
                        'sort_order' => 1,
                    ],
                ],
            ])
            ->assertRedirect(route('admin.settings.general.index'))
            ->assertSessionHas('success');

        $this->assertSame(10, OrderSetting::integer(OrderSettingKey::EmpanadaUnitSurchargePercent));
        $this->assertSame(2500, OrderSetting::integer(OrderSettingKey::DeliveryFee));
        $this->assertSame('+54 9 387 555-0101', OrderSetting::string(OrderSettingKey::WhatsappOrderPhone));
        $this->assertDatabaseHas('raw_price_discounts', ['pricing_mode' => PriceTier::Dozen->value, 'amount' => 2200]);
        // Retired rows are kept untouched as history.
        $this->assertDatabaseHas('raw_price_discounts', ['pricing_mode' => PriceTier::Unit->value, 'amount' => 200]);
        $this->assertDatabaseHas('raw_price_discounts', ['pricing_mode' => PriceTier::HalfDozen->value, 'amount' => 1000]);
        $this->assertDatabaseHas('order_settings', ['key' => 'combined_dozen_raw_price', 'integer_value' => 16000]);
        $this->assertDatabaseHas('order_settings', ['key' => 'combined_dozen_cooked_price', 'integer_value' => 17500]);
        $this->assertDatabaseHas('order_addons', [
            'id' => $addons[0]->id,
            'name' => 'Lactonesa grande',
            'price' => 650,
            'is_available' => true,
            'sort_order' => 2,
        ]);
        $this->assertDatabaseHas('order_addons', [
            'id' => $addons[1]->id,
            'price' => 300,
            'is_available' => false,
            'sort_order' => 1,
        ]);
    }

    public function test_percentage_change_recalculates_every_stored_empanada_unit_price(): void
    {
        $dulce = $this->empanada('Dulce', 20000);
        $deleted = $this->empanada('Retirada', 22000);
        $deleted->delete();
        $soda = $this->unitProduct('Soda', 1550);
        $payload = $this->validPayload();
        $payload['empanada_unit_surcharge_percent'] = 20;

        $this->actingAs(User::factory()->administrator()->create())
            ->put(route('admin.settings.general.update'), $payload)
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(20, OrderSetting::integer(OrderSettingKey::EmpanadaUnitSurchargePercent));
        // U = D / 12 × 1.20, rounded half-up to ARS 100.
        $this->assertSame(2000, $dulce->fresh()->unit_price);
        $this->assertSame(2200, Product::withTrashed()->findOrFail($deleted->id)->unit_price);
        $this->assertSame(1550, $soda->fresh()->unit_price);
        $this->assertSame(20000, $dulce->fresh()->dozen_price);
    }

    public function test_unchanged_percentage_does_not_recalculate_unit_prices(): void
    {
        $dulce = $this->empanada('Dulce', 20000);
        Product::query()->whereKey($dulce->id)->update(['unit_price' => 1900]);

        $this->actingAs(User::factory()->administrator()->create())
            ->put(route('admin.settings.general.update'), $this->validPayload())
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(1900, $dulce->fresh()->unit_price);
    }

    public function test_percentage_must_be_an_integer_between_zero_and_one_hundred(): void
    {
        $dulce = $this->empanada('Dulce', 20000);
        $admin = User::factory()->administrator()->create();

        foreach ([-1, 101, 'diez', 10.5, null, ''] as $invalid) {
            $payload = $this->validPayload();
            $payload['empanada_unit_surcharge_percent'] = $invalid;

            $this->actingAs($admin)
                ->put(route('admin.settings.general.update'), $payload)
                ->assertSessionHasErrors('empanada_unit_surcharge_percent');
        }

        $this->assertSame(10, OrderSetting::integer(OrderSettingKey::EmpanadaUnitSurchargePercent));
        $this->assertSame(1800, $dulce->fresh()->unit_price);
    }

    public function test_retired_prices_and_discounts_are_rejected_explicitly(): void
    {
        $payload = $this->validPayload();
        $payload['combined_dozen_raw_price'] = 16000;
        $payload['combined_dozen_cooked_price'] = 17500;
        $payload['raw_price_discounts']['unit'] = 250;
        $payload['raw_price_discounts']['half_dozen'] = 1200;
        $payload['delivery_fee'] = 900;

        $this->actingAs(User::factory()->administrator()->create())
            ->put(route('admin.settings.general.update'), $payload)
            ->assertSessionHasErrors([
                'combined_dozen_raw_price' => 'El precio fijo de docena combinada ya no se usa; recargá la página.',
                'combined_dozen_cooked_price' => 'El precio fijo de docena combinada ya no se usa; recargá la página.',
                'raw_price_discounts.unit' => 'Solo se usa el descuento crudo de docena; recargá la página.',
                'raw_price_discounts.half_dozen' => 'Solo se usa el descuento crudo de docena; recargá la página.',
            ]);

        $this->assertSame(0, OrderSetting::integer(OrderSettingKey::DeliveryFee));
        $this->assertSame(200, RawPriceDiscount::query()->where('pricing_mode', PriceTier::Unit)->value('amount'));
    }

    public function test_every_stored_setting_row_still_hydrates_after_the_retirement(): void
    {
        $keys = OrderSetting::query()->orderBy('key')->get()->map(fn (OrderSetting $setting): string => $setting->key->value);

        $this->assertContains('combined_dozen_raw_price', $keys);
        $this->assertContains('combined_dozen_cooked_price', $keys);
    }

    public function test_invalid_configuration_is_rejected_without_partial_writes(): void
    {
        $dulce = $this->empanada('Dulce', 20000);
        $admin = User::factory()->administrator()->create();
        $payload = $this->validPayload();
        $payload['empanada_unit_surcharge_percent'] = 20;
        $payload['delivery_fee'] = -1;
        $payload['raw_price_discounts']['unexpected'] = 1;
        $payload['addons'][0]['id'] = 999999;

        $this->actingAs($admin)
            ->from(route('admin.settings.general.index'))
            ->put(route('admin.settings.general.update'), $payload)
            ->assertRedirect(route('admin.settings.general.index'))
            ->assertSessionHasErrors([
                'delivery_fee',
                'raw_price_discounts',
                'addons.0.id',
            ]);

        $this->assertSame(10, OrderSetting::integer(OrderSettingKey::EmpanadaUnitSurchargePercent));
        $this->assertSame(1800, $dulce->fresh()->unit_price);
        $this->assertSame(2000, RawPriceDiscount::query()->where('pricing_mode', PriceTier::Dozen)->value('amount'));
    }

    public function test_dozen_raw_discount_cannot_exceed_the_cheapest_empanada_dozen(): void
    {
        $this->empanada('Salteña', 18000);
        $payload = $this->validPayload();
        $payload['raw_price_discounts']['dozen'] = 18001;

        $this->actingAs(User::factory()->administrator()->create())
            ->put(route('admin.settings.general.update'), $payload)
            ->assertSessionHasErrors('raw_price_discounts.dozen');

        $this->assertSame(2000, RawPriceDiscount::query()->where('pricing_mode', PriceTier::Dozen)->value('amount'));
    }

    public function test_dozen_raw_discount_ignores_cheap_unit_products(): void
    {
        $this->unitProduct('Soda', 100);
        $payload = $this->validPayload();
        $payload['raw_price_discounts']['dozen'] = 2200;

        $this->actingAs(User::factory()->administrator()->create())
            ->put(route('admin.settings.general.update'), $payload)
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(2200, RawPriceDiscount::query()->where('pricing_mode', PriceTier::Dozen)->value('amount'));
    }

    private function empanada(string $name, int $dozenPrice): Product
    {
        return Product::query()->create([
            'category_id' => $this->category()->id,
            'pricing_family' => 'empanada',
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'unit_price' => EmpanadaUnitPrice::fromDozen($dozenPrice, 10),
            'half_dozen_price' => intdiv($dozenPrice, 2) + 500,
            'dozen_price' => $dozenPrice,
            'is_available' => true,
            'sort_order' => 1,
        ]);
    }

    private function unitProduct(string $name, int $unitPrice): Product
    {
        return Product::query()->create([
            'category_id' => $this->category()->id,
            'pricing_family' => 'unit',
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'unit_price' => $unitPrice,
            'half_dozen_price' => $unitPrice,
            'dozen_price' => $unitPrice,
            'is_available' => true,
            'sort_order' => 1,
        ]);
    }

    private function category(): Category
    {
        return Category::query()->firstOrCreate(
            ['slug' => 'empanadas'],
            ['name' => 'Empanadas', 'sort_order' => 1, 'is_active' => true],
        );
    }

    /** @return array<string, mixed> */
    private function validPayload(): array
    {
        return [
            'empanada_unit_surcharge_percent' => 10,
            'delivery_fee' => 0,
            'whatsapp_order_phone' => '',
            'raw_price_discounts' => ['dozen' => 2000],
            'addons' => OrderAddon::query()->orderBy('sort_order')->get()->map(
                fn (OrderAddon $addon): array => [
                    'id' => $addon->id,
                    'name' => $addon->name,
                    'price' => $addon->price,
                    'is_available' => $addon->is_available,
                    'sort_order' => $addon->sort_order,
                ],
            )->all(),
        ];
    }
}
