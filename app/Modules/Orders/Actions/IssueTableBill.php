<?php

namespace App\Modules\Orders\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\TableSession;
use App\Modules\Orders\Support\OrderDocuments;
use App\Modules\Orders\Support\TableSessionGuard;
use Illuminate\Support\Facades\DB;

/**
 * Prints the detailed bill of an open table from its current order snapshot. One bill per order
 * version: printing again without new rounds reuses the same job; a new round yields a new bill.
 */
final readonly class IssueTableBill
{
    public function __construct(private TableSessionGuard $guard, private OrderDocuments $documents) {}

    public function execute(TableSession $session, User $actor): void
    {
        DB::transaction(function () use ($session, $actor): void {
            $locked = $this->guard->lockOpen($session, $actor, cashierOnly: true);
            $order = Order::query()->lockForUpdate()->findOrFail($locked->order_id);
            event($this->documents->bill($locked->load('waiter'), $order, $actor));
        });
    }
}
