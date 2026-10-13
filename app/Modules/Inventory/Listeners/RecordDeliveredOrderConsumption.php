<?php

namespace App\Modules\Inventory\Listeners;

use App\Modules\Inventory\Actions\ConsumeDeliveredOrder;
use App\Modules\Orders\Events\OrderDelivered;

final readonly class RecordDeliveredOrderConsumption
{
    public function __construct(private ConsumeDeliveredOrder $action) {}

    public function handle(OrderDelivered $event): void
    {
        $this->action->execute($event->orderId, $event->businessDate, $event->adminId);
    }
}
