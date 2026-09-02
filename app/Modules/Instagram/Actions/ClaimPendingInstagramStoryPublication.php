<?php

namespace App\Modules\Instagram\Actions;

use App\Modules\Instagram\Enums\InstagramStoryPublicationStatus;
use App\Modules\Instagram\Models\InstagramAutomationSetting;
use App\Modules\Instagram\Models\InstagramStoryPublication;
use Illuminate\Support\Facades\DB;

final class ClaimPendingInstagramStoryPublication
{
    public function execute(): ?InstagramStoryPublication
    {
        if (! config('instagram.automation_enabled') || ! InstagramAutomationSetting::current()->enabled) {
            return null;
        }

        return DB::transaction(function (): ?InstagramStoryPublication {
            $publication = InstagramStoryPublication::query()
                ->where('status', InstagramStoryPublicationStatus::Pending->value)
                ->where('attempts', '<', max(1, (int) config('instagram.max_attempts')))
                ->where('scheduled_for', '<=', now())
                ->with('asset')
                ->orderBy('scheduled_for')
                ->orderBy('queue_order')
                ->orderBy('id')
                ->lock('for update skip locked')
                ->first();

            if (! $publication) {
                return null;
            }

            $publication->update([
                'status' => InstagramStoryPublicationStatus::Processing,
                'attempts' => $publication->attempts + 1,
                'processing_started_at' => now(),
                'failed_at' => null,
                'error_message' => null,
                'failure_code' => null,
                'retryable' => false,
                'requires_manual_review' => false,
            ]);

            return $publication->fresh('asset');
        });
    }
}
