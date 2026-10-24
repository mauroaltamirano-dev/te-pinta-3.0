<?php

namespace App\Modules\Instagram\Support;

use App\Modules\Instagram\Models\InstagramStoryAsset;
use Illuminate\Support\Facades\URL;
use UnexpectedValueException;

final class InstagramStoryMediaUrl
{
    /** Absolute URL that Meta fetches through the public media origin. */
    public function temporary(InstagramStoryAsset $asset, ?int $ttlMinutes = null): string
    {
        return $this->publicBaseUrl().$this->preview($asset, $ttlMinutes);
    }

    /** Host-relative URL for admin previews, served by whichever host the admin is using. */
    public function preview(InstagramStoryAsset $asset, ?int $ttlMinutes = null): string
    {
        $ttlMinutes ??= (int) config('instagram.media_url_ttl_minutes');

        return URL::temporarySignedRoute(
            'instagram.media.show',
            now()->addMinutes(max(1, $ttlMinutes)),
            ['asset' => $asset->id, 'filename' => 'story.jpg'],
            absolute: false,
        );
    }

    private function publicBaseUrl(): string
    {
        $baseUrl = rtrim((string) config('instagram.public_media_base_url'), '/');
        $parts = parse_url($baseUrl);

        if (
            $baseUrl === ''
            || $parts === false
            || ! in_array($parts['scheme'] ?? null, ['http', 'https'], true)
            || ! isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || ! in_array($parts['path'] ?? '', ['', '/'], true)
        ) {
            throw new UnexpectedValueException('La URL base pública de medios de Instagram debe ser un origen HTTP(S) sin ruta, consulta ni credenciales.');
        }

        if (app()->environment('production') && $parts['scheme'] !== 'https') {
            throw new UnexpectedValueException('La URL base pública de medios de Instagram debe usar HTTPS en producción.');
        }

        return $baseUrl;
    }
}
