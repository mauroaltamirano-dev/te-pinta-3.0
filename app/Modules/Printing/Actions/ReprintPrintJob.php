<?php

namespace App\Modules\Printing\Actions;

use App\Modules\Printing\Models\PrintJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** An explicit reprint: a new pending job with the original frozen payload. */
final class ReprintPrintJob
{
    public function execute(int $jobId, int $userId): PrintJob
    {
        $original = PrintJob::queue()->findOrFail($jobId);
        $root = $original->reprint_of_id ?? $original->id;
        $reprint = PrintJob::query()->create([
            'kind' => $original->kind, 'order_id' => $original->order_id, 'dedupe_key' => "reprint:{$root}:".Str::uuid(),
            'payload' => $original->payload, 'reprint_of_id' => $root, 'requested_by' => $userId,
        ]);
        Log::info('[Printing] print_job.reprinted', ['module' => 'Printing', 'print_job_id' => $reprint->id, 'reprint_of_id' => $root, 'actor_id' => $userId]);

        return $reprint;
    }
}
