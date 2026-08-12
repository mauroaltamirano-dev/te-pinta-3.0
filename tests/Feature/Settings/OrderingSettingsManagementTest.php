<?php

namespace Tests\Feature\Settings;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\OrderSettingKey;
use App\Modules\Orders\Models\OrderAddon;
use App\Modules\Orders\Models\OrderSetting;
use App\Modules\Products\Enums\PriceTier;
use App\Modules\Products\Models\CookingSurcharge;
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

        CookingSurcharge::query()->insert([
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
                ->where('orderingSettings.combined_dozen_raw_price', 16000)
                ->where('orderingSettings.combined_dozen_cooked_price', 17500)
                ->where('orderingSettings.delivery_fee', 0)
                ->where('orderingSettings.whatsapp_order_phone', '')
                ->where('orderingSettings.cooking_surcharges.unit', 200)
                ->where('orderingSettings.cooking_surcharges.half_dozen', 1000)
                ->where('orderingSettings.cooking_surcharges.dozen', 2000)
                ->has('orderingSettings.addons', 2)
                ->where('orderingSettings.addons.0.name', 'Lactonesa')
                ->where('orderingSettings.addons.0.price', 500)
                ->where('orderingSettings.addons.1.name', 'Yasgua')
                ->where('orderingSettings.addons.1.price', 200));
    }

    public function test_admin_updates_all_ordering_configuration_atomically(): void
    {
        $admin = User::factory()->administrator()->create();
        $addons = OrderAddon::query()->orderBy('sort_order')->get();

        $this->actingAs($admin)
            ->put(route('admin.settings.general.update'), [
                'combined_dozen_raw_price' => 17000,
                'combined_dozen_cooked_price' => 19000,
                'delivery_fee' => 2500,
                'whatsapp_order_phone' => '+54 9 387 555-0101',
                'cooking_surcharges' => [
                    'unit' => 250,
                    'half_dozen' => 1200,
                    'dozen' => 2200,
                ],
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

        $this->assertSame(17000, OrderSetting::integer(OrderSettingKey::CombinedDozenRawPrice));
        $this->assertSame(19000, OrderSetting::integer(OrderSettingKey::CombinedDozenCookedPrice));
        $this->assertSame(2500, OrderSetting::integer(OrderSettingKey::DeliveryFee));
        $this->assertSame('+54 9 387 555-0101', OrderSetting::string(OrderSettingKey::WhatsappOrderPhone));
        $this->assertDatabaseHas('cooking_surcharges', ['pricing_mode' => PriceTier::Unit->value, 'amount' => 250]);
        $this->assertDatabaseHas('cooking_surcharges', ['pricing_mode' => PriceTier::HalfDozen->value, 'amount' => 1200]);
        $this->assertDatabaseHas('cooking_surcharges', ['pricing_mode' => PriceTier::Dozen->value, 'amount' => 2200]);
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

    public function test_invalid_configuration_is_rejected_without_partial_writes(): void
    {
        $admin = User::factory()->administrator()->create();
        $payload = $this->validPayload();
        $payload['combined_dozen_raw_price'] = 99999;
        $payload['delivery_fee'] = -1;
        $payload['cooking_surcharges']['unexpected'] = 1;
        $payload['addons'][0]['id'] = 999999;

        $this->actingAs($admin)
            ->from(route('admin.settings.general.index'))
            ->put(route('admin.settings.general.update'), $payload)
            ->assertRedirect(route('admin.settings.general.index'))
            ->assertSessionHasErrors([
                'delivery_fee',
                'cooking_surcharges',
                'addons.0.id',
            ]);

        $this->assertSame(16000, OrderSetting::integer(OrderSettingKey::CombinedDozenRawPrice));
        $this->assertSame(200, CookingSurcharge::query()->where('pricing_mode', PriceTier::Unit)->value('amount'));
    }

    /** @return array<string, mixed> */
    private function validPayload(): array
    {
        return [
            'combined_dozen_raw_price' => 16000,
            'combined_dozen_cooked_price' => 17500,
            'delivery_fee' => 0,
            'whatsapp_order_phone' => '',
            'cooking_surcharges' => [
                'unit' => 200,
                'half_dozen' => 1000,
                'dozen' => 2000,
            ],
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
