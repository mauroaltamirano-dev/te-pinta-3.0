<?php

namespace App\Modules\Management\Listeners;

use App\Modules\Management\Actions\ApplyDistributionToOrderPayment;
use App\Modules\Management\Actions\CreateManagementOrderPayment;
use App\Modules\Orders\Events\OrderPaymentConfirmed;

final readonly class RecordConfirmedOrderPayment
{
    public function __construct(
        private CreateManagementOrderPayment $create,
        private ApplyDistributionToOrderPayment $applyDistribution,
    ) {}

    public function handle(OrderPaymentConfirmed $event): void
    {
        $payment = $this->create->execute($event->orderId, $event->amount, $event->confirmedOn, $event->adminId);
        $this->applyDistribution->execute($payment, $event->adminId);
    }
}
