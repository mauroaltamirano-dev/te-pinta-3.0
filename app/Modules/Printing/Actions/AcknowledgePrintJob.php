<?php

namespace App\Modules\Printing\Actions;

use App\Modules\Printing\Models\PrintJob;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Marks a claimed job printed. Repeating the acknowledgement with the same claim token is a no-op;
 * a token from another claim is a conflict. Without a token a person confirms a job that printed
 * but was never acknowledged.
 */
final class AcknowledgePrintJob
{
    public function execute(int $jobId, ?string $claimToken, int $userId): PrintJob
    {
        return DB::transaction(function () use ($jobId, $claimToken, $userId): PrintJob {
            $job = PrintJob::queue()->lockForUpdate()->findOrFail($jobId);
            if ($job->status === 'printed' && ($claimToken === null || $job->claim_token === $claimToken)) {
                return $job;
            }
            if ($job->status !== 'claimed' || ($claimToken !== null && $job->claim_token !== $claimToken)) {
                throw new ConflictHttpException('This print job belongs to another claim.');
            }
            $job->forceFill(['status' => 'printed', 'printed_at' => now(), 'printed_by' => $userId])->save();

            return $job;
        });
    }
}
