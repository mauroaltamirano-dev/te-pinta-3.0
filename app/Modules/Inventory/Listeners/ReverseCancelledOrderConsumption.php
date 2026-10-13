<?php

namespace App\Modules\Inventory\Listeners;

use App\Modules\Inventory\Actions\ReverseOrderInventoryConsumption;
use App\Modules\Orders\Events\OrderDeliveryCancelled;

final readonly class ReverseCancelledOrderConsumption
{
    public function __construct(private ReverseOrderInventoryConsumption $action) {}

    public function handle(OrderDeliveryCancelled $event): void
    {
        $this->action->execute($event->orderId, $event->businessDate, $event->adminId);
    }
}
