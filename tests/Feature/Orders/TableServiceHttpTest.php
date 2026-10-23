<?php

namespace Tests\Feature\Orders;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Actions\ConfigureProductInventoryConsumption;
use App\Modules\Management\Models\Wallet;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Enums\TableSessionStatus;
use App\Modules\Orders\Models\TableSession;
use App\Modules\Printing\Models\PrintJob;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Pricing\EmpanadaUnitPrice;
use App\Modules\Restaurant\Models\DiningArea;
use App\Modules\Restaurant\Models\DiningTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** The waiter and cashier screens over HTTP: tables → table → round → bill → charge. */
final class TableServiceHttpTest extends TestCase
{
    use RefreshDatabase;

    private Product $salteña;

    private Product $beer;

    private DiningArea $area;

    private DiningTable $table;

    private User $waiter;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $empanadas = Category::query()->create(['name' => 'Empanadas', 'slug' => 'empanadas', 'sort_order' => 1, 'is_active' => true]);
        $this->salteña = Product::query()->create([
            'category_id' => $empanadas->id, 'name' => 'Salteña', 'slug' => 'saltena', 'pricing_family' => 'empanada',
            'unit_price' => EmpanadaUnitPrice::fromDozen(18000, 10), 'half_dozen_price' => 6 * 1700, 'dozen_price' => 18000,
            'is_available' => true, 'sort_order' => 1,
        ]);
        $this->beer = Product::query()->create([
            'category_id' => $empanadas->id, 'name' => 'Cerveza', 'slug' => 'cerveza', 'pricing_family' => 'unit',
            'unit_price' => 4000, 'half_dozen_price' => 4000, 'dozen_price' => 4000, 'is_available' => true, 'sort_order' => 2,
        ]);
        $this->area = DiningArea::query()->create(['name' => 'Vereda', 'width' => 1600, 'height' => 1000, 'is_active' => true]);
        $this->table = $this->addTable('M1');
        $this->waiter = User::factory()->withRole(Role::Waiter)->create(['name' => 'Franco']);
        $this->cashier = User::factory()->withRole(Role::Cashier)->create(['name' => 'Lu']);
    }

    public function test_the_board_lists_active_tables_by_code_and_keeps_a_deactivated_table_while_its_session_is_open(): void
    {
        $this->addTable('M10');
        $this->addTable('M2');
        $retired = $this->addTable('M3');
        $this->addTable('M4', active: false);
        $this->round($this->waiter, $retired, [$this->beer->id => 1]);
        $retired->update(['is_active' => false]);

        $this->actingAs($this->waiter)->get(route('admin.tables.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/tables/index')
                ->where('tables', fn ($tables): bool => collect($tables)->pluck('code')->all() === ['M1', 'M2', 'M3', 'M10'])
                ->where('tables.2.available', false)
                ->where('tables.2.session.waiter', 'Franco')
                ->where('tables.2.session.mine', true)
                ->where('tables.2.session.total', 4000)
                ->where('tables.2.session.opened_at', now()->setTimezone((string) config('orders.timezone'))->format('H:i'))
                ->where('tables.0.session', null));
    }

    public function test_the_board_draws_the_floor_of_the_listed_tables_without_changing_it(): void
    {
        $this->area->update(['obstacles' => [['type' => 'rectangle', 'x' => '0.0500', 'y' => '0.8000', 'width' => '0.4000', 'height' => '0.1200', 'label' => 'Barra']]]);
        $this->table->update(['shape' => 'circle', 'position_x' => '0.2000', 'position_y' => '0.3000', 'width' => '0.0625', 'height' => '0.1000']);
        $this->addTable('M4', active: false);
        $patio = DiningArea::query()->create(['name' => 'Patio', 'width' => 1200, 'height' => 600, 'is_active' => true]);
        $kept = $patio->tables()->create([
            'code' => 'P1', 'shape' => 'rectangle', 'position_x' => '0.5000', 'position_y' => '0.2500',
            'width' => '0.1000', 'height' => '0.2000', 'rotation' => 45, 'is_active' => true,
        ]);
        $this->round($this->waiter, $kept, [$this->beer->id => 1]);
        $patio->update(['is_active' => false]);
        DiningArea::query()->create(['name' => 'Terraza', 'width' => 800, 'height' => 800, 'is_active' => true])->tables()->create([
            'code' => 'T1', 'shape' => 'rectangle', 'position_x' => '0.1000', 'position_y' => '0.1000', 'width' => '0.1000', 'height' => '0.1000', 'rotation' => 0, 'is_active' => false,
        ]);
        $layout = DB::table('dining_areas')->orderBy('id')->get(['id', 'layout_version', 'updated_at'])->all();

        $this->actingAs($this->waiter)->get(route('admin.tables.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/tables/index')
                ->where('tables', fn ($tables): bool => collect($tables)->pluck('code')->all() === ['M1', 'P1'])
                // Only areas with a listed table: the empty Terraza is left out, the deactivated Patio keeps its open table.
                ->where('areas', [
                    [
                        'id' => $this->area->id, 'name' => 'Vereda', 'width' => 1600, 'height' => 1000,
                        'obstacles' => [['type' => 'rectangle', 'x' => '0.0500', 'y' => '0.8000', 'width' => '0.4000', 'height' => '0.1200', 'label' => 'Barra']],
                        'tables' => [['id' => $this->table->id, 'shape' => 'circle', 'x' => '0.2000', 'y' => '0.3000', 'width' => '0.0625', 'height' => '0.1000', 'rotation' => 0]],
                    ],
                    [
                        'id' => $patio->id, 'name' => 'Patio', 'width' => 1200, 'height' => 600, 'obstacles' => [],
                        'tables' => [['id' => $kept->id, 'shape' => 'rectangle', 'x' => '0.5000', 'y' => '0.2500', 'width' => '0.1000', 'height' => '0.2000', 'rotation' => 45]],
                    ],
                ]));
        $this->assertEquals($layout, DB::table('dining_areas')->orderBy('id')->get(['id', 'layout_version', 'updated_at'])->all());
    }

    public function test_a_waiter_opens_a_table_and_six_plus_six_salteñas_cost_one_dozen(): void
    {
        $this->round($this->waiter, $this->table, [$this->salteña->id => 6])->assertRedirect(route('admin.tables.show', $this->table));
        $session = TableSession::query()->sole();
        $this->round($this->waiter, $this->table, [$this->salteña->id => 6], session: $session->id)->assertSessionHasNoErrors();

        $this->get(route('admin.tables.show', $this->table))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/tables/show')
                ->where('session.total', 18000)
                ->where('session.can_act', true)
                ->where('session.rounds', fn ($rounds): bool => collect($rounds)->pluck('sequence')->all() === [1, 2])
                ->where('session.rounds.0.items', [['name' => 'Salteña', 'quantity' => 6]])
                ->where('session.lines', [['product_id' => $this->salteña->id, 'addon_id' => null, 'name' => 'Salteña', 'quantity' => 12]])
                ->where('can_cashier', false)
                ->where('missing_consumption', [])
                ->has('products', 2));
        $this->assertSame(2, PrintJob::query()->where('kind', 'kitchen_ticket')->count());
    }

    public function test_another_waiter_cannot_act_on_the_table_but_a_cashier_adds_a_marked_round(): void
    {
        $this->round($this->waiter, $this->table, [$this->salteña->id => 6]);
        $session = TableSession::query()->sole();
        $other = User::factory()->withRole(Role::Waiter)->create();

        $this->actingAs($other)->get(route('admin.tables.show', $this->table))
            ->assertInertia(fn (Assert $page) => $page->where('session.can_act', false)->where('session.mine', false));
        $this->round($other, $this->table, [$this->beer->id => 1], session: $session->id)->assertForbidden();

        $this->round($this->cashier, $this->table, [$this->beer->id => 1], session: $session->id)->assertSessionHasNoErrors();
        $this->actingAs($this->waiter)->get(route('admin.tables.show', $this->table))
            ->assertInertia(fn (Assert $page) => $page
                ->where('session.rounds.1.added_by', 'Cajero Lu')
                ->where('session.rounds.0.added_by', null));
    }

    public function test_a_stale_screen_cannot_open_a_table_someone_else_just_opened(): void
    {
        $this->round($this->waiter, $this->table, [$this->salteña->id => 6]);

        $this->round($this->cashier, $this->table, [$this->beer->id => 1])->assertSessionHasErrors('table');
        $this->assertSame(1, TableSession::query()->sole()->rounds()->count());
    }

    public function test_a_void_round_subtracts_and_cannot_go_below_zero(): void
    {
        $this->round($this->waiter, $this->table, [$this->beer->id => 2]);
        $session = TableSession::query()->sole();

        $this->round($this->waiter, $this->table, [$this->beer->id => 3], session: $session->id, kind: 'void')->assertSessionHasErrors();
        $this->round($this->waiter, $this->table, [$this->beer->id => 1], session: $session->id, kind: 'void')->assertSessionHasNoErrors();

        $this->get(route('admin.tables.show', $this->table))
            ->assertInertia(fn (Assert $page) => $page->where('session.total', 4000)->where('session.rounds.1.kind', 'void'));
    }

    public function test_the_waiter_requests_the_bill_and_only_a_cashier_role_prints_and_charges_it(): void
    {
        $this->round($this->waiter, $this->table, [$this->beer->id => 1]);
        $session = TableSession::query()->sole();

        $this->actingAs($this->waiter)->post(route('admin.table-sessions.bill-request', $session))->assertRedirect();
        $this->post(route('admin.table-sessions.bill', $session))->assertForbidden();
        $this->post(route('admin.table-sessions.charge', $session), ['payment_method' => 'cash'])->assertForbidden();
        $this->get(route('admin.tables.index'))->assertInertia(fn (Assert $page) => $page->where('tables.0.session.bill_requested', true));

        $this->actingAs($this->cashier)->get(route('admin.tables.show', $this->table))
            ->assertInertia(fn (Assert $page) => $page->where('can_cashier', true)->where('missing_consumption', ['Cerveza']));
        $this->post(route('admin.table-sessions.bill', $session))->assertRedirect();
        $this->assertSame(1, PrintJob::query()->where('kind', 'bill')->count());

        // Without consumption configuration the charge is refused and the table stays open.
        Wallet::query()->create(['name' => 'Caja', 'is_order_payment_default' => true]);
        $this->post(route('admin.table-sessions.charge', $session), ['payment_method' => 'cash'])->assertSessionHasErrors('status');
        $this->assertSame(TableSessionStatus::Open, $session->refresh()->status);
        app(ConfigureProductInventoryConsumption::class)->execute($this->beer->id, 'no_inventory', [], User::factory()->administrator()->create()->id);
        $this->actingAs($this->cashier)->get(route('admin.tables.show', $this->table))
            ->assertInertia(fn (Assert $page) => $page->where('missing_consumption', []));

        $this->post(route('admin.table-sessions.charge', $session), ['payment_method' => 'teleport'])->assertSessionHasErrors('payment_method');
        $this->post(route('admin.table-sessions.charge', $session), ['payment_method' => 'cash'])->assertRedirect(route('admin.tables.index'));
        $session->refresh();
        $this->assertSame(TableSessionStatus::Closed, $session->status);
        $this->assertSame([PaymentStatus::Paid, OrderStatus::Delivered, 4000], [$session->order->payment_status, $session->order->status, (int) $session->order->total]);
        $this->get(route('admin.tables.index'))->assertInertia(fn (Assert $page) => $page->where('tables.0.session', null));

        // S20: the charged session rejects a round from the screen that still shows it.
        $this->round($this->waiter, $this->table, [$this->beer->id => 1], session: $session->id)->assertSessionHasErrors('table');
    }

    public function test_a_cashier_role_cancels_an_open_table(): void
    {
        $this->round($this->waiter, $this->table, [$this->beer->id => 1]);
        $session = TableSession::query()->sole();

        $this->actingAs($this->waiter)->post(route('admin.table-sessions.cancel', $session))->assertForbidden();
        $this->actingAs($this->cashier)->post(route('admin.table-sessions.cancel', $session))->assertRedirect(route('admin.tables.index'));
        $this->assertSame([TableSessionStatus::Cancelled, OrderStatus::Cancelled], [$session->refresh()->status, $session->order->status]);
    }

    public function test_the_cashier_is_warned_about_a_table_product_that_became_unavailable(): void
    {
        $this->round($this->waiter, $this->table, [$this->beer->id => 1]);
        $this->beer->update(['is_available' => false]);
        $this->beer->delete();

        $this->actingAs($this->cashier)->get(route('admin.tables.show', $this->table))
            ->assertInertia(fn (Assert $page) => $page->where('missing_consumption', ['Cerveza']));
    }

    public function test_an_unknown_table_is_not_found_and_an_empty_round_is_rejected(): void
    {
        $this->actingAs($this->waiter)->get(route('admin.tables.show', 999999))->assertNotFound();
        $this->round($this->waiter, $this->table, [])->assertSessionHasErrors('items');
        $this->assertSame(0, TableSession::query()->count());
    }

    public function test_members_without_order_management_cannot_open_the_tables(): void
    {
        $kitchen = User::factory()->withRole(Role::Kitchen)->create();

        $this->actingAs($kitchen)->get(route('admin.tables.index'))->assertForbidden();
        $this->round($kitchen, $this->table, [$this->beer->id => 1])->assertForbidden();
    }

    private function addTable(string $code, bool $active = true): DiningTable
    {
        return $this->area->tables()->create([
            'code' => $code, 'shape' => 'rectangle', 'position_x' => '0.1000', 'position_y' => '0.1000',
            'width' => '0.0500', 'height' => '0.0500', 'rotation' => 0, 'is_active' => $active,
        ]);
    }

    /** @param array<int, int> $items */
    private function round(User $actor, DiningTable $table, array $items, ?int $session = null, string $kind = 'order'): TestResponse
    {
        return $this->actingAs($actor)->post(route('admin.tables.rounds.store', $table), [
            'expected_session_id' => $session, 'kind' => $kind, 'note' => null, 'round_key' => (string) Str::uuid(), 'addons' => [],
            'items' => array_map(fn (int $id, int $quantity): array => ['product_id' => $id, 'quantity' => $quantity], array_keys($items), $items),
        ]);
    }
}
