<?php

namespace Tests\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class InstagramPlaywrightFixture
{
    /** @return array<string, mixed> */
    public static function create(string $identity): array
    {
        if (! preg_match('/^[a-z0-9-]+$/', $identity)) {
            throw new RuntimeException('The Playwright fixture identity is invalid.');
        }

        $now = Carbon::now();
        $scheduleAsset = self::asset("{$identity} Story en uso", $identity.'-schedule');
        $replacementAsset = self::asset("{$identity} Story alternativa", $identity.'-replacement');

        $scheduleOne = self::schedule("{$identity} Tomando pedidos", $scheduleAsset['id'], 1, $now);
        $scheduleTwo = self::schedule("{$identity} Menú", $scheduleAsset['id'], 2, $now);

        $publications = [];
        foreach ([
            'published' => [
                'status' => 'published',
                'attempts' => 1,
                'published_at' => $now->copy()->subMinutes(20),
                'meta_container_id' => 'playwright-container',
                'meta_media_id' => 'playwright-media',
            ],
            'processing' => ['status' => 'processing', 'attempts' => 1, 'processing_started_at' => $now->copy()->subMinutes(2)],
            'failed' => [
                'status' => 'failed',
                'attempts' => 1,
                'failed_at' => $now->copy()->subMinutes(10),
                'failure_code' => 'meta_token_expired',
                'error_message' => 'El token de Meta había expirado. Technical response: OAuthException code 190.',
                'retryable' => true,
            ],
            'ambiguous' => [
                'status' => 'failed',
                'attempts' => 1,
                'failed_at' => $now->copy()->subMinutes(8),
                'failure_code' => 'ambiguous_publish',
                'error_message' => 'Meta pudo haber recibido la publicación antes de perderse la respuesta.',
                'requires_manual_review' => true,
            ],
            'cancelled' => [
                'status' => 'cancelled',
                'attempts' => 0,
                'cancelled_at' => $now->copy()->subMinutes(5),
            ],
            'pending' => ['status' => 'pending', 'attempts' => 0],
        ] as $key => $attributes) {
            $asset = self::asset("{$identity} {$key}", $identity.'-'.$key);
            $publications[$key] = [
                'id' => self::publication($asset['id'], $now->copy()->subMinutes(count($publications) + 1), $attributes, $now),
                'name' => $asset['name'],
            ];
        }

        $archiveAsset = self::asset("{$identity} published archive", $identity.'-published-archive');
        $publications['archive'] = [
            'id' => self::publication($archiveAsset['id'], $now->copy()->subDays(40), [
                'status' => 'published',
                'attempts' => 1,
                'published_at' => $now->copy()->subDays(40)->addMinute(),
            ], $now),
            'name' => $archiveAsset['name'],
        ];

        return [
            'identity' => $identity,
            'assets' => [
                'in_use' => $scheduleAsset,
                'replacement' => $replacementAsset,
            ],
            'schedules' => [
                'first' => ['id' => $scheduleOne, 'name' => "{$identity} Tomando pedidos"],
                'second' => ['id' => $scheduleTwo, 'name' => "{$identity} Menú"],
            ],
            'publications' => $publications,
        ];
    }

    /** @return array{id:int,name:string} */
    private static function asset(string $name, string $pathSuffix): array
    {
        self::assertIsolatedMediaStorage();

        $path = "instagram/stories/playwright-{$pathSuffix}.jpg";
        $image = imagecreatetruecolor(108, 192);
        if ($image === false) {
            throw new RuntimeException('The Playwright Story fixture image could not be created.');
        }

        $background = imagecolorallocate($image, 244, 116, 98);
        if ($background === false) {
            imagedestroy($image);

            throw new RuntimeException('The Playwright Story fixture color could not be allocated.');
        }
        imagefill($image, 0, 0, $background);
        ob_start();
        imagejpeg($image, null, 85);
        $contents = ob_get_clean();
        imagedestroy($image);

        if (! is_string($contents)) {
            throw new RuntimeException('The Playwright Story fixture image could not be encoded.');
        }

        Storage::disk((string) config('instagram.media_disk'))->put($path, $contents);
        $now = Carbon::now();
        $id = DB::table('instagram_story_assets')->insertGetId([
            'name' => $name,
            'category' => 'playwright',
            'file_path' => $path,
            'mime_type' => 'image/jpeg',
            'width' => 108,
            'height' => 192,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return ['id' => $id, 'name' => $name];
    }

    private static function assertIsolatedMediaStorage(): void
    {
        $disk = (string) config('instagram.media_disk');
        $root = (string) config("filesystems.disks.{$disk}.root");
        $testingRoot = storage_path('framework/testing');
        $normalizedRoot = str_replace('\\', '/', $root);
        $normalizedTestingRoot = rtrim(str_replace('\\', '/', $testingRoot), '/').'/';

        if (! app()->environment('testing') || ! str_starts_with($normalizedRoot.'/', $normalizedTestingRoot)) {
            throw new RuntimeException('The Playwright Instagram fixture media disk must be isolated under storage/framework/testing.');
        }
    }

    private static function schedule(string $name, int $assetId, int $sortOrder, Carbon $now): int
    {
        $attributes = [
            'asset_id' => $assetId,
            'name' => $name,
            'time' => '19:00',
            'days_of_week' => json_encode([1, 2, 3, 4, 5, 6, 7], JSON_THROW_ON_ERROR),
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        if (Schema::hasColumn('instagram_story_schedules', 'sort_order')) {
            $attributes['sort_order'] = $sortOrder;
        }

        return DB::table('instagram_story_schedules')->insertGetId($attributes);
    }

    /** @param array<string, mixed> $overrides */
    private static function publication(int $assetId, Carbon $scheduledFor, array $overrides, Carbon $now): int
    {
        $attributes = [
            'schedule_id' => null,
            'asset_id' => $assetId,
            'scheduled_for' => $scheduledFor,
            'status' => 'pending',
            'attempts' => 0,
            'processing_started_at' => null,
            'published_at' => null,
            'failed_at' => null,
            'error_message' => null,
            'failure_code' => null,
            'requires_manual_review' => false,
            'meta_container_id' => null,
            'meta_media_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
            ...$overrides,
        ];
        if (Schema::hasColumn('instagram_story_publications', 'archived_at')) {
            $attributes['archived_at'] = null;
        }
        if (Schema::hasColumn('instagram_story_publications', 'queue_order')) {
            $attributes['queue_order'] = 0;
        }
        if (Schema::hasColumn('instagram_story_publications', 'retryable')) {
            $attributes['retryable'] = false;
        }
        if (Schema::hasColumn('instagram_story_publications', 'cancelled_at')) {
            $attributes['cancelled_at'] = null;
        }
        if (Schema::hasColumn('instagram_story_publications', 'n8n_execution_id')) {
            $attributes['n8n_execution_id'] = 'playwright-execution';
        }

        $attributes = [...$attributes, ...$overrides];

        return DB::table('instagram_story_publications')->insertGetId($attributes);
    }
}
