<?php

namespace Tests\Feature\Marketing;

use App\Modules\Identity\Models\User;
use App\Modules\Marketing\Enums\FeaturedProductStatus;
use App\Modules\Marketing\Models\FeaturedProduct;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FeaturedProductSchedulingTest extends TestCase
{
    use InteractsWithMarketing;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-08-23 15:00:00 UTC'));
    }

    public function test_temporal_statuses_and_half_open_boundaries_are_derived(): void
    {
        $now = CarbonImmutable::parse('2026-08-23 15:00:00 UTC');
        $record = fn (array $attributes): FeaturedProduct => new FeaturedProduct($attributes);

        $disabled = $record([
            'starts_at' => $now->subHour(),
            'ends_at' => $now->addHour(),
            'is_enabled' => false,
        ]);
        $future = $record([
            'starts_at' => $now->addHour(),
            'ends_at' => $now->addHours(2),
            'is_enabled' => true,
        ]);
        $active = $record([
            'starts_at' => $now,
            'ends_at' => $now->addHour(),
            'is_enabled' => true,
        ]);
        $finished = $record([
            'starts_at' => $now->subHours(2),
            'ends_at' => $now,
            'is_enabled' => true,
        ]);
        $indefinite = $record([
            'starts_at' => $now->subHour(),
            'ends_at' => null,
            'is_enabled' => true,
        ]);

        $this->assertSame(FeaturedProductStatus::Inactive, $disabled->statusAt($now));
        $this->assertSame(FeaturedProductStatus::Scheduled, $future->statusAt($now));
        $this->assertSame(FeaturedProductStatus::Active, $active->statusAt($now));
        $this->assertSame(FeaturedProductStatus::Finished, $finished->statusAt($now));
        $this->assertSame(FeaturedProductStatus::Active, $indefinite->statusAt($now));
    }

    public function test_adjacent_enabled_windows_are_allowed_and_overlap_is_rejected(): void
    {
        $admin = User::factory()->administrator()->create();
        $product = $this->product();

        $this->actingAs($admin)->post(route('admin.marketing.featured-products.store'), $this->payload($product, [
            'starts_at' => '2026-08-24T10:00',
            'ends_at' => '2026-08-24T18:00',
        ]))->assertSessionHasNoErrors();

        $this->actingAs($admin)->post(route('admin.marketing.featured-products.store'), $this->payload($product, [
            'starts_at' => '2026-08-24T18:00',
            'ends_at' => '2026-08-24T22:00',
        ]))->assertSessionHasNoErrors();

        $this->actingAs($admin)->post(route('admin.marketing.featured-products.store'), $this->payload($product, [
            'starts_at' => '2026-08-24T17:00',
            'ends_at' => '2026-08-24T22:00',
        ]))->assertSessionHasErrors([
            'starts_at' => 'Ya existe una recomendación programada para parte de este período.',
        ]);

        $this->assertDatabaseCount('marketing_featured_products', 2);
    }

    public function test_update_cannot_move_a_window_over_an_existing_schedule(): void
    {
        $admin = User::factory()->administrator()->create();
        $product = $this->product();

        foreach ([['10:00', '18:00'], ['18:00', '22:00']] as [$start, $end]) {
            $this->actingAs($admin)->post(route('admin.marketing.featured-products.store'), $this->payload($product, [
                'starts_at' => "2026-08-24T{$start}",
                'ends_at' => "2026-08-24T{$end}",
            ]))->assertSessionHasNoErrors();
        }

        $second = FeaturedProduct::query()->orderByDesc('starts_at')->firstOrFail();
        $this->actingAs($admin)->patch(
            route('admin.marketing.featured-products.update', $second),
            $this->payload($product, [
                'starts_at' => '2026-08-24T17:00',
                'ends_at' => '2026-08-24T22:00',
            ]),
        )->assertSessionHasErrors('starts_at');

        $this->assertSame('2026-08-24T21:00:00+00:00', $second->refresh()->starts_at->toIso8601String());
    }

    public function test_open_ended_window_blocks_later_enabled_records_but_disabled_records_do_not(): void
    {
        $admin = User::factory()->administrator()->create();
        $product = $this->product();

        $this->actingAs($admin)->post(route('admin.marketing.featured-products.store'), $this->payload($product, [
            'starts_at' => '2026-08-24T10:00',
            'ends_at' => null,
        ]))->assertSessionHasNoErrors();

        $this->actingAs($admin)->post(route('admin.marketing.featured-products.store'), $this->payload($product, [
            'starts_at' => '2026-08-25T10:00',
            'ends_at' => '2026-08-25T18:00',
        ]))->assertSessionHasErrors('starts_at');

        FeaturedProduct::query()->sole()->update(['is_enabled' => false]);

        $this->actingAs($admin)->post(route('admin.marketing.featured-products.store'), $this->payload($product, [
            'starts_at' => '2026-08-25T10:00',
            'ends_at' => '2026-08-25T18:00',
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('marketing_featured_products', 2);
    }

    public function test_database_constraint_rejects_enabled_overlap_outside_actions(): void
    {
        $product = $this->product();
        $this->featured($product);

        try {
            FeaturedProduct::query()->create([
                'product_id' => $product->id,
                'starts_at' => '2026-08-23 19:00:00+00',
                'ends_at' => '2026-08-23 21:00:00+00',
                'is_enabled' => true,
            ]);
            $this->fail('The exclusion constraint should reject overlapping enabled windows.');
        } catch (QueryException $exception) {
            $this->assertSame('23P01', $exception->getCode());
        }
    }

    public function test_database_check_rejects_an_end_not_after_its_start(): void
    {
        $product = $this->product();

        try {
            FeaturedProduct::query()->create([
                'product_id' => $product->id,
                'starts_at' => '2026-08-24 20:00:00+00',
                'ends_at' => '2026-08-24 20:00:00+00',
                'is_enabled' => true,
            ]);
            $this->fail('The database check should reject an empty window.');
        } catch (QueryException $exception) {
            $this->assertSame('23514', $exception->getCode());
        }
    }

    public function test_end_must_be_after_start_and_text_is_bounded(): void
    {
        $admin = User::factory()->administrator()->create();
        $product = $this->product();

        $this->actingAs($admin)->post(route('admin.marketing.featured-products.store'), $this->payload($product, [
            'headline' => str_repeat('a', 81),
            'short_copy' => str_repeat('b', 241),
            'starts_at' => '2026-08-24T18:00',
            'ends_at' => '2026-08-24T18:00',
        ]))->assertSessionHasErrors(['headline', 'short_copy', 'ends_at']);

        $this->assertDatabaseCount('marketing_featured_products', 0);
    }

    public function test_marketing_copy_rejects_html_instead_of_sanitizing_it(): void
    {
        $admin = User::factory()->administrator()->create();
        $product = $this->product();

        $this->actingAs($admin)->post(route('admin.marketing.featured-products.store'), $this->payload($product, [
            'headline' => '<strong>Recomendación</strong>',
            'short_copy' => '<script>alert("x")</script>',
        ]))->assertSessionHasErrors([
            'headline' => 'El campo headline debe contener texto plano, sin HTML.',
            'short_copy' => 'El campo short copy debe contener texto plano, sin HTML.',
        ]);

        $this->assertDatabaseCount('marketing_featured_products', 0);
    }
}
