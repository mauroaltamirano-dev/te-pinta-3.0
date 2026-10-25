<?php

namespace Tests\Feature\Orders;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\OrderSettingKey;
use App\Modules\Orders\Models\OrderSetting;
use App\Modules\Orders\Models\PublicOrderingHour;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class PublicOrderingHoursTest extends TestCase
{
    use RefreshDatabase;

    private const ZONE = 'America/Argentina/Buenos_Aires';

    protected function setUp(): void
    {
        parent::setUp();
        $category = Category::query()->create(['name' => 'Empanadas', 'slug' => 'empanadas', 'sort_order' => 1, 'is_active' => true]);
        Product::query()->create([
            'category_id' => $category->id, 'name' => 'Salteña', 'slug' => 'saltena',
            'unit_price' => 1800, 'half_dozen_price' => 10000, 'dozen_price' => 18000,
            'is_available' => true, 'sort_order' => 1,
        ]);
        OrderSetting::query()->where('key', OrderSettingKey::WhatsappOrderPhone->value)->update(['string_value' => '5491112345678']);
    }

    public function test_every_day_is_seeded_from_seven_pm_to_midnight(): void
    {
        $hours = PublicOrderingHour::query()->orderBy('weekday')->get()
            ->map(fn (PublicOrderingHour $hour): array => [$hour->weekday, $hour->opens_at, $hour->closes_at])->all();

        $this->assertSame([
            [1, '19:00', '00:00'], [2, '19:00', '00:00'], [3, '19:00', '00:00'], [4, '19:00', '00:00'],
            [5, '19:00', '00:00'], [6, '19:00', '00:00'], [7, '19:00', '00:00'],
        ], $hours);
    }

    public function test_an_order_inside_the_window_is_for_that_evening_without_a_customer_schedule(): void
    {
        $this->travelToLocal('2026-10-26 22:15'); // Monday

        $this->postJson(route('public.orders.store'), $this->payload())->assertCreated();

        $this->assertDatabaseHas('orders', ['delivery_date' => '2026-10-26', 'delivery_period' => 'night', 'delivery_time' => null]);
    }

    public function test_an_order_outside_the_window_is_rejected_with_the_next_opening(): void
    {
        $this->travelToLocal('2026-10-26 18:59');

        $this->postJson(route('public.orders.store'), $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ordering')
            ->assertJsonPath('errors.ordering.0', 'Ahora no estamos tomando pedidos por la web. Volvemos hoy a las 19:00.');
        $this->assertDatabaseCount('orders', 0);

        $this->travelToLocal('2026-10-27 00:00');
        $this->postJson(route('public.orders.store'), $this->payload())
            ->assertUnprocessable()
            ->assertJsonPath('errors.ordering.0', 'Ahora no estamos tomando pedidos por la web. Volvemos hoy a las 19:00.');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_a_window_past_midnight_belongs_to_the_day_it_opened(): void
    {
        PublicOrderingHour::query()->where('weekday', 1)->update(['opens_at' => '11:30', 'closes_at' => '01:00']);
        $this->travelToLocal('2026-10-27 00:30'); // Tuesday, inside Monday's window

        $this->postJson(route('public.orders.store'), $this->payload())->assertCreated();

        $this->assertDatabaseHas('orders', ['delivery_date' => '2026-10-26', 'delivery_period' => 'midday']);
    }

    public function test_equal_opening_and_closing_times_take_orders_all_day(): void
    {
        PublicOrderingHour::query()->where('weekday', 1)->update(['opens_at' => '00:00', 'closes_at' => '00:00']);
        $this->travelToLocal('2026-10-26 16:00');

        $this->postJson(route('public.orders.store'), $this->payload())->assertCreated();

        $this->assertDatabaseHas('orders', ['delivery_date' => '2026-10-26']);
    }

    public function test_closed_days_are_skipped_when_announcing_the_next_opening(): void
    {
        PublicOrderingHour::query()->whereIn('weekday', [2, 3])->update(['opens_at' => null, 'closes_at' => null]);
        $this->travelToLocal('2026-10-27 20:00'); // Tuesday, closed

        $this->postJson(route('public.orders.store'), $this->payload())
            ->assertUnprocessable()
            ->assertJsonPath('errors.ordering.0', 'Ahora no estamos tomando pedidos por la web. Volvemos el jueves a las 19:00.');
        $this->getJson(route('public.orders.options'))->assertOk()
            ->assertJsonPath('data.ordering.is_open', false)
            ->assertJsonPath('data.ordering.message', 'Ahora no estamos tomando pedidos por la web. Volvemos el jueves a las 19:00.');

        PublicOrderingHour::query()->update(['opens_at' => null, 'closes_at' => null]);
        $this->getJson(route('public.orders.options'))
            ->assertJsonPath('data.ordering.message', 'Ahora no estamos tomando pedidos por la web.');
    }

    public function test_options_report_an_open_window_with_its_closing_time(): void
    {
        $this->travelToLocal('2026-10-26 21:00');

        $this->getJson(route('public.orders.options'))->assertOk()
            ->assertJsonPath('data.ordering.is_open', true)
            ->assertJsonPath('data.ordering.message', 'Tomamos pedidos hasta las 00:00.');
    }

    public function test_public_checkout_no_longer_accepts_a_customer_schedule(): void
    {
        $this->travelToLocal('2026-10-26 21:00');

        $this->postJson(route('public.orders.store'), $this->payload() + [
            'delivery_date' => '2026-10-26', 'delivery_period' => 'night', 'delivery_time' => '21:30',
        ])->assertUnprocessable()->assertJsonValidationErrors(['delivery_date', 'delivery_period', 'delivery_time']);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_an_administrator_edits_the_weekly_hours(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->withoutVite();

        $this->actingAs($admin)->get(route('admin.settings.general.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('orderingHours', 7)
                ->where('orderingHours.0', ['weekday' => 1, 'opens_at' => '19:00', 'closes_at' => '00:00']));

        $hours = $this->weekPayload();
        $hours[1] = ['weekday' => 2, 'opens_at' => null, 'closes_at' => null];
        $hours[5] = ['weekday' => 6, 'opens_at' => '11:30', 'closes_at' => '01:30'];

        $this->actingAs($admin)->put(route('admin.settings.general.ordering-hours.update'), ['hours' => $hours])
            ->assertRedirect(route('admin.settings.general.index'))
            ->assertSessionHas('success', 'Horario de pedidos web actualizado.');

        $this->assertDatabaseHas('public_ordering_hours', ['weekday' => 2, 'opens_at' => null, 'closes_at' => null]);
        $this->assertDatabaseHas('public_ordering_hours', ['weekday' => 6, 'opens_at' => '11:30:00', 'closes_at' => '01:30:00']);
        $this->assertDatabaseHas('public_ordering_hours', ['weekday' => 1, 'opens_at' => '19:00:00', 'closes_at' => '00:00:00']);
    }

    public function test_weekly_hours_need_both_times_or_none_and_every_day_once(): void
    {
        $admin = User::factory()->administrator()->create();

        $hours = $this->weekPayload();
        $hours[0] = ['weekday' => 1, 'opens_at' => '19:00', 'closes_at' => null];
        $hours[1] = ['weekday' => 2, 'opens_at' => '25:00', 'closes_at' => '23:00'];
        $this->actingAs($admin)->put(route('admin.settings.general.ordering-hours.update'), ['hours' => $hours])
            ->assertSessionHasErrors(['hours.0.closes_at', 'hours.1.opens_at']);

        $hours = $this->weekPayload();
        $hours[6]['weekday'] = 1;
        $this->actingAs($admin)->put(route('admin.settings.general.ordering-hours.update'), ['hours' => $hours])
            ->assertSessionHasErrors('hours.6.weekday');

        $this->assertDatabaseMissing('public_ordering_hours', ['closes_at' => null, 'weekday' => 1]);
        $this->assertSame(7, PublicOrderingHour::query()->where('opens_at', '19:00')->count());
    }

    public function test_only_catalog_managers_edit_the_weekly_hours(): void
    {
        $this->put(route('admin.settings.general.ordering-hours.update'), ['hours' => $this->weekPayload()])
            ->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())
            ->put(route('admin.settings.general.ordering-hours.update'), ['hours' => $this->weekPayload()])
            ->assertForbidden();
    }

    private function travelToLocal(string $localTime): void
    {
        $this->travelTo(CarbonImmutable::parse($localTime, self::ZONE));
    }

    /** @return list<array{weekday: int, opens_at: ?string, closes_at: ?string}> */
    private function weekPayload(): array
    {
        return array_map(fn (int $day): array => ['weekday' => $day, 'opens_at' => '19:00', 'closes_at' => '00:00'], range(1, 7));
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'items' => [['product_slug' => 'saltena', 'quantity' => 6]],
            'addons' => [], 'condition' => 'raw', 'delivery_type' => 'pickup',
            'delivery_address' => null, 'notes' => null,
            'customer' => ['name' => 'Visitor name', 'phone' => '+54 9 11 1234-5678', 'address' => null],
            'idempotency_key' => 'ordering-hours-key-0001',
        ];
    }
}
