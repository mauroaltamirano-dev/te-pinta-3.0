<?php

namespace App\Modules\Printing\Actions;

use App\Modules\Printing\Models\PrintJob;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Hands pending jobs to one station, oldest first. Concurrent stations never receive the same job. */
final class ClaimPrintJobs
{
    /** @return Collection<int, PrintJob> */
    public function execute(int $stationUserId, int $limit = 10): Collection
    {
        return DB::transaction(function () use ($stationUserId, $limit): Collection {
            $jobs = PrintJob::query()->where('status', 'pending')->orderBy('id')->limit($limit)->lock('for update skip locked')->get();
            foreach ($jobs as $job) {
                $job->forceFill([
                    'status' => 'claimed', 'claim_token' => (string) Str::uuid(), 'claimed_at' => now(),
                    'claimed_by' => $stationUserId, 'attempts' => $job->attempts + 1,
                ])->save();
            }

            return $jobs;
        });
    }
}
