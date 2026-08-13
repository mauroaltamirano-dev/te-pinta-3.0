<?php

namespace App\Modules\Orders\Enums;

enum OrderStatus: string
{
    case PendingConfirmation = 'pending_confirmation';
    case Waiting = 'waiting';
    case Prepared = 'prepared';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function isClosed(): bool
    {
        return $this === self::Delivered || $this === self::Cancelled;
    }
}
