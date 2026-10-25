<?php

namespace App\Modules\Settings\Http\Controllers;

use App\Core\Branding\RestaurantBranding;
use App\Core\Branding\RestaurantIdentity;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Orders\Enums\OrderSettingKey;
use App\Modules\Orders\Models\OrderAddon;
use App\Modules\Orders\Models\OrderSetting;
use App\Modules\Orders\Models\PublicOrderingHour;
use App\Modules\Settings\Actions\UpdateOrderingSettings;
use App\Modules\Settings\Data\OrderingSettingsData;
use App\Modules\Settings\Http\Requests\UpdateOrderingSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class OrderingSettingsController
{
    public function index(Request $request, RestaurantBranding $branding, RestaurantIdentity $identity): Response
    {
        abort_unless($request->user()?->can(Permission::CatalogManage->value) === true, 403);

        return Inertia::render('admin/settings/general', [
            'branding' => ['accent_color' => $branding->palette()->accent],
            'identity' => $identity->current(),
            'orderingSettings' => [
                'empanada_unit_surcharge_percent' => OrderSetting::integer(OrderSettingKey::EmpanadaUnitSurchargePercent),
                'combined_dozen_discount_percent' => OrderSetting::integer(OrderSettingKey::CombinedDozenDiscountPercent),
                'delivery_fee' => OrderSetting::integer(OrderSettingKey::DeliveryFee),
                'whatsapp_order_phone' => OrderSetting::string(OrderSettingKey::WhatsappOrderPhone),
                'raw_discount_percent' => OrderSetting::integer(OrderSettingKey::RawDiscountPercent),
                'addons' => OrderAddon::query()
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get(['id', 'name', 'price', 'is_available', 'sort_order']),
            ],
            'orderingHours' => $this->orderingHours(),
        ]);
    }

    public function update(
        UpdateOrderingSettingsRequest $request,
        UpdateOrderingSettings $updateOrderingSettings,
    ): RedirectResponse {
        $updateOrderingSettings->execute(OrderingSettingsData::fromRequest($request));

        return to_route('admin.settings.general.index')->with('success', 'Configuración de pedidos actualizada.');
    }

    /**
     * Every weekday, a missing row shown as closed so saving the form restores it.
     *
     * @return list<array{weekday: int, opens_at: ?string, closes_at: ?string}>
     */
    private function orderingHours(): array
    {
        $hours = PublicOrderingHour::query()->get()->keyBy('weekday');

        return array_map(fn (int $weekday): array => [
            'weekday' => $weekday,
            'opens_at' => $hours->get($weekday)?->opens_at,
            'closes_at' => $hours->get($weekday)?->closes_at,
        ], range(1, 7));
    }
}
