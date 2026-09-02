<?php

namespace App\Modules\Instagram\Actions;

use App\Modules\Instagram\Enums\InstagramStoryPublicationStatus;
use App\Modules\Instagram\Models\InstagramStoryPublication;

final class FailStaleInstagramStoryPublications
{
    public function execute(): int
    {
        $cutoff = now()->subMinutes(max(1, (int) config('instagram.processing_timeout_minutes')));

        return InstagramStoryPublication::query()
            ->where('status', InstagramStoryPublicationStatus::Processing)
            ->where('processing_started_at', '<', $cutoff)
            ->update([
                'status' => InstagramStoryPublicationStatus::Failed->value,
                'failed_at' => now(),
                'error_message' => 'Se desconoce el resultado porque la automatización no informó un estado final. Revisá la publicación manualmente antes de reintentar.',
                'failure_code' => 'ambiguous_processing_timeout',
                'retryable' => false,
                'requires_manual_review' => true,
                'updated_at' => now(),
            ]);
    }
}
