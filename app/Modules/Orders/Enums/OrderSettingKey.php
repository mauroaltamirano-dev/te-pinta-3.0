<?php

namespace App\Modules\Orders\Enums;

enum OrderSettingKey: string
{
    // combined_dozen_raw_price / combined_dozen_cooked_price rows are retired history, never read.
    case CombinedHalfDozenSurcharge = 'combined_half_dozen_surcharge';
    case DeliveryFee = 'delivery_fee';
    case EmpanadaUnitSurchargePercent = 'empanada_unit_surcharge_percent';
    case WhatsappOrderPhone = 'whatsapp_order_phone';
}
