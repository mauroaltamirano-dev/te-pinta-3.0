<?php

namespace App\Modules\Instagram\Http\Controllers;

use App\Modules\Instagram\Models\InstagramStoryAsset;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class InstagramStoryMediaController
{
    public function __invoke(InstagramStoryAsset $asset, string $filename): StreamedResponse
    {
        abort_unless($filename === 'story.jpg', 404);
        abort_unless(
            str_starts_with($asset->file_path, 'instagram/stories/')
                && ! str_contains($asset->file_path, '..')
                && str_ends_with($asset->file_path, '.jpg'),
            404,
        );
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk((string) config('instagram.media_disk'));
        abort_unless($disk->exists($asset->file_path), 404);

        return $disk->response($asset->file_path, 'story.jpg', [
            'Content-Type' => $asset->mime_type,
            'Content-Disposition' => 'inline; filename="story.jpg"',
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
