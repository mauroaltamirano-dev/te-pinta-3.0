<?php

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Enums\PaymentMethod;
use App\Modules\Orders\Enums\PaymentStatus;

final readonly class ResolveOrderPayment
{
    /** @return array{status: PaymentStatus, method: ?PaymentMethod} */
    public function forTotal(int $total, PaymentStatus $requestedStatus, ?PaymentMethod $requestedMethod): array
    {
        if ($total === 0) {
            return ['status' => PaymentStatus::NotRequired, 'method' => null];
        }

        if ($requestedStatus === PaymentStatus::Paid) {
            return ['status' => PaymentStatus::Paid, 'method' => $requestedMethod];
        }

        return ['status' => PaymentStatus::Unpaid, 'method' => null];
    }
}
