<?php

namespace App\Modules\Settings\Actions;

use App\Modules\Orders\Enums\OrderSettingKey;
use App\Modules\Orders\Models\OrderAddon;
use App\Modules\Orders\Models\OrderSetting;
use App\Modules\Products\Actions\RecalculateEmpanadaUnitPrices;
use App\Modules\Products\Enums\PriceTier;
use App\Modules\Products\Enums\ProductPricingFamily;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\RawPriceDiscount;
use App\Modules\Settings\Data\OrderingSettingsData;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateOrderingSettings
{
    public function __construct(private readonly RecalculateEmpanadaUnitPrices $unitPrices) {}

    public function execute(OrderingSettingsData $data): void
    {
        $this->validateRawPrices($data);
        $this->validateCombinedSurcharge($data);

        DB::transaction(function () use ($data): void {
            if ($data->combinedHalfDozenSurcharge !== null) {
                $this->updateIntegerSetting(OrderSettingKey::CombinedHalfDozenSurcharge, $data->combinedHalfDozenSurcharge);
            }
            $this->updateSurchargePercent($data->empanadaUnitSurchargePercent);
            $this->updateIntegerSetting(OrderSettingKey::CombinedDozenDiscountPercent, $data->combinedDozenDiscountPercent);
            $this->updateIntegerSetting(OrderSettingKey::DeliveryFee, $data->deliveryFee);

            OrderSetting::query()->updateOrCreate(
                ['key' => OrderSettingKey::WhatsappOrderPhone->value],
                ['integer_value' => null, 'string_value' => $data->whatsappOrderPhone],
            );

            // Unit and half-dozen discount rows are retired history and stay untouched.
            RawPriceDiscount::query()->updateOrCreate(
                ['pricing_mode' => PriceTier::Dozen->value],
                ['amount' => $data->dozenRawDiscount],
            );

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

    private function validateRawPrices(OrderingSettingsData $data): void
    {
        $minimumDozenPrice = Product::query()->where('pricing_family', ProductPricingFamily::Empanada)->min('dozen_price');

        if ($minimumDozenPrice !== null && $data->dozenRawDiscount > (int) $minimumDozenPrice) {
            throw ValidationException::withMessages([
                'raw_price_discounts.dozen' => 'El descuento crudo no puede superar el menor precio cocinado.',
            ]);
        }
    }

    /**
     * Stored empanada unit prices follow the percentage. Updating the setting row first locks it, so a concurrent
     * product save (which reads the percentage with a shared lock) either finishes before the recalculation or
     * waits and derives its unit price from the new percentage.
     */
    private function updateSurchargePercent(int $percent): void
    {
        $current = OrderSetting::query()->where('key', OrderSettingKey::EmpanadaUnitSurchargePercent->value)
            ->lockForUpdate()->value('integer_value');
        $this->updateIntegerSetting(OrderSettingKey::EmpanadaUnitSurchargePercent, $percent);

        if ($current === null || (int) $current !== $percent) {
            $this->unitPrices->execute($percent);
        }
    }

    private function validateCombinedSurcharge(OrderingSettingsData $data): void
    {
        if ($data->combinedHalfDozenSurcharge === null
            || $data->combinedHalfDozenSurcharge === OrderSetting::integer(OrderSettingKey::CombinedHalfDozenSurcharge)) {
            return;
        }
        $conflicts = Product::query()->where('pricing_family', ProductPricingFamily::Empanada)
            ->where('is_available', true)->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->whereRaw('dozen_price::bigint + ? >= 12 * unit_price::bigint', [2 * $data->combinedHalfDozenSurcharge])
            ->orderBy('id')->pluck('name')->all();
        if ($conflicts !== []) {
            throw ValidationException::withMessages(['combined_half_dozen_surcharge' => 'El recargo no conserva ahorro por empanada cocinada en: '.implode(', ', $conflicts).'.']);
        }
    }

    private function updateIntegerSetting(OrderSettingKey $key, int $value): void
    {
        OrderSetting::query()->updateOrCreate(
            ['key' => $key->value],
            ['integer_value' => $value, 'string_value' => null],
        );
    }
}
