<?php

namespace Tests\Feature\Instagram;

use App\Modules\Identity\Models\User;
use App\Modules\Instagram\Actions\ClaimPendingInstagramStoryPublication;
use App\Modules\Instagram\Actions\FailStaleInstagramStoryPublications;
use App\Modules\Instagram\Actions\GenerateDueInstagramStoryPublications;
use App\Modules\Instagram\Enums\InstagramStoryPublicationStatus;
use App\Modules\Instagram\Models\InstagramAutomationSetting;
use App\Modules\Instagram\Models\InstagramStoryAsset;
use App\Modules\Instagram\Models\InstagramStoryPublication;
use App\Modules\Instagram\Models\InstagramStorySchedule;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

final class InstagramAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('instagram.automation_enabled', true);
        config()->set('instagram.n8n_token', 'test-n8n-token');
        config()->set('instagram.media_disk', 'local');
        config()->set('instagram.public_media_base_url', 'https://media.example.test:8443');
        Storage::fake('local');
        InstagramAutomationSetting::current()->update(['enabled' => true]);
    }

    public function test_scheduler_creates_only_due_active_day_and_is_idempotent(): void
    {
        $asset = $this->asset();
        $schedule = InstagramStorySchedule::query()->create([
            'name' => 'Morning', 'time' => '11:00', 'days_of_week' => [1],
            'asset_id' => $asset->id, 'is_active' => true,
        ]);
        $generate = app(GenerateDueInstagramStoryPublications::class);
        $monday = CarbonImmutable::parse('2026-08-24 11:00:30', 'America/Argentina/Cordoba');

        $this->assertSame(1, $generate->execute($monday));
        $this->assertSame(0, $generate->execute($monday));
        $this->assertDatabaseCount('instagram_story_publications', 1);
        $this->assertDatabaseHas('instagram_story_publications', ['schedule_id' => $schedule->id, 'status' => 'pending']);
        $this->assertSame(0, $generate->execute($monday->subMinute()));
        $this->assertSame(0, $generate->execute($monday->addDay()));
    }

    public function test_scheduler_respects_both_deployment_and_runtime_switches(): void
    {
        $asset = $this->asset();
        InstagramStorySchedule::query()->create(['name' => 'Morning', 'time' => '11:00', 'days_of_week' => [1], 'asset_id' => $asset->id, 'is_active' => true]);
        $now = CarbonImmutable::parse('2026-08-24 11:00', 'America/Argentina/Cordoba');

        InstagramAutomationSetting::current()->update(['enabled' => false]);
        $this->assertSame(0, app(GenerateDueInstagramStoryPublications::class)->execute($now));
        InstagramAutomationSetting::current()->update(['enabled' => true]);
        config()->set('instagram.automation_enabled', false);
        $this->assertSame(0, app(GenerateDueInstagramStoryPublications::class)->execute($now));
    }

    public function test_n8n_token_and_atomic_claim_state_are_enforced(): void
    {
        $publication = $this->publication();
        $this->assertTrue((bool) config('instagram.automation_enabled'));
        $this->assertTrue(InstagramAutomationSetting::current()->enabled);
        $directlyClaimed = app(ClaimPendingInstagramStoryPublication::class)->execute();
        $this->assertSame($publication->id, $directlyClaimed?->id);
        $publication = $this->publication();
        $this->getJson('/api/automation/instagram/stories/pending')->assertUnauthorized();
        $this->withToken('wrong')->getJson('/api/automation/instagram/stories/pending')->assertUnauthorized();

        $this->withToken('test-n8n-token')->getJson('/api/automation/instagram/stories/pending')
            ->assertOk()
            ->assertJsonPath('id', $publication->id)
            ->assertJsonPath('attempt', 1)
            ->assertJsonPath(
                'asset.media_url',
                fn (string $url): bool => str_starts_with($url, 'https://media.example.test:8443/media/instagram/')
                    && str_contains($url, 'expires=')
                    && str_contains($url, 'signature='),
            );
        $this->withToken('test-n8n-token')->getJson('/api/automation/instagram/stories/pending')->assertNoContent();
        $this->assertDatabaseHas('instagram_story_publications', ['id' => $publication->id, 'status' => 'processing', 'attempts' => 1]);
    }

    public function test_callbacks_validate_state_publish_and_retry_failed_work(): void
    {
        $publication = $this->publication(['status' => InstagramStoryPublicationStatus::Processing, 'attempts' => 1, 'processing_started_at' => now()]);
        $this->withToken('test-n8n-token')->postJson("/api/automation/instagram/stories/{$publication->id}/published", ['container_id' => 'container-1', 'media_id' => 'media-1'])
            ->assertOk()->assertJson(['status' => 'published']);
        $this->assertDatabaseHas('instagram_story_publications', ['id' => $publication->id, 'status' => 'published', 'meta_media_id' => 'media-1']);
        $this->withToken('test-n8n-token')->postJson("/api/automation/instagram/stories/{$publication->id}/failed", ['error' => 'x', 'retryable' => true])->assertUnprocessable();

        $retry = $this->publication(['status' => InstagramStoryPublicationStatus::Processing, 'attempts' => 1, 'processing_started_at' => now()]);
        $this->withToken('test-n8n-token')->postJson("/api/automation/instagram/stories/{$retry->id}/failed", ['error' => 'Bearer super-secret', 'retryable' => true])
            ->assertOk()->assertJson(['status' => 'pending']);
        $this->assertDatabaseHas('instagram_story_publications', ['id' => $retry->id, 'status' => 'pending', 'error_message' => 'Bearer [redacted]']);

        config()->set('instagram.max_attempts', 2);
        $exhausted = $this->publication(['status' => InstagramStoryPublicationStatus::Processing, 'attempts' => 2, 'processing_started_at' => now()]);
        $this->withToken('test-n8n-token')->postJson("/api/automation/instagram/stories/{$exhausted->id}/failed", [
            'error' => 'Temporary Meta failure',
            'retryable' => true,
        ])->assertOk()->assertJson(['status' => 'failed']);
        $this->assertDatabaseHas('instagram_story_publications', [
            'id' => $exhausted->id,
            'status' => 'failed',
            'attempts' => 2,
            'failure_code' => 'publication_failed',
            'requires_manual_review' => false,
        ]);

        $ambiguous = $this->publication(['status' => InstagramStoryPublicationStatus::Processing, 'attempts' => 1, 'processing_started_at' => now()]);
        $this->withToken('test-n8n-token')->postJson("/api/automation/instagram/stories/{$ambiguous->id}/failed", [
            'error' => 'Meta publish timed out',
            'retryable' => true,
            'ambiguous' => true,
        ])->assertOk()->assertJson(['status' => 'failed']);
        $this->assertDatabaseHas('instagram_story_publications', [
            'id' => $ambiguous->id,
            'status' => 'failed',
            'failure_code' => 'ambiguous_publish_result',
            'requires_manual_review' => true,
        ]);

        $pending = $this->publication();
        $this->withToken('test-n8n-token')->postJson("/api/automation/instagram/stories/{$pending->id}/published", [
            'container_id' => 'container-invalid',
            'media_id' => 'media-invalid',
        ])->assertUnprocessable();
        $this->withToken('test-n8n-token')->postJson("/api/automation/instagram/stories/{$pending->id}/failed", [
            'error' => 'Invalid transition',
            'retryable' => false,
        ])->assertUnprocessable();
        $this->assertDatabaseHas('instagram_story_publications', ['id' => $pending->id, 'status' => 'pending', 'attempts' => 0]);
    }

    public function test_stale_processing_becomes_terminal_manual_review_instead_of_requeueing(): void
    {
        $publication = $this->publication(['status' => InstagramStoryPublicationStatus::Processing, 'attempts' => 1, 'processing_started_at' => now()->subHour()]);
        config()->set('instagram.processing_timeout_minutes', 20);

        $this->assertSame(1, app(FailStaleInstagramStoryPublications::class)->execute());
        $this->assertDatabaseHas('instagram_story_publications', ['id' => $publication->id, 'status' => 'failed', 'failure_code' => 'ambiguous_processing_timeout', 'requires_manual_review' => true]);
    }

    public function test_signed_media_url_streams_only_the_bound_asset_and_expires(): void
    {
        $asset = $this->asset();
        Storage::disk('local')->put($asset->file_path, 'jpeg-bytes');
        $publicBaseUrl = (string) config('instagram.public_media_base_url');
        $valid = $publicBaseUrl.URL::temporarySignedRoute('instagram.media.show', now()->addMinute(), ['asset' => $asset->id, 'filename' => 'story.jpg'], absolute: false);
        $expired = $publicBaseUrl.URL::temporarySignedRoute('instagram.media.show', now()->subMinute(), ['asset' => $asset->id, 'filename' => 'story.jpg'], absolute: false);

        $this->get($valid)->assertOk()->assertHeader('content-type', 'image/jpeg');
        $this->get($expired)->assertForbidden();

        $otherAsset = InstagramStoryAsset::query()->create([
            'name' => 'Other', 'category' => 'other', 'file_path' => 'instagram/stories/other.jpg',
            'mime_type' => 'image/jpeg', 'width' => 1080, 'height' => 1920, 'is_active' => true,
        ]);
        Storage::disk('local')->put($otherAsset->file_path, 'other-jpeg-bytes');
        $tampered = preg_replace(
            '#/media/instagram/'.$asset->id.'/story\\.jpg#',
            '/media/instagram/'.$otherAsset->id.'/story.jpg',
            $valid,
        );
        $this->assertIsString($tampered);
        $this->get($tampered)->assertForbidden();

        $tamperedExpiration = preg_replace_callback(
            '/expires=(\d+)/',
            fn (array $matches): string => 'expires='.(string) ((int) $matches[1] + 60),
            $valid,
        );
        $this->assertIsString($tamperedExpiration);
        $this->get($tamperedExpiration)->assertForbidden();

        Storage::disk('local')->delete($asset->file_path);
        $this->get($valid)->assertNotFound();
        $this->get('/media/instagram/../../.env')->assertNotFound();

        $arbitrary = InstagramStoryAsset::query()->create([
            'name' => 'Unsafe', 'category' => 'other', 'file_path' => 'private/story.jpg',
            'mime_type' => 'image/jpeg', 'width' => 1080, 'height' => 1920, 'is_active' => true,
        ]);
        Storage::disk('local')->put($arbitrary->file_path, 'private-bytes');
        $arbitraryUrl = $publicBaseUrl.URL::temporarySignedRoute('instagram.media.show', now()->addMinute(), ['asset' => $arbitrary->id, 'filename' => 'story.jpg'], absolute: false);
        $this->get($arbitraryUrl)->assertNotFound();
    }

    public function test_admin_upload_rejects_non_images_and_normalizes_png_to_jpeg(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin)->post('/admin/marketing/instagram/assets', [
            'name' => 'PDF', 'category' => 'other', 'image' => UploadedFile::fake()->create('document.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('image');

        $this->actingAs($admin)->post('/admin/marketing/instagram/assets', [
            'name' => 'Bad', 'category' => 'other', 'image' => UploadedFile::fake()->create('bad.png', 10, 'image/png'),
        ])->assertSessionHasErrors('image');

        $this->actingAs($admin)->post('/admin/marketing/instagram/assets', [
            'name' => 'Menu', 'category' => 'menu', 'is_active' => true,
            'image' => UploadedFile::fake()->image('menu.png', 1080, 1920),
        ])->assertSessionHasNoErrors();

        $asset = InstagramStoryAsset::query()->where('name', 'Menu')->firstOrFail();
        $this->assertSame('image/jpeg', $asset->mime_type);
        $this->assertStringEndsWith('.jpg', $asset->file_path);
        Storage::disk('local')->assertExists($asset->file_path);
        $this->assertSame('image/jpeg', getimagesizefromstring(Storage::disk('local')->get($asset->file_path))['mime']);
    }

    private function asset(): InstagramStoryAsset
    {
        return InstagramStoryAsset::query()->create([
            'name' => 'Menu', 'category' => 'menu', 'file_path' => 'instagram/stories/menu.jpg',
            'mime_type' => 'image/jpeg', 'width' => 1080, 'height' => 1920, 'is_active' => true,
        ]);
    }

    private function publication(array $overrides = []): InstagramStoryPublication
    {
        $asset = InstagramStoryAsset::query()->first() ?? $this->asset();

        return InstagramStoryPublication::query()->create([...[
            'asset_id' => $asset->id, 'scheduled_for' => now()->subMinute(),
            'status' => InstagramStoryPublicationStatus::Pending, 'attempts' => 0,
        ], ...$overrides]);
    }
}
