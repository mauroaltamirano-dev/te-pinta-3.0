<?php

namespace App\Modules\Printing\Http\Controllers;

use App\Modules\Printing\Actions\AcknowledgePrintJob;
use App\Modules\Printing\Actions\ClaimPrintJobs;
use App\Modules\Printing\Actions\ReleasePrintJob;
use App\Modules\Printing\Actions\ReprintPrintJob;
use App\Modules\Printing\Http\Resources\PrintJobResource;
use App\Modules\Printing\Models\PrintJob;
use App\Modules\Printing\Queries\PrintQueueStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class PrintJobController
{
    public function status(PrintQueueStatus $status): JsonResponse
    {
        return response()->json(['data' => $status->get()]);
    }

    public function recent(): AnonymousResourceCollection
    {
        return PrintJobResource::collection(PrintJob::query()->latest('id')->limit(30)->get());
    }

    public function claim(Request $request, ClaimPrintJobs $claim): AnonymousResourceCollection
    {
        return PrintJobResource::collection($claim->execute((int) $request->user()->getAuthIdentifier()));
    }

    public function printed(Request $request, int $job, AcknowledgePrintJob $acknowledge): PrintJobResource
    {
        $validated = $request->validate(['claim_token' => ['nullable', 'uuid']]);

        return new PrintJobResource($acknowledge->execute($job, $validated['claim_token'] ?? null, (int) $request->user()->getAuthIdentifier()));
    }

    public function release(Request $request, int $job, ReleasePrintJob $release): PrintJobResource
    {
        return new PrintJobResource($release->execute($job, (int) $request->user()->getAuthIdentifier()));
    }

    public function reprint(Request $request, int $job, ReprintPrintJob $reprint): JsonResponse
    {
        return (new PrintJobResource($reprint->execute($job, (int) $request->user()->getAuthIdentifier())))->response()->setStatusCode(201);
    }
}
