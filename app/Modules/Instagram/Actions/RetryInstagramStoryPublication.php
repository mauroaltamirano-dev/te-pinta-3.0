<?php

namespace App\Modules\Instagram\Actions;

use App\Modules\Instagram\Enums\InstagramStoryPublicationStatus;
use App\Modules\Instagram\Models\InstagramStoryPublication;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RetryInstagramStoryPublication
{
    public function execute(InstagramStoryPublication $publication): void
    {
        DB::transaction(function () use ($publication): void {
            $locked = InstagramStoryPublication::query()->lockForUpdate()->findOrFail($publication->id);
            $safe = $locked->status === InstagramStoryPublicationStatus::Failed
                && $locked->retryable
                && ! $locked->requires_manual_review
                && $locked->meta_media_id === null
                && $locked->attempts < max(1, (int) config('instagram.max_attempts'));

            if (! $safe) {
                throw ValidationException::withMessages(['publication' => 'Esta publicación no se puede reintentar de forma segura. Revisá sus detalles.']);
            }

            $locked->update([
                'status' => InstagramStoryPublicationStatus::Pending,
                'processing_started_at' => null,
                'failed_at' => null,
                'archived_at' => null,
            ]);
        });
    }
}
