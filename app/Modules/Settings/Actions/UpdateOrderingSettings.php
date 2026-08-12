<?php

namespace App\Modules\Settings\Actions;

use App\Modules\Orders\Enums\OrderSettingKey;
use App\Modules\Orders\Models\OrderAddon;
use App\Modules\Orders\Models\OrderSetting;
use App\Modules\Products\Enums\PriceTier;
use App\Modules\Products\Models\CookingSurcharge;
use App\Modules\Settings\Data\OrderingSettingsData;
use Illuminate\Support\Facades\DB;

final class UpdateOrderingSettings
{
    public function execute(OrderingSettingsData $data): void
    {
        DB::transaction(function () use ($data): void {
            $this->updateIntegerSetting(OrderSettingKey::CombinedDozenRawPrice, $data->combinedDozenRawPrice);
            $this->updateIntegerSetting(OrderSettingKey::CombinedDozenCookedPrice, $data->combinedDozenCookedPrice);
            $this->updateIntegerSetting(OrderSettingKey::DeliveryFee, $data->deliveryFee);

            OrderSetting::query()->updateOrCreate(
                ['key' => OrderSettingKey::WhatsappOrderPhone->value],
                ['integer_value' => null, 'string_value' => $data->whatsappOrderPhone],
            );

            foreach (PriceTier::cases() as $tier) {
                CookingSurcharge::query()->updateOrCreate(
                    ['pricing_mode' => $tier->value],
                    ['amount' => $data->cookingSurcharges[$tier->value]],
                );
            }

            foreach ($data->addons as $addonData) {
                OrderAddon::query()
                    ->whereKey($addonData['id'])
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->firstOrFail()
                    ->update([
                        'name' => $addonData['name'],
                        'price' => $addonData['price'],
                        'is_available' => $addonData['is_available'],
                        'sort_order' => $addonData['sort_order'],
                    ]);
            }
        });
    }

    private function updateIntegerSetting(OrderSettingKey $key, int $value): void
    {
        OrderSetting::query()->updateOrCreate(
            ['key' => $key->value],
            ['integer_value' => $value, 'string_value' => null],
        );
    }
}
