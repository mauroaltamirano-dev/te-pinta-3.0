<?php

namespace App\Modules\Orders\Support;

use App\Modules\Orders\Enums\OrderSettingKey;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderSetting;
use Illuminate\Validation\ValidationException;

final readonly class WhatsAppOrderLink
{
    public function __construct(private OrderSummaryFormatter $formatter) {}

    public function for(Order $order): string
    {
        $phone = preg_replace('/\D+/', '', OrderSetting::string(OrderSettingKey::WhatsappOrderPhone));
        if ($phone === '') {
            throw ValidationException::withMessages(['checkout' => 'Los pedidos por WhatsApp no están configurados.']);
        }

        return 'https://wa.me/'.$phone.'?text='.rawurlencode($this->formatter->format($order));
    }
}
