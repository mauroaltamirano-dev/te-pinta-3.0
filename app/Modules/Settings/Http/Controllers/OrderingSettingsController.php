<?php

namespace App\Modules\Settings\Http\Controllers;

use App\Core\Branding\RestaurantBranding;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Orders\Enums\OrderSettingKey;
use App\Modules\Orders\Models\OrderAddon;
use App\Modules\Orders\Models\OrderSetting;
use App\Modules\Products\Enums\PriceTier;
use App\Modules\Products\Models\RawPriceDiscount;
use App\Modules\Settings\Actions\UpdateOrderingSettings;
use App\Modules\Settings\Data\OrderingSettingsData;
use App\Modules\Settings\Http\Requests\UpdateOrderingSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

final class OrderingSettingsController
{
    public function index(Request $request, RestaurantBranding $branding): Response
    {
        abort_unless($request->user()?->can(Permission::CatalogManage->value) === true, 403);

        $dozenRawDiscount = RawPriceDiscount::query()->where('pricing_mode', PriceTier::Dozen->value)->value('amount');

        if ($dozenRawDiscount === null) {
            throw new LogicException('Falta el descuento de precio crudo dozen.');
        }

        return Inertia::render('admin/settings/general', [
            'branding' => ['accent_color' => $branding->palette()->accent],
            'orderingSettings' => [
                'empanada_unit_surcharge_percent' => OrderSetting::integer(OrderSettingKey::EmpanadaUnitSurchargePercent),
                'delivery_fee' => OrderSetting::integer(OrderSettingKey::DeliveryFee),
                'whatsapp_order_phone' => OrderSetting::string(OrderSettingKey::WhatsappOrderPhone),
                // Only the dozen discount prices raw orders; unit and half-dozen rows are retired history.
                'raw_price_discounts' => ['dozen' => (int) $dozenRawDiscount],
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

        return to_route('admin.settings.general.index')->with('success', 'Configuración de pedidos actualizada.');
    }
}
