<?php

namespace Tests\Feature\Inventory;

use App\Modules\Identity\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class InventoryBusinessDateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 02:30 UTC on 16 October is still 23:30 on 15 October in Buenos Aires.
     */
    private const LATE_EVENING_UTC = '2026-10-16 02:30:00';

    public function test_adjustment_forms_default_to_the_business_date(): void
    {
        $this->travelTo(CarbonImmutable::parse(self::LATE_EVENING_UTC, 'UTC'));

        $this->actingAs(User::factory()->administrator()->create())
            ->get('/admin/inventory/adjustments')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('business_today', '2026-10-15'));
    }

    public function test_production_forms_default_to_the_business_date(): void
    {
        $this->travelTo(CarbonImmutable::parse(self::LATE_EVENING_UTC, 'UTC'));

        $this->actingAs(User::factory()->administrator()->create())
            ->get('/admin/inventory/production/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('business_today', '2026-10-15'));
    }
}
