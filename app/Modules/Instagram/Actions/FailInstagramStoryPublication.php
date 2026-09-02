<?php

namespace App\Modules\Instagram\Actions;

use App\Modules\Instagram\Enums\InstagramStoryPublicationStatus;
use App\Modules\Instagram\Models\InstagramStoryPublication;
use App\Modules\Instagram\Support\SanitizeAutomationError;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class FailInstagramStoryPublication
{
    public function execute(InstagramStoryPublication $publication, string $error, bool $retryable, bool $ambiguous = false): string
    {
        return DB::transaction(function () use ($publication, $error, $retryable, $ambiguous): string {
            $locked = InstagramStoryPublication::query()->lockForUpdate()->findOrFail($publication->id);
            if ($locked->status !== InstagramStoryPublicationStatus::Processing) {
                throw ValidationException::withMessages(['publication' => 'Sólo una publicación en proceso puede marcarse como fallida.']);
            }

            $willRetry = ! $ambiguous && $retryable && $locked->attempts < max(1, (int) config('instagram.max_attempts'));
            $status = $willRetry ? InstagramStoryPublicationStatus::Pending : InstagramStoryPublicationStatus::Failed;
            $locked->update([
                'status' => $status,
                'processing_started_at' => null,
                'failed_at' => $willRetry ? null : now(),
                'error_message' => SanitizeAutomationError::execute($error),
                'failure_code' => $ambiguous ? 'ambiguous_publish_result' : ($willRetry ? 'retryable_failure' : 'publication_failed'),
                'retryable' => ! $ambiguous && $retryable,
                'requires_manual_review' => $ambiguous,
            ]);

            return $status->value;
        });
    }
}
