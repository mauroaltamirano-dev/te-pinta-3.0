<?php

namespace App\Modules\Instagram\Actions;

use App\Modules\Instagram\Enums\InstagramStoryPublicationStatus;
use App\Modules\Instagram\Models\InstagramStoryPublication;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ArchiveInstagramStoryPublication
{
    public function execute(InstagramStoryPublication $publication): void
    {
        DB::transaction(function () use ($publication): void {
            $locked = InstagramStoryPublication::query()->lockForUpdate()->findOrFail($publication->id);

            if (! in_array($locked->status, [InstagramStoryPublicationStatus::Published, InstagramStoryPublicationStatus::Failed, InstagramStoryPublicationStatus::Cancelled], true)) {
                throw ValidationException::withMessages(['publication' => 'Sólo se pueden archivar publicaciones finalizadas.']);
            }

            if ($locked->archived_at === null) {
                $locked->update(['archived_at' => now()]);
            }
        });
    }
}
