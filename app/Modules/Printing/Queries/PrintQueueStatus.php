<?php

namespace App\Modules\Printing\Queries;

use App\Modules\Printing\Models\PrintJob;

final class PrintQueueStatus
{
    /** @return array{pending: int, oldest_pending_seconds: int|null, unconfirmed: int} */
    public function get(): array
    {
        $pending = PrintJob::queue()->toBase()->where('status', 'pending')->selectRaw('count(*) as total, min(created_at) as oldest')->first();
        $oldest = $pending?->oldest === null ? null : max(0, (int) now()->diffInSeconds($pending->oldest, true));

        return [
            'pending' => (int) ($pending->total ?? 0),
            'oldest_pending_seconds' => $oldest,
            'unconfirmed' => PrintJob::queue()->where('status', 'claimed')->count(),
        ];
    }
}
