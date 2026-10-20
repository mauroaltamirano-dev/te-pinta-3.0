<?php

namespace App\Modules\Printing\Queries;

use App\Modules\Printing\Models\PrintJob;

final class PrintQueueStatus
{
    /** @return array{pending: int, oldest_pending_seconds: int|null, unconfirmed: int, oldest_unconfirmed_seconds: int|null} */
    public function get(): array
    {
        $pending = PrintJob::queue()->toBase()->where('status', 'pending')->selectRaw('count(*) as total, min(created_at) as oldest')->first();
        $unconfirmed = PrintJob::queue()->toBase()->where('status', 'claimed')->selectRaw('count(*) as total, min(claimed_at) as oldest')->first();

        return [
            'pending' => (int) ($pending->total ?? 0),
            'oldest_pending_seconds' => $this->age($pending?->oldest),
            'unconfirmed' => (int) ($unconfirmed->total ?? 0),
            // A claim the station never acknowledged: printing right now, or stuck after a lost response.
            'oldest_unconfirmed_seconds' => $this->age($unconfirmed?->oldest),
        ];
    }

    private function age(?string $since): ?int
    {
        return $since === null ? null : max(0, (int) now()->diffInSeconds($since, true));
    }
}
