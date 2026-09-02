<?php

namespace App\Modules\Instagram\Actions;

use App\Modules\Instagram\Enums\InstagramStoryPublicationStatus;
use App\Modules\Instagram\Models\InstagramStoryPublication;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CompleteInstagramStoryPublication
{
    public function execute(InstagramStoryPublication $publication, string $containerId, string $mediaId): void
    {
        DB::transaction(function () use ($publication, $containerId, $mediaId): void {
            $locked = InstagramStoryPublication::query()->lockForUpdate()->findOrFail($publication->id);

            if ($locked->status === InstagramStoryPublicationStatus::Published) {
                if ($locked->meta_container_id === $containerId && $locked->meta_media_id === $mediaId) {
                    return;
                }

                throw ValidationException::withMessages(['publication' => 'La publicación ya se completó con otros identificadores de Meta.']);
            }

            $isLateAmbiguousResult = $locked->status === InstagramStoryPublicationStatus::Failed
                && $locked->failure_code === 'ambiguous_processing_timeout';
            if ($locked->status !== InstagramStoryPublicationStatus::Processing && ! $isLateAmbiguousResult) {
                throw ValidationException::withMessages(['publication' => 'Sólo una publicación en proceso puede marcarse como publicada.']);
            }

            $locked->update([
                'status' => InstagramStoryPublicationStatus::Published->value,
                'published_at' => now(),
                'meta_container_id' => $containerId,
                'meta_media_id' => $mediaId,
                'error_message' => null,
                'failure_code' => null,
                'retryable' => false,
                'requires_manual_review' => false,
            ]);
        });
    }
}
