<?php

namespace App\Modules\Orders\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\TableSession;
use App\Modules\Orders\Support\TableSessionGuard;
use Illuminate\Support\Facades\DB;

/** Flags an open table as waiting for its bill; it prints nothing and does not lock the table. */
final readonly class RequestTableBill
{
    public function __construct(private TableSessionGuard $guard) {}

    public function execute(TableSession $session, User $actor): TableSession
    {
        return DB::transaction(function () use ($session, $actor): TableSession {
            $locked = $this->guard->lockOpen($session, $actor, cashierOnly: false);
            if ($locked->bill_requested_at === null) {
                $locked->update(['bill_requested_at' => now(), 'bill_requested_by' => $actor->id]);
            }

            return $locked;
        });
    }
}
