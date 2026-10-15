<?php

namespace App\Modules\Settings\Actions;

use App\Modules\Orders\Enums\OrderSettingKey;
use App\Modules\Orders\Models\OrderAddon;
use App\Modules\Orders\Models\OrderSetting;
use App\Modules\Products\Enums\PriceTier;
use App\Modules\Products\Enums\ProductPricingFamily;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\RawPriceDiscount;
use App\Modules\Settings\Data\OrderingSettingsData;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateOrderingSettings
{
    public function execute(OrderingSettingsData $data): void
    {
        $this->validateRawPrices($data);
        $this->validateCombinedSurcharge($data);

        DB::transaction(function () use ($data): void {
            if ($data->combinedHalfDozenSurcharge !== null) {
                $this->updateIntegerSetting(OrderSettingKey::CombinedHalfDozenSurcharge, $data->combinedHalfDozenSurcharge);
            }
            $this->updateIntegerSetting(OrderSettingKey::CombinedDozenRawPrice, $data->combinedDozenRawPrice);
            $this->updateIntegerSetting(OrderSettingKey::CombinedDozenCookedPrice, $data->combinedDozenCookedPrice);
            $this->updateIntegerSetting(OrderSettingKey::DeliveryFee, $data->deliveryFee);

            OrderSetting::query()->updateOrCreate(
                ['key' => OrderSettingKey::WhatsappOrderPhone->value],
                ['integer_value' => null, 'string_value' => $data->whatsappOrderPhone],
            );

            foreach (PriceTier::cases() as $tier) {
                RawPriceDiscount::query()->updateOrCreate(
                    ['pricing_mode' => $tier->value],
                    ['amount' => $data->rawPriceDiscounts[$tier->value]],
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

    private function validateRawPrices(OrderingSettingsData $data): void
    {
        if ($data->combinedDozenRawPrice >= $data->combinedDozenCookedPrice) {
            throw ValidationException::withMessages([
                'combined_dozen_raw_price' => 'El precio crudo debe ser menor que el precio cocinado.',
            ]);
        }

        $columns = [
            PriceTier::Unit->value => 'unit_price',
            PriceTier::HalfDozen->value => 'half_dozen_price',
            PriceTier::Dozen->value => 'dozen_price',
        ];

        foreach ($columns as $mode => $column) {
            $minimumCookedPrice = Product::query()->where('pricing_family', ProductPricingFamily::Empanada)->min($column);

            if ($minimumCookedPrice !== null && $data->rawPriceDiscounts[$mode] > (int) $minimumCookedPrice) {
                throw ValidationException::withMessages([
                    "raw_price_discounts.{$mode}" => 'El descuento crudo no puede superar el menor precio cocinado.',
                ]);
            }
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
