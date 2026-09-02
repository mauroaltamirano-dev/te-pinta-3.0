<?php

namespace App\Modules\Instagram\Actions;

use App\Modules\Instagram\Enums\InstagramStoryPublicationStatus;
use App\Modules\Instagram\Models\InstagramStoryPublication;

final class ArchiveOldInstagramStoryPublications
{
    public function execute(int $days = 30): int
    {
        $cutoff = now()->subDays($days);

        return InstagramStoryPublication::query()
            ->whereNull('archived_at')
            ->where('requires_manual_review', false)
            ->where(function ($query) use ($cutoff): void {
                $query
                    ->where(fn ($published) => $published
                        ->where('status', InstagramStoryPublicationStatus::Published->value)
                        ->where('published_at', '<', $cutoff))
                    ->orWhere(fn ($failed) => $failed
                        ->where('status', InstagramStoryPublicationStatus::Failed->value)
                        ->where('failed_at', '<', $cutoff))
                    ->orWhere(fn ($cancelled) => $cancelled
                        ->where('status', InstagramStoryPublicationStatus::Cancelled->value)
                        ->where('cancelled_at', '<', $cutoff));
            })
            ->update(['archived_at' => now(), 'updated_at' => now()]);
    }
}
