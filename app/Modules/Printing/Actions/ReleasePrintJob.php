<?php

namespace App\Modules\Printing\Actions;

use App\Modules\Printing\Models\PrintJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** A person decided that a claimed, unconfirmed job did not print: it goes back to the queue. */
final class ReleasePrintJob
{
    public function execute(int $jobId, int $userId): PrintJob
    {
        $job = DB::transaction(function () use ($jobId): PrintJob {
            $job = PrintJob::queue()->lockForUpdate()->findOrFail($jobId);
            if ($job->status !== 'claimed') {
                throw new ConflictHttpException('Only an unconfirmed print job can be released.');
            }
            $job->forceFill(['status' => 'pending', 'claim_token' => null, 'claimed_at' => null, 'claimed_by' => null])->save();

            return $job;
        });
        Log::info('[Printing] print_job.released', ['module' => 'Printing', 'print_job_id' => $job->id, 'actor_id' => $userId]);

        return $job;
    }
}
