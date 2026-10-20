<?php

namespace Tests\Feature\Orders;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Actions\ConfigureProductInventoryConsumption;
use App\Modules\Inventory\Queries\ProductsWithoutConsumption;
use App\Modules\Management\Models\ManagementOrderPayment;
use App\Modules\Management\Models\Wallet;
use App\Modules\Orders\Actions\CancelTableSession;
use App\Modules\Orders\Actions\ChargeTableSession;
use App\Modules\Orders\Actions\ConfirmTableRound;
use App\Modules\Orders\Actions\CreateOrder;
use App\Modules\Orders\Actions\IssueTableBill;
use App\Modules\Orders\Actions\RequestTableBill;
use App\Modules\Orders\Actions\UpdateOrderPaymentStatus;
use App\Modules\Orders\Actions\UpdateOrderStatus;
use App\Modules\Orders\Data\OrderCheckoutData;
use App\Modules\Orders\Enums\DeliveryType;
use App\Modules\Orders\Enums\OrderCondition;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentMethod;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Enums\TableRoundKind;
use App\Modules\Orders\Enums\TableSessionStatus;
use App\Modules\Orders\Events\PrintableDocumentIssued;
use App\Modules\Orders\Models\OrderAddon;
use App\Modules\Orders\Models\TableRound;
use App\Modules\Orders\Models\TableSession;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Pricing\EmpanadaUnitPrice;
use App\Modules\Restaurant\Models\DiningArea;
use App\Modules\Restaurant\Models\DiningTable;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class TableServiceTest extends TestCase
{
    use RefreshDatabase;

    private Product $salteña;

    private Product $coke;

    private DiningTable $table;

    private User $waiter;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([PrintableDocumentIssued::class]);
        $empanadas = Category::query()->create(['name' => 'Empanadas', 'slug' => 'empanadas', 'sort_order' => 1, 'is_active' => true]);
        $drinks = Category::query()->create(['name' => 'Bebidas', 'slug' => 'bebidas', 'sort_order' => 2, 'is_active' => true]);
        $this->salteña = Product::query()->create([
            'category_id' => $empanadas->id, 'name' => 'Salteña', 'slug' => 'saltena', 'pricing_family' => 'empanada',
            'unit_price' => EmpanadaUnitPrice::fromDozen(18000, 10), 'half_dozen_price' => 6 * 1700, 'dozen_price' => 18000,
            'is_available' => true, 'sort_order' => 1,
        ]);
        $this->coke = Product::query()->create([
            'category_id' => $drinks->id, 'name' => 'Coca-Cola', 'slug' => 'coca-cola', 'pricing_family' => 'unit',
            'unit_price' => 2500, 'half_dozen_price' => 2500, 'dozen_price' => 2500, 'is_available' => true, 'sort_order' => 1,
        ]);
        $area = DiningArea::query()->create(['name' => 'Vereda', 'width' => 1600, 'height' => 1000, 'is_active' => true]);
        $this->table = $area->tables()->create([
            'code' => 'M1', 'shape' => 'rectangle', 'position_x' => '0.1000', 'position_y' => '0.1000',
            'width' => '0.1000', 'height' => '0.1000', 'rotation' => 0, 'is_active' => true,
        ]);
        $this->waiter = User::factory()->withRole(Role::Waiter)->create(['name' => 'Franco']);
    }

    public function test_six_salteñas_in_one_round_and_six_in_another_are_priced_as_one_dozen(): void
    {
        $this->round([$this->salteña->id => 6]);
        $order = $this->round([$this->salteña->id => 6])->session->order->refresh();

        $this->assertSame(18000, $order->total);
        $this->assertSame([['dozen', 1, 12, 18000]], $order->items->map(fn ($item): array => [$item->pricing_mode->value, $item->quantity, $item->units_count, $item->line_total])->all());
        $this->assertSame(OrderSource::Pos, $order->source);
        $this->assertSame(DeliveryType::DineIn, $order->delivery_type);
        $this->assertSame(OrderStatus::Waiting, $order->status);
        $this->assertSame(PaymentStatus::Unpaid, $order->payment_status);
        $this->assertNull($order->customer_id);
        $this->assertSame(2, TableRound::query()->count());
    }

    public function test_the_provisional_total_follows_every_round_and_a_partial_first_round_uses_unit_prices(): void
    {
        $order = $this->round([$this->salteña->id => 6, $this->coke->id => 1])->session->order;
        $this->assertSame(6 * 1700 + 2500, $order->refresh()->total);

        $this->round([$this->salteña->id => 7, $this->coke->id => 1]);
        // 13 salteñas = 1 dozen + 1 unit; 2 cokes.
        $this->assertSame(18000 + 1700 + 5000, $order->refresh()->total);
        $this->assertSame(1, $order->revisions()->count());
    }

    public function test_each_round_issues_one_kitchen_ticket_with_only_its_own_lines_and_no_prices(): void
    {
        $this->round([$this->salteña->id => 6, $this->coke->id => 1]);
        $second = $this->round([$this->coke->id => 1], note: 'Sin hielo');

        $tickets = $this->issued('kitchen_ticket');
        $this->assertCount(2, $tickets);
        $last = $tickets[1];
        $this->assertSame("table-round:{$second->id}", $last->dedupeKey);
        $this->assertSame('Mesa M1', $last->payload['title']);
        $this->assertSame(2, $last->payload['round']);
        $this->assertFalse($last->payload['void']);
        $this->assertSame('Franco', $last->payload['waiter']);
        $this->assertNull($last->payload['added_by']);
        $this->assertSame('Sin hielo', $last->payload['note']);
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $last->payload['time']);
        $this->assertSame([['name' => 'Coca-Cola', 'quantity' => 1]], $last->payload['lines']);
        $this->assertStringNotContainsString('2500', json_encode($last->payload, JSON_THROW_ON_ERROR));
        $this->assertSame([['name' => 'Salteña', 'quantity' => 6], ['name' => 'Coca-Cola', 'quantity' => 1]], $tickets[0]->payload['lines']);
    }

    public function test_confirmed_rounds_and_their_lines_cannot_be_changed_or_deleted(): void
    {
        $round = $this->round([$this->coke->id => 1]);

        foreach (['update table_rounds set note = ? where id = ?' => ['x', $round->id], 'delete from table_round_items where table_round_id = ?' => [$round->id]] as $sql => $bindings) {
            try {
                DB::transaction(fn () => DB::statement($sql, $bindings));
                $this->fail('Mutation should be rejected: '.$sql);
            } catch (QueryException $exception) {
                $this->assertStringContainsString('immutable', $exception->getMessage());
            }
        }
    }

    public function test_a_void_round_subtracts_and_prints_a_void_ticket_but_cannot_exceed_or_empty_the_table(): void
    {
        $this->round([$this->coke->id => 2, $this->salteña->id => 6]);
        $void = $this->round([$this->coke->id => 1], kind: TableRoundKind::Void);

        $this->assertSame(2500 + 6 * 1700, $void->session->order->refresh()->total);
        $ticket = $this->issued('kitchen_ticket')[1];
        $this->assertTrue($ticket->payload['void']);
        $this->assertSame([['name' => 'Coca-Cola', 'quantity' => 1]], $ticket->payload['lines']);

        $this->assertRejected(fn () => $this->round([$this->coke->id => 2], kind: TableRoundKind::Void), 'items');
        $this->assertRejected(fn () => $this->round([$this->coke->id => 1, $this->salteña->id => 6], kind: TableRoundKind::Void), 'items');
        $this->assertSame(2, TableRound::query()->count());
    }

    public function test_a_void_round_needs_an_open_table(): void
    {
        $this->assertRejected(fn () => $this->round([$this->coke->id => 1], kind: TableRoundKind::Void), 'table');
        $this->assertSame(0, TableSession::query()->count());
    }

    public function test_one_open_session_per_table_owned_by_its_waiter(): void
    {
        $first = $this->round([$this->coke->id => 1]);
        $second = $this->round([$this->coke->id => 1]);
        $this->assertSame($first->table_session_id, $second->table_session_id);
        $this->assertSame($this->waiter->id, $first->session->waiter_id);

        $otherWaiter = User::factory()->withRole(Role::Waiter)->create();
        $this->assertForbidden(fn () => $this->round([$this->coke->id => 1], actor: $otherWaiter));
        $this->assertForbidden(fn () => app(RequestTableBill::class)->execute($first->session, $otherWaiter));
        $this->assertSame(1, TableSession::query()->count());

        // The database enforces it too: another order cannot open a second session on the same table.
        $otherOrder = app(CreateOrder::class)->execute(new OrderCheckoutData(
            items: [['product_id' => $this->coke->id, 'quantity' => 1]], addons: [], condition: OrderCondition::Cooked, deliveryType: DeliveryType::DineIn,
            deliveryPeriod: null, deliveryDate: null, deliveryTime: null, deliveryAddress: null, notes: null,
            customerId: null, customerName: null, customerPhone: null, customerPhoneNormalized: null, customerAddress: null,
            paymentStatus: PaymentStatus::Unpaid,
        ), OrderSource::Pos, $otherWaiter->id);
        try {
            DB::transaction(fn () => TableSession::query()->create([
                'dining_table_id' => $this->table->id, 'order_id' => $otherOrder->id, 'waiter_id' => $otherWaiter->id, 'opened_at' => now(),
            ]));
            $this->fail('A second open session must be rejected by the database.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('table_sessions_one_open_per_table', $exception->getMessage());
        }
    }

    public function test_owner_manager_and_cashier_can_add_to_a_waiters_table_and_the_ticket_says_who_added_it(): void
    {
        $this->round([$this->coke->id => 1]);
        foreach ([[Role::Cashier, 'Cajero Lu'], [Role::Manager, 'Encargado Ana'], [Role::Owner, 'Dueño Mauro']] as [$role, $expected]) {
            $actor = User::factory()->withRole($role)->create(['name' => explode(' ', $expected)[1]]);
            $round = $this->round([$this->coke->id => 1], actor: $actor);
            $this->assertSame($this->waiter->id, $round->session->waiter_id);
            $this->assertSame($role, $round->confirmed_by_role);
            $ticket = collect($this->issued('kitchen_ticket'))->last();
            $this->assertSame('Franco', $ticket->payload['waiter']);
            $this->assertSame($expected, $ticket->payload['added_by']);
        }
    }

    public function test_members_without_order_management_cannot_take_tables(): void
    {
        foreach ([Role::Kitchen, Role::Accountant] as $role) {
            $this->assertForbidden(fn () => $this->round([$this->coke->id => 1], actor: User::factory()->withRole($role)->create()));
        }
        $this->assertSame(0, TableSession::query()->count());
    }

    public function test_a_repeated_round_key_returns_the_same_round_and_a_reused_key_with_other_lines_fails(): void
    {
        $key = (string) Str::uuid();
        $first = $this->round([$this->coke->id => 1], key: $key);
        $replay = $this->round([$this->coke->id => 1], key: $key);

        $this->assertSame($first->id, $replay->id);
        $this->assertCount(1, $this->issued('kitchen_ticket'));
        $this->assertSame(2500, $first->session->order->refresh()->total);
        $this->assertRejected(fn () => $this->round([$this->coke->id => 2], key: $key), 'round_key');
    }

    public function test_a_retry_after_the_screen_refreshed_replays_the_round_instead_of_failing(): void
    {
        $key = (string) Str::uuid();
        $first = $this->round([$this->coke->id => 1], key: $key, session: null);
        // The phone timed out, refreshed (now it sees the open session) and sent the same round again.
        $retry = $this->round([$this->coke->id => 1], key: $key, session: $first->table_session_id);

        $this->assertSame($first->id, $retry->id);
        $this->assertSame(1, TableRound::query()->count());
    }

    public function test_a_concurrent_duplicate_of_the_first_round_waits_and_replays(): void
    {
        $key = (string) Str::uuid();
        $first = $this->round([$this->coke->id => 1], key: $key, session: null);
        // Same request as the first one, decided after the first committed (double tap on a free table).
        $duplicate = (new \ReflectionMethod(ConfirmTableRound::class, 'confirm'))->invoke(
            app(ConfirmTableRound::class), $this->table->id, null, TableRoundKind::Order, [$this->coke->id => 1], [], null,
            $first->idempotency_hash, $first->fingerprint, $this->waiter,
        );

        $this->assertSame($first->id, $duplicate->id);
        $this->assertSame(1, TableRound::query()->count());
        $this->assertCount(1, $this->issued('kitchen_ticket'));
    }

    public function test_tickets_print_the_confirmation_time_in_the_business_time_zone(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-31 00:45:00', 'UTC'));
        $this->round([$this->coke->id => 1]);

        $ticket = $this->issued('kitchen_ticket')[0]->payload;
        $this->assertSame(['2026-10-30', '21:45'], [$ticket['date'], $ticket['time']]);
    }

    public function test_an_inactive_table_cannot_be_opened_but_an_open_one_keeps_working_after_deactivation(): void
    {
        $this->table->update(['is_active' => false]);
        $this->assertRejected(fn () => $this->round([$this->coke->id => 1]), 'table');

        $this->table->update(['is_active' => true]);
        $this->round([$this->coke->id => 1]);
        $this->table->update(['is_active' => false]);
        $this->assertSame(5000, $this->round([$this->coke->id => 1])->session->order->refresh()->total);
    }

    public function test_requesting_the_bill_flags_the_table_and_a_new_round_clears_it(): void
    {
        $session = $this->round([$this->coke->id => 1])->session;
        app(RequestTableBill::class)->execute($session, $this->waiter);
        $this->assertNotNull($session->refresh()->bill_requested_at);
        $this->assertSame($this->waiter->id, $session->bill_requested_by);
        $this->assertCount(0, $this->issued('bill'));

        $this->round([$this->coke->id => 1]);
        $this->assertNull($session->refresh()->bill_requested_at);
    }

    public function test_only_cashier_roles_print_the_bill_with_prices_once_per_order_version(): void
    {
        $session = $this->round([$this->salteña->id => 6])->session;
        $this->round([$this->salteña->id => 6, $this->coke->id => 1]);
        $this->assertForbidden(fn () => app(IssueTableBill::class)->execute($session->refresh(), $this->waiter));

        $cashier = User::factory()->withRole(Role::Cashier)->create();
        app(IssueTableBill::class)->execute($session->refresh(), $cashier);
        app(IssueTableBill::class)->execute($session->refresh(), $cashier);
        $bills = $this->issued('bill');
        $this->assertCount(2, $bills);
        $this->assertSame($bills[0]->dedupeKey, $bills[1]->dedupeKey);
        $bill = $bills[0]->payload;
        $this->assertSame('Mesa M1', $bill['title']);
        $this->assertSame('Franco', $bill['waiter']);
        $this->assertSame(20500, $bill['total']);
        $this->assertSame('Documento no válido como factura', $bill['legend']);
        $this->assertSame([
            ['name' => 'Salteña', 'detail' => '1 docena', 'quantity' => 1, 'unit_price' => 18000, 'total' => 18000],
            ['name' => 'Coca-Cola', 'detail' => null, 'quantity' => 1, 'unit_price' => 2500, 'total' => 2500],
        ], $bill['lines']);

        $this->round([$this->coke->id => 1]);
        app(IssueTableBill::class)->execute($session->refresh(), $cashier);
        $this->assertNotSame($bills[0]->dedupeKey, collect($this->issued('bill'))->last()->dedupeKey);
    }

    public function test_charging_marks_paid_and_delivered_closes_the_table_and_prints_nothing(): void
    {
        $this->configureInventory();
        Wallet::query()->create(['name' => 'Caja', 'is_order_payment_default' => true]);
        $session = $this->round([$this->salteña->id => 12])->session;
        $this->assertForbidden(fn () => app(ChargeTableSession::class)->execute($session, PaymentMethod::Cash, $this->waiter));

        $cashier = User::factory()->withRole(Role::Cashier)->create();
        $printed = count(Event::dispatched(PrintableDocumentIssued::class));
        app(ChargeTableSession::class)->execute($session, PaymentMethod::Cash, $cashier);

        $order = $session->order->refresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(PaymentMethod::Cash, $order->payment_method);
        $this->assertSame(OrderStatus::Delivered, $order->status);
        $this->assertSame(18000, (int) ManagementOrderPayment::query()->where('order_id', $order->id)->value('amount'));
        $this->assertSame(TableSessionStatus::Closed, $session->refresh()->status);
        $this->assertSame($cashier->id, $session->closed_by);
        $this->assertCount($printed, Event::dispatched(PrintableDocumentIssued::class));
        $this->assertRejected(fn () => app(ChargeTableSession::class)->execute($session, PaymentMethod::Cash, $cashier), 'table');

        $next = $this->round([$this->coke->id => 1])->session;
        $this->assertNotSame($session->id, $next->id);
        $this->assertNotSame($order->id, $next->order_id);
    }

    public function test_a_charged_table_is_frozen_rounds_for_it_are_rejected_and_its_total_never_changes(): void
    {
        $this->configureInventory();
        Wallet::query()->create(['name' => 'Caja', 'is_order_payment_default' => true]);
        $session = $this->round([$this->salteña->id => 6])->session;
        $this->round([$this->salteña->id => 6]);
        app(ChargeTableSession::class)->execute($session->refresh(), PaymentMethod::Cash, User::factory()->withRole(Role::Cashier)->create());
        $order = $session->order->refresh();
        $snapshot = [$order->total, $order->edit_version, $order->items()->count(), TableRound::query()->count()];

        // A phone still showing the charged table cannot add to it or void from it.
        $this->assertRejected(fn () => $this->round([$this->salteña->id => 1], session: $session->id), 'table');
        $this->assertRejected(fn () => $this->round([$this->salteña->id => 1], kind: TableRoundKind::Void, session: $session->id), 'table');
        $this->assertForbidden(fn () => app(IssueTableBill::class)->execute($session->refresh(), $this->waiter));
        $this->assertRejected(fn () => app(IssueTableBill::class)->execute($session->refresh(), User::factory()->withRole(Role::Cashier)->create()), 'table');
        $this->assertRejected(fn () => app(RequestTableBill::class)->execute($session->refresh(), $this->waiter), 'table');

        // A later round on the same table opens a new session and leaves the charged order untouched.
        $this->assertNotSame($session->id, $this->round([$this->salteña->id => 1])->table_session_id);
        $order->refresh();
        $this->assertSame([18000, $snapshot[1], 1, $snapshot[3] + 1], [$order->total, $order->edit_version, $order->items()->count(), TableRound::query()->count()]);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
    }

    public function test_a_stale_screen_cannot_open_a_table_someone_else_just_opened(): void
    {
        $this->round([$this->coke->id => 1]);
        $this->assertRejected(fn () => $this->round([$this->coke->id => 1], session: null), 'table');
        $this->assertSame(1, TableRound::query()->count());
    }

    public function test_charging_without_inventory_configuration_changes_nothing(): void
    {
        Wallet::query()->create(['name' => 'Caja', 'is_order_payment_default' => true]);
        $session = $this->round([$this->coke->id => 1])->session;
        $owner = User::factory()->administrator()->create();

        $this->assertRejected(fn () => app(ChargeTableSession::class)->execute($session, PaymentMethod::Cash, $owner), 'status');
        $this->assertSame(TableSessionStatus::Open, $session->refresh()->status);
        $this->assertSame(PaymentStatus::Unpaid, $session->order->refresh()->payment_status);
        $this->assertSame(0, ManagementOrderPayment::query()->count());
        $this->assertSame(['Coca-Cola', 'Salteña'], app(ProductsWithoutConsumption::class)->names());
    }

    public function test_cancelling_a_table_cancels_its_order_and_frees_the_table(): void
    {
        $session = $this->round([$this->coke->id => 1])->session;
        $this->assertForbidden(fn () => app(CancelTableSession::class)->execute($session, $this->waiter));

        app(CancelTableSession::class)->execute($session, User::factory()->withRole(Role::Manager)->create());
        $this->assertSame(TableSessionStatus::Cancelled, $session->refresh()->status);
        $this->assertSame(OrderStatus::Cancelled, $session->order->refresh()->status);
        $this->assertNotSame($session->id, $this->round([$this->coke->id => 1])->table_session_id);
    }

    public function test_generic_order_status_and_payment_changes_are_refused_while_the_table_is_open(): void
    {
        $order = $this->round([$this->coke->id => 1])->session->order;
        $owner = User::factory()->administrator()->create();

        $this->assertRejected(fn () => app(UpdateOrderStatus::class)->execute($order, OrderStatus::Delivered, $owner->id), 'order');
        $this->assertRejected(fn () => app(UpdateOrderPaymentStatus::class)->execute($order, PaymentStatus::Paid, PaymentMethod::Cash, $owner->id), 'order');
        $this->assertSame(OrderStatus::Waiting, $order->refresh()->status);
    }

    public function test_a_charged_table_order_follows_the_regular_order_rules_again(): void
    {
        $this->configureInventory();
        Wallet::query()->create(['name' => 'Caja', 'is_order_payment_default' => true]);
        $session = $this->round([$this->coke->id => 1])->session;
        $owner = User::factory()->administrator()->create();
        app(ChargeTableSession::class)->execute($session, PaymentMethod::Cash, $owner);

        $this->assertSame(OrderStatus::Cancelled, app(UpdateOrderStatus::class)->execute($session->order, OrderStatus::Cancelled, $owner->id)->status);
    }

    public function test_an_open_table_order_cannot_be_sent_to_the_trash(): void
    {
        $order = $this->round([$this->coke->id => 1])->session->order;

        $this->actingAs(User::factory()->administrator()->create())
            ->delete(route('admin.orders.destroy', $order))
            ->assertSessionHasErrors('order');
        $this->assertNull($order->refresh()->deleted_at);
    }

    public function test_rounds_can_include_available_add_ons(): void
    {
        $addon = OrderAddon::query()->where('is_available', true)->orderBy('id')->firstOrFail();
        $round = $this->round([$this->salteña->id => 6], addons: [$addon->id => 2]);

        $this->assertSame(6 * 1700 + 2 * $addon->price, $round->session->order->refresh()->total);
        $this->assertSame([['name' => 'Salteña', 'quantity' => 6], ['name' => $addon->name, 'quantity' => 2]], $this->issued('kitchen_ticket')[0]->payload['lines']);
    }

    /**
     * @param  array<int, int>  $items
     * @param  array<int, int>  $addons
     */
    private function round(array $items, ?User $actor = null, TableRoundKind $kind = TableRoundKind::Order, array $addons = [], ?string $note = null, ?string $key = null, int|false|null $session = false): TableRound
    {
        $open = TableSession::query()->where('dining_table_id', $this->table->id)->where('status', TableSessionStatus::Open)->value('id');

        return app(ConfirmTableRound::class)->execute(
            diningTableId: $this->table->id,
            expectedSessionId: $session === false ? $open : $session,
            kind: $kind,
            items: array_map(fn (int $id, int $quantity): array => ['product_id' => $id, 'quantity' => $quantity], array_keys($items), $items),
            addons: array_map(fn (int $id, int $quantity): array => ['addon_id' => $id, 'quantity' => $quantity], array_keys($addons), $addons),
            note: $note,
            roundKey: $key ?? (string) Str::uuid(),
            actor: $actor ?? $this->waiter,
        );
    }

    /** @return list<PrintableDocumentIssued> */
    private function issued(string $kind): array
    {
        return Event::dispatched(PrintableDocumentIssued::class, fn (PrintableDocumentIssued $event): bool => $event->kind === $kind)
            ->map(fn (array $arguments): PrintableDocumentIssued => $arguments[0])->values()->all();
    }

    private function configureInventory(): void
    {
        $owner = User::factory()->administrator()->create();
        foreach ([$this->salteña, $this->coke] as $product) {
            app(ConfigureProductInventoryConsumption::class)->execute($product->id, 'no_inventory', [], $owner->id);
        }
    }

    private function assertForbidden(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected an authorization failure.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }
    }

    private function assertRejected(callable $callback, string $field): void
    {
        try {
            $callback();
            $this->fail("Expected a validation failure on {$field}.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }
}
