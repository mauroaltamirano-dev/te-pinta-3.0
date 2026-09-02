<?php

namespace App\Modules\Instagram\Actions;

use App\Modules\Instagram\Enums\InstagramStoryPublicationStatus;
use App\Modules\Instagram\Models\InstagramStoryPublication;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CancelInstagramStoryPublication
{
    public function execute(InstagramStoryPublication $publication): void
    {
        DB::transaction(function () use ($publication): void {
            $locked = InstagramStoryPublication::query()->lockForUpdate()->findOrFail($publication->id);
            $safe = in_array($locked->status, [InstagramStoryPublicationStatus::Pending, InstagramStoryPublicationStatus::Failed], true)
                && ! $locked->requires_manual_review
                && $locked->meta_media_id === null;

            if (! $safe) {
                throw ValidationException::withMessages(['publication' => 'Esta publicación no se puede cancelar de forma segura. Primero conciliá las publicaciones en proceso o pendientes de revisión.']);
            }

            $locked->update([
                'status' => InstagramStoryPublicationStatus::Cancelled,
                'processing_started_at' => null,
                'cancelled_at' => now(),
                'retryable' => false,
                'archived_at' => null,
            ]);
        });
    }
}
