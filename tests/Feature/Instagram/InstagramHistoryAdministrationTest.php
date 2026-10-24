<?php

namespace Tests\Feature\Instagram;

use App\Modules\Identity\Models\User;
use App\Modules\Instagram\Actions\ClaimPendingInstagramStoryPublication;
use App\Modules\Instagram\Actions\GenerateDueInstagramStoryPublications;
use App\Modules\Instagram\Enums\InstagramStoryPublicationStatus;
use App\Modules\Instagram\Models\InstagramAutomationSetting;
use App\Modules\Instagram\Models\InstagramStoryAsset;
use App\Modules\Instagram\Models\InstagramStoryPublication;
use App\Modules\Instagram\Models\InstagramStorySchedule;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class InstagramHistoryAdministrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->administrator()->create();
        config()->set('instagram.automation_enabled', true);
        config()->set('instagram.timezone', 'America/Argentina/Cordoba');
        config()->set('instagram.max_attempts', 3);
        config()->set('instagram.media_disk', 'local');
        config()->set('instagram.public_media_base_url', 'https://media.example.test:8443');
        Storage::fake('local');
        InstagramAutomationSetting::current()->update(['enabled' => true]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_history_is_filtered_searched_paginated_and_descending(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 12:00', 'America/Argentina/Cordoba'));
        $targetAsset = $this->asset('Target Story', 'target.jpg');
        $otherAsset = $this->asset('Other Story', 'other.jpg');
        foreach (range(1, 22) as $day) {
            $this->publication($otherAsset, [
                'status' => InstagramStoryPublicationStatus::Published,
                'scheduled_for' => now()->subMinutes($day),
                'published_at' => now()->subMinutes($day),
            ]);
        }
        $target = $this->publication($targetAsset, [
            'status' => InstagramStoryPublicationStatus::Failed,
            'scheduled_for' => now(),
            'failed_at' => now(),
            'error_message' => 'Temporary Meta failure',
            'retryable' => true,
        ]);

        $this->actingAs($this->admin)->get('/admin/marketing/instagram?period=all')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/instagram/index')
                ->has('publications', 20)
                ->where('publications.0.id', $target->id)
                ->where('publication_pagination.total', 23)
                ->where('publication_pagination.last_page', 2));

        $this->actingAs($this->admin)->get('/admin/marketing/instagram?status=failed&period=today&search=Target')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('publications', 1)
                ->where('publications.0.id', $target->id)
                ->where('publications.0.type', 'manual')
                ->where('publications.0.retryable', true)
                ->where('publications.0.can_retry', true)
                ->where('publication_filters.status', 'failed')
                ->where('publication_filters.period', 'today')
                ->where('publication_filters.search', 'Target'));
    }

    public function test_admin_previews_do_not_depend_on_the_public_media_base_url(): void
    {
        config()->set('instagram.public_media_base_url', '');
        Storage::disk('local')->put('instagram/stories/menu.jpg', 'jpeg-bytes');
        $asset = $this->asset();

        $response = $this->actingAs($this->admin)->get('/admin/marketing/instagram')->assertOk();
        $previewUrl = $response->viewData('page')['props']['assets'][0]['preview_url'];

        $this->assertStringStartsWith("/media/instagram/{$asset->id}/story.jpg?", $previewUrl);
        $this->get($previewUrl)->assertOk();
    }

    public function test_archive_actions_preserve_audit_and_exclude_unsafe_rows(): void
    {
        $asset = $this->asset();
        $old = now()->subDays(31);
        $published = $this->publication($asset, ['status' => InstagramStoryPublicationStatus::Published, 'published_at' => $old, 'scheduled_for' => $old]);
        $failed = $this->publication($asset, ['status' => InstagramStoryPublicationStatus::Failed, 'failed_at' => $old, 'scheduled_for' => $old]);
        $cancelled = $this->publication($asset, ['status' => InstagramStoryPublicationStatus::Cancelled, 'cancelled_at' => $old, 'scheduled_for' => $old]);
        $pending = $this->publication($asset, ['status' => InstagramStoryPublicationStatus::Pending, 'scheduled_for' => $old]);
        $processing = $this->publication($asset, ['status' => InstagramStoryPublicationStatus::Processing, 'processing_started_at' => $old, 'scheduled_for' => $old]);
        $ambiguous = $this->publication($asset, [
            'status' => InstagramStoryPublicationStatus::Failed,
            'failed_at' => $old,
            'scheduled_for' => $old,
            'requires_manual_review' => true,
            'retryable' => false,
        ]);

        $this->actingAs($this->admin)->patch("/admin/marketing/instagram/publications/{$published->id}/archive")
            ->assertSessionHasNoErrors();
        $this->assertNotNull($published->fresh()->archived_at);
        $this->assertDatabaseCount('instagram_story_publications', 6);

        $this->actingAs($this->admin)->post('/admin/marketing/instagram/publications/archive-old', ['days' => 30])
            ->assertSessionHasNoErrors();
        $this->assertNotNull($failed->fresh()->archived_at);
        $this->assertNotNull($cancelled->fresh()->archived_at);
        $this->assertNull($pending->fresh()->archived_at);
        $this->assertNull($processing->fresh()->archived_at);
        $this->assertNull($ambiguous->fresh()->archived_at);

        $this->actingAs($this->admin)->patch("/admin/marketing/instagram/publications/{$processing->id}/archive")
            ->assertSessionHasErrors('publication');

        $this->actingAs($this->admin)->get('/admin/marketing/instagram?period=all')
            ->assertInertia(fn (Assert $page) => $page->where('publication_pagination.total', 3));
        $this->actingAs($this->admin)->get('/admin/marketing/instagram?period=all&show_archived=1')
            ->assertInertia(fn (Assert $page) => $page->where('publication_pagination.total', 6));
    }

    public function test_retry_and_cancel_are_locked_to_safe_states(): void
    {
        $asset = $this->asset();
        $retryable = $this->publication($asset, [
            'status' => InstagramStoryPublicationStatus::Failed,
            'attempts' => 1,
            'failed_at' => now(),
            'retryable' => true,
        ]);
        $ambiguous = $this->publication($asset, [
            'status' => InstagramStoryPublicationStatus::Failed,
            'attempts' => 1,
            'failed_at' => now(),
            'retryable' => true,
            'requires_manual_review' => true,
        ]);
        $processing = $this->publication($asset, [
            'status' => InstagramStoryPublicationStatus::Processing,
            'attempts' => 1,
            'processing_started_at' => now(),
        ]);
        $pending = $this->publication($asset);

        $this->actingAs($this->admin)->post("/admin/marketing/instagram/publications/{$retryable->id}/retry")
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('instagram_story_publications', ['id' => $retryable->id, 'status' => 'pending', 'attempts' => 1]);

        $this->actingAs($this->admin)->post("/admin/marketing/instagram/publications/{$ambiguous->id}/retry")
            ->assertSessionHasErrors('publication');
        $this->actingAs($this->admin)->post("/admin/marketing/instagram/publications/{$ambiguous->id}/cancel")
            ->assertSessionHasErrors('publication');
        $this->actingAs($this->admin)->post("/admin/marketing/instagram/publications/{$processing->id}/cancel")
            ->assertSessionHasErrors('publication');

        $this->actingAs($this->admin)->post("/admin/marketing/instagram/publications/{$pending->id}/cancel")
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('instagram_story_publications', ['id' => $pending->id, 'status' => 'cancelled', 'retryable' => false]);
        $this->assertNotNull($pending->fresh()->cancelled_at);
    }

    public function test_assets_and_active_schedules_preserve_reference_integrity(): void
    {
        $asset = $this->asset();
        $schedule = InstagramStorySchedule::query()->create([
            'name' => 'Menu at night', 'time' => '19:00', 'days_of_week' => [1, 2, 3, 4, 5, 6, 7],
            'sort_order' => 1, 'asset_id' => $asset->id, 'is_active' => true,
        ]);

        $this->actingAs($this->admin)->delete("/admin/marketing/instagram/assets/{$asset->id}")
            ->assertSessionHasErrors('asset');
        $this->assertDatabaseHas('instagram_story_assets', ['id' => $asset->id]);

        $this->actingAs($this->admin)->patch("/admin/marketing/instagram/assets/{$asset->id}", [
            'name' => $asset->name, 'category' => $asset->category, 'is_active' => false,
        ])->assertSessionHasErrors('is_active');
        $this->assertTrue($asset->fresh()->is_active);

        $this->actingAs($this->admin)->patch("/admin/marketing/instagram/schedules/{$schedule->id}", [
            'name' => $schedule->name, 'time' => '19:00', 'days_of_week' => [1, 2, 3, 4, 5, 6, 7],
            'sort_order' => 1, 'asset_id' => null, 'is_active' => true,
        ])->assertSessionHasErrors('asset_id');
        $this->assertSame($asset->id, $schedule->fresh()->asset_id);
    }

    public function test_same_time_schedules_are_generated_and_claimed_in_explicit_order_and_sunday_is_iso_seven(): void
    {
        $asset = $this->asset();
        $this->actingAs($this->admin)->post('/admin/marketing/instagram/schedules', [
            'name' => 'Invalid order', 'time' => '19:00', 'days_of_week' => [7], 'sort_order' => 0,
            'asset_id' => $asset->id, 'is_active' => true,
        ])->assertSessionHasErrors('sort_order');
        $this->actingAs($this->admin)->post('/admin/marketing/instagram/schedules', [
            'name' => 'Menu', 'time' => '19:00', 'days_of_week' => [1, 2, 3, 4, 5, 6, 7], 'sort_order' => 2,
            'asset_id' => $asset->id, 'is_active' => true,
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post('/admin/marketing/instagram/schedules', [
            'name' => 'Orders open', 'time' => '19:00', 'days_of_week' => [2, 3, 4, 5, 6, 7], 'sort_order' => 1,
            'asset_id' => $asset->id, 'is_active' => true,
        ])->assertSessionHasNoErrors();
        $later = InstagramStorySchedule::query()->where('name', 'Menu')->firstOrFail();
        $first = InstagramStorySchedule::query()->where('name', 'Orders open')->firstOrFail();
        $sunday = CarbonImmutable::parse('2026-08-30 19:00', 'America/Argentina/Cordoba');
        CarbonImmutable::setTestNow($sunday);

        $this->assertSame(2, app(GenerateDueInstagramStoryPublications::class)->execute($sunday));
        $this->assertDatabaseHas('instagram_story_publications', ['schedule_id' => $first->id, 'queue_order' => 1]);
        $this->assertDatabaseHas('instagram_story_publications', ['schedule_id' => $later->id, 'queue_order' => 2]);

        $claimedFirst = app(ClaimPendingInstagramStoryPublication::class)->execute();
        $claimedLater = app(ClaimPendingInstagramStoryPublication::class)->execute();
        $this->assertSame($first->id, $claimedFirst?->schedule_id);
        $this->assertSame($later->id, $claimedLater?->schedule_id);
    }

    public function test_new_admin_actions_require_administrator_authorization(): void
    {
        $publication = $this->publication($this->asset(), ['status' => InstagramStoryPublicationStatus::Published, 'published_at' => now()]);
        $user = User::factory()->create();

        $this->patch("/admin/marketing/instagram/publications/{$publication->id}/archive")->assertRedirect('/login');
        $this->actingAs($user)->patch("/admin/marketing/instagram/publications/{$publication->id}/archive")->assertForbidden();
        $this->assertNull($publication->fresh()->archived_at);
    }

    private function asset(string $name = 'Menu', string $file = 'menu.jpg'): InstagramStoryAsset
    {
        return InstagramStoryAsset::query()->create([
            'name' => $name, 'category' => 'menu', 'file_path' => "instagram/stories/{$file}",
            'mime_type' => 'image/jpeg', 'width' => 1080, 'height' => 1920, 'is_active' => true,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function publication(InstagramStoryAsset $asset, array $overrides = []): InstagramStoryPublication
    {
        return InstagramStoryPublication::query()->create([...[
            'asset_id' => $asset->id,
            'scheduled_for' => now()->subMinute(),
            'status' => InstagramStoryPublicationStatus::Pending,
            'attempts' => 0,
        ], ...$overrides]);
    }
}
