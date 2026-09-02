<?php

namespace App\Modules\Instagram\Http\Controllers;

use App\Modules\Instagram\Actions\ClaimPendingInstagramStoryPublication;
use App\Modules\Instagram\Actions\CompleteInstagramStoryPublication;
use App\Modules\Instagram\Actions\FailInstagramStoryPublication;
use App\Modules\Instagram\Models\InstagramStoryPublication;
use App\Modules\Instagram\Support\InstagramStoryMediaUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class InstagramAutomationApiController
{
    public function pending(ClaimPendingInstagramStoryPublication $claim, InstagramStoryMediaUrl $mediaUrl): JsonResponse
    {
        $publication = $claim->execute();
        if (! $publication) {
            return response()->json(null, 204);
        }

        return response()->json([
            'id' => $publication->id,
            'scheduled_for' => $publication->scheduled_for->toIso8601String(),
            'attempt' => $publication->attempts,
            'asset' => [
                'name' => $publication->asset->name,
                'mime_type' => $publication->asset->mime_type,
                'width' => $publication->asset->width,
                'height' => $publication->asset->height,
                'media_url' => $mediaUrl->temporary($publication->asset),
            ],
        ]);
    }

    public function published(Request $request, InstagramStoryPublication $publication, CompleteInstagramStoryPublication $complete): JsonResponse
    {
        $data = $request->validate(['container_id' => ['required', 'string', 'max:255'], 'media_id' => ['required', 'string', 'max:255']]);
        $complete->execute($publication, $data['container_id'], $data['media_id']);

        return response()->json(['status' => 'published']);
    }

    public function failed(Request $request, InstagramStoryPublication $publication, FailInstagramStoryPublication $fail): JsonResponse
    {
        $data = $request->validate([
            'error' => ['required', 'string', 'max:10000'],
            'retryable' => ['required', 'boolean'],
            'ambiguous' => ['sometimes', 'boolean'],
        ]);

        return response()->json([
            'status' => $fail->execute($publication, $data['error'], $data['retryable'], (bool) ($data['ambiguous'] ?? false)),
        ]);
    }
}
