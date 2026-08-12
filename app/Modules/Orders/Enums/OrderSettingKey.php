<?php

namespace App\Modules\Orders\Enums;

enum OrderSettingKey: string
{
    case CombinedDozenRawPrice = 'combined_dozen_raw_price';
    case CombinedDozenCookedPrice = 'combined_dozen_cooked_price';
    case DeliveryFee = 'delivery_fee';
    case WhatsappOrderPhone = 'whatsapp_order_phone';
}
