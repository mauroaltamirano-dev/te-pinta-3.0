<?php

namespace App\Modules\Printing\Queries;

use Illuminate\Support\Facades\DB;

final class PrintQueueStatus
{
    /** @return array{pending: int, oldest_pending_seconds: int|null, unconfirmed: int} */
    public function get(): array
    {
        $pending = DB::table('print_jobs')->where('status', 'pending')->selectRaw('count(*) as total, min(created_at) as oldest')->first();
        $oldest = $pending?->oldest === null ? null : max(0, (int) now()->diffInSeconds($pending->oldest, true));

        return [
            'pending' => (int) ($pending->total ?? 0),
            'oldest_pending_seconds' => $oldest,
            'unconfirmed' => DB::table('print_jobs')->where('status', 'claimed')->count(),
        ];
    }
}
