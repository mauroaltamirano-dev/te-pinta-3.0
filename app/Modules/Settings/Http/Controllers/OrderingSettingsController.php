<?php

namespace App\Modules\Settings\Http\Controllers;

use App\Modules\Orders\Enums\OrderSettingKey;
use App\Modules\Orders\Models\OrderAddon;
use App\Modules\Orders\Models\OrderSetting;
use App\Modules\Products\Enums\PriceTier;
use App\Modules\Products\Models\CookingSurcharge;
use App\Modules\Settings\Actions\UpdateOrderingSettings;
use App\Modules\Settings\Data\OrderingSettingsData;
use App\Modules\Settings\Http\Requests\UpdateOrderingSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

final class OrderingSettingsController
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->is_admin === true, 403);

        /** @var Collection<string, int> $surcharges */
        $surcharges = CookingSurcharge::query()->pluck('amount', 'pricing_mode');

        return Inertia::render('admin/settings/general', [
            'orderingSettings' => [
                'combined_dozen_raw_price' => OrderSetting::integer(OrderSettingKey::CombinedDozenRawPrice),
                'combined_dozen_cooked_price' => OrderSetting::integer(OrderSettingKey::CombinedDozenCookedPrice),
                'delivery_fee' => OrderSetting::integer(OrderSettingKey::DeliveryFee),
                'whatsapp_order_phone' => OrderSetting::string(OrderSettingKey::WhatsappOrderPhone),
                'cooking_surcharges' => collect(PriceTier::cases())
                    ->mapWithKeys(function (PriceTier $tier) use ($surcharges): array {
                        if (! $surcharges->has($tier->value)) {
                            throw new LogicException("Missing cooking surcharge {$tier->value}.");
                        }

                        return [$tier->value => (int) $surcharges->get($tier->value)];
                    })
                    ->all(),
                'addons' => OrderAddon::query()
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get(['id', 'name', 'price', 'is_available', 'sort_order']),
            ],
        ]);
    }

    public function update(
        UpdateOrderingSettingsRequest $request,
        UpdateOrderingSettings $updateOrderingSettings,
    ): RedirectResponse {
        $updateOrderingSettings->execute(OrderingSettingsData::fromRequest($request));

        return to_route('admin.settings.general.index')->with('success', 'Ordering settings updated.');
    }
}
