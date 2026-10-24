<?php

namespace App\Modules\Orders\Enums;

enum OrderSettingKey: string
{
    /** Retired: rows are kept as history and never read; the case stays so stored rows still hydrate. */
    case CombinedDozenRawPrice = 'combined_dozen_raw_price';
    /** Retired: rows are kept as history and never read; the case stays so stored rows still hydrate. */
    case CombinedDozenCookedPrice = 'combined_dozen_cooked_price';
    case CombinedDozenDiscountPercent = 'combined_dozen_discount_percent';
    case CombinedHalfDozenSurcharge = 'combined_half_dozen_surcharge';
    case DeliveryFee = 'delivery_fee';
    case EmpanadaUnitSurchargePercent = 'empanada_unit_surcharge_percent';
    case RawDiscountPercent = 'raw_discount_percent';
    case WhatsappOrderPhone = 'whatsapp_order_phone';
}
