<?php

namespace Tests\Feature\Printing;

use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Actions\ConfirmTableRound;
use App\Modules\Orders\Actions\CreateOrder;
use App\Modules\Orders\Actions\IssueTableBill;
use App\Modules\Orders\Data\OrderCheckoutData;
use App\Modules\Orders\Enums\DeliveryPeriod;
use App\Modules\Orders\Enums\DeliveryType;
use App\Modules\Orders\Enums\OrderCondition;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Enums\TableRoundKind;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderSetting;
use App\Modules\Orders\Models\TableRound;
use App\Modules\Printing\Models\PrintJob;
use App\Modules\Products\Enums\PriceTier;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\RawPriceDiscount;
use App\Modules\Products\Pricing\EmpanadaUnitPrice;
use App\Modules\Restaurant\Models\DiningArea;
use App\Modules\Restaurant\Models\DiningTable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class PrintJobTest extends TestCase
{
    use RefreshDatabase;

    private Product $salteña;

    private Product $coke;

    private DiningTable $table;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        RawPriceDiscount::query()->insert(['pricing_mode' => PriceTier::Dozen->value, 'amount' => 2000]);
        $empanadas = Category::query()->create(['name' => 'Empanadas', 'slug' => 'empanadas', 'sort_order' => 1, 'is_active' => true]);
        $this->salteña = Product::query()->create([
            'category_id' => $empanadas->id, 'name' => 'Salteña', 'slug' => 'saltena', 'pricing_family' => 'empanada',
            'unit_price' => EmpanadaUnitPrice::fromDozen(18000, 10), 'half_dozen_price' => 6 * 1700, 'dozen_price' => 18000,
            'is_available' => true, 'sort_order' => 1,
        ]);
        $this->coke = Product::query()->create([
            'category_id' => $empanadas->id, 'name' => 'Coca-Cola', 'slug' => 'coca-cola', 'pricing_family' => 'unit',
            'unit_price' => 2500, 'half_dozen_price' => 2500, 'dozen_price' => 2500, 'is_available' => true, 'sort_order' => 2,
        ]);
        $area = DiningArea::query()->create(['name' => 'Vereda', 'width' => 1600, 'height' => 1000, 'is_active' => true]);
        $this->table = $area->tables()->create([
            'code' => 'M1', 'shape' => 'rectangle', 'position_x' => '0.1000', 'position_y' => '0.1000',
            'width' => '0.1000', 'height' => '0.1000', 'rotation' => 0, 'is_active' => true,
        ]);
        $this->cashier = User::factory()->withRole(Role::Cashier)->create(['name' => 'Lu']);
        OrderSetting::query()->where('key', 'whatsapp_order_phone')->update(['string_value' => '5491112345678']);
    }

    public function test_each_table_round_stores_one_pending_kitchen_job_and_a_retried_round_stores_none(): void
    {
        $key = (string) Str::uuid();
        $round = $this->round([$this->salteña->id => 6], key: $key);
        $this->round([$this->salteña->id => 6], key: $key);

        $job = PrintJob::query()->sole();
        $this->assertSame(['kitchen_ticket', 'pending', "table-round:{$round->id}", $round->session->order_id], [$job->kind, $job->status, $job->dedupe_key, $job->order_id]);
        $this->assertSame([['name' => 'Salteña', 'quantity' => 6]], $job->payload['lines']);
        $this->assertSame('Mesa M1', $job->payload['title']);
        $this->assertSame($this->cashier->id, $job->requested_by);
    }

    public function test_printing_the_same_bill_twice_stores_one_job_and_a_new_round_stores_a_new_bill(): void
    {
        $session = $this->round([$this->coke->id => 1])->session;
        app(IssueTableBill::class)->execute($session, $this->cashier);
        app(IssueTableBill::class)->execute($session->refresh(), $this->cashier);
        $this->assertSame(1, PrintJob::query()->where('kind', 'bill')->count());

        $this->round([$this->coke->id => 1], session: $session->id);
        app(IssueTableBill::class)->execute($session->refresh(), $this->cashier);
        $this->assertSame([2500, 5000], PrintJob::query()->where('kind', 'bill')->orderBy('id')->get()->map(fn (PrintJob $job): int => $job->payload['total'])->all());
    }

    public function test_admin_and_counter_orders_print_one_kitchen_ticket_with_units_and_no_prices(): void
    {
        $customer = Customer::query()->create(['name' => 'Ana', 'phone' => '+54 9 11 0000-0000', 'phone_normalized' => '5491100000000']);
        $this->actingAs($this->cashier)->post(route('admin.orders.store'), [
            'items' => [['product_id' => $this->salteña->id, 'quantity' => 13], ['product_id' => $this->coke->id, 'quantity' => 2]],
            'addons' => [], 'condition' => 'raw', 'delivery_type' => 'pickup', 'delivery_period' => 'night',
            'delivery_date' => now()->toDateString(), 'delivery_time' => '21:30', 'delivery_address' => null,
            'notes' => 'Retira la hermana', 'customer_id' => $customer->id, 'payment_status' => 'unpaid',
        ])->assertSessionHasNoErrors();
        $order = Order::query()->latest('id')->firstOrFail();

        $job = PrintJob::query()->sole();
        $this->assertSame("order:{$order->id}:kitchen", $job->dedupe_key);
        $this->assertSame('Retiro', $job->payload['title']);
        $this->assertSame('Ana', $job->payload['customer']);
        $this->assertSame('Crudas (para cocinar en casa)', $job->payload['condition']);
        $this->assertSame('Noche 21:30', $job->payload['schedule']);
        $this->assertSame('Retira la hermana', $job->payload['note']);
        $this->assertSame([['name' => 'Salteña', 'quantity' => 13], ['name' => 'Coca-Cola', 'quantity' => 2]], $job->payload['lines']);
        $this->assertStringNotContainsString('18000', json_encode($job->payload, JSON_THROW_ON_ERROR));

        $counter = app(CreateOrder::class)->execute($this->counterData(), OrderSource::Pos, $this->cashier->id);
        $ticket = PrintJob::query()->where('order_id', $counter->id)->sole();
        $this->assertSame('Mostrador', $ticket->payload['title']);
        $this->assertNull($ticket->payload['customer']);
    }

    public function test_a_public_order_prints_only_when_staff_first_confirm_it(): void
    {
        $this->postJson(route('public.orders.store'), [
            'items' => [['product_slug' => 'saltena', 'quantity' => 6]], 'addons' => [], 'condition' => 'cooked', 'delivery_type' => 'pickup',
            'delivery_period' => 'night', 'delivery_date' => now()->toDateString(), 'delivery_time' => null, 'delivery_address' => null,
            'notes' => null, 'customer' => ['name' => 'Visitor', 'phone' => '+54 9 11 1234-5678', 'address' => 'X'],
            'idempotency_key' => 'print-public-order-key-0001',
        ])->assertSuccessful();
        $order = Order::query()->latest('id')->firstOrFail();
        $this->assertSame(0, PrintJob::query()->count());

        foreach (['waiting', 'prepared', 'waiting'] as $status) {
            $this->actingAs($this->cashier)->patch(route('admin.orders.status.update', $order), ['status' => $status])->assertSessionHasNoErrors();
        }
        $job = PrintJob::query()->sole();
        $this->assertSame("order:{$order->id}:kitchen", $job->dedupe_key);
        $this->assertSame('Visitor', $job->payload['customer']);
    }

    public function test_confirming_a_public_order_through_the_full_edit_prints_it_once(): void
    {
        $order = $this->publicOrder('print-public-order-key-0003');
        $payload = fn (Order $order): array => [
            'items' => [['product_id' => $this->coke->id, 'quantity' => 2]], 'addons' => [], 'condition' => 'cooked', 'delivery_type' => 'pickup',
            'delivery_period' => 'night', 'delivery_date' => now()->toDateString(), 'delivery_time' => null, 'delivery_address' => null,
            'notes' => null, 'customer_id' => $order->customer_id, 'payment_status' => 'unpaid', 'status' => 'waiting', 'expected_version' => $order->edit_version,
        ];

        $this->actingAs($this->cashier)->put(route('admin.orders.update', $order), $payload($order))->assertSessionHasNoErrors();
        $this->actingAs($this->cashier)->put(route('admin.orders.update', $order), $payload($order->refresh()))->assertSessionHasNoErrors();

        $job = PrintJob::query()->sole();
        $this->assertSame("order:{$order->id}:kitchen", $job->dedupe_key);
        $this->assertSame([['name' => 'Coca-Cola', 'quantity' => 2]], $job->payload['lines']);
    }

    public function test_a_cancelled_public_order_never_prints(): void
    {
        $order = $this->publicOrder('print-public-order-key-0002');

        $this->actingAs($this->cashier)->patch(route('admin.orders.status.update', $order), ['status' => 'cancelled'])->assertSessionHasNoErrors();
        $this->assertSame(0, PrintJob::query()->count());
    }

    public function test_the_station_claims_each_pending_job_once_and_acknowledges_it_idempotently(): void
    {
        $this->round([$this->coke->id => 1]);
        $this->round([$this->coke->id => 1], session: TableRound::query()->value('table_session_id'));

        $claimed = $this->actingAs($this->cashier)->postJson(route('admin.print-jobs.claim'))->assertOk()->json('data');
        $this->assertCount(2, $claimed);
        $this->assertSame(['kitchen_ticket', 'kitchen_ticket'], array_column($claimed, 'kind'));
        $this->assertLessThan($claimed[1]['id'], $claimed[0]['id']);
        $this->assertSame([], $this->postJson(route('admin.print-jobs.claim'))->assertOk()->json('data'));

        $first = $claimed[0];
        $this->postJson(route('admin.print-jobs.printed', $first['id']), ['claim_token' => $first['claim_token']])->assertOk();
        $this->postJson(route('admin.print-jobs.printed', $first['id']), ['claim_token' => $first['claim_token']])->assertOk();
        $this->postJson(route('admin.print-jobs.printed', $claimed[1]['id']), ['claim_token' => (string) Str::uuid()])->assertStatus(409);

        $job = PrintJob::query()->findOrFail($first['id']);
        $this->assertSame(['printed', $this->cashier->id, 1], [$job->status, $job->printed_by, $job->attempts]);
        $this->assertSame('claimed', PrintJob::query()->findOrFail($claimed[1]['id'])->status);
    }

    public function test_a_person_confirms_an_unacknowledged_job_without_its_claim_token(): void
    {
        $this->round([$this->coke->id => 1]);
        $job = $this->actingAs($this->cashier)->postJson(route('admin.print-jobs.claim'))->json('data.0');

        $this->postJson(route('admin.print-jobs.printed', $job['id']))->assertOk()->assertJsonPath('data.status', 'printed');
        $this->postJson(route('admin.print-jobs.printed', $job['id']))->assertOk();
        $this->postJson(route('admin.print-jobs.release', $job['id']))->assertStatus(409);
        $this->assertSame(['printed', $this->cashier->id], [PrintJob::query()->sole()->status, PrintJob::query()->sole()->printed_by]);
    }

    public function test_an_unconfirmed_claim_is_never_printed_again_automatically_and_a_person_releases_it(): void
    {
        $this->round([$this->coke->id => 1]);
        $job = $this->actingAs($this->cashier)->postJson(route('admin.print-jobs.claim'))->json('data.0');
        $this->travel(10)->minutes();

        $this->assertSame([], $this->postJson(route('admin.print-jobs.claim'))->json('data'));
        $status = $this->getJson(route('admin.print-jobs.status'))->assertOk()->json('data');
        $this->assertSame(0, $status['pending']);
        $this->assertSame(1, $status['unconfirmed']);
        $this->assertGreaterThanOrEqual(600, $status['oldest_unconfirmed_seconds']);

        $this->postJson(route('admin.print-jobs.release', $job['id']))->assertOk();
        $this->assertSame('pending', PrintJob::query()->findOrFail($job['id'])->status);
        $this->assertCount(1, $this->postJson(route('admin.print-jobs.claim'))->json('data'));
    }

    public function test_status_reports_pending_jobs_and_the_age_of_the_oldest(): void
    {
        $this->round([$this->coke->id => 1]);
        $this->travel(45)->seconds();

        $status = $this->actingAs($this->cashier)->getJson(route('admin.print-jobs.status'))->assertOk()->json('data');
        $this->assertSame(1, $status['pending']);
        $this->assertGreaterThanOrEqual(45, $status['oldest_pending_seconds']);
    }

    public function test_a_reprint_is_a_new_job_with_the_same_frozen_payload(): void
    {
        $this->round([$this->coke->id => 1]);
        $original = PrintJob::query()->sole();

        $this->actingAs($this->cashier)->postJson(route('admin.print-jobs.reprint', $original->id))->assertCreated();
        $reprint = PrintJob::query()->whereKeyNot($original->id)->sole();
        $this->assertSame([$original->payload, $original->id, 'pending', $original->kind], [$reprint->payload, $reprint->reprint_of_id, $reprint->status, $reprint->kind]);
        $claimed = collect($this->postJson(route('admin.print-jobs.claim'))->json('data'))->keyBy('id');
        $this->assertSame([false, true], [$claimed[$original->id]['reprint'], $claimed[$reprint->id]['reprint']]);
    }

    public function test_print_job_content_cannot_be_rewritten(): void
    {
        $this->round([$this->coke->id => 1]);
        $this->expectException(QueryException::class);
        DB::table('print_jobs')->update(['payload' => '{"lines":[]}']);
    }

    public function test_the_station_never_sees_or_touches_another_restaurants_jobs(): void
    {
        $this->round([$this->coke->id => 1]);
        $own = PrintJob::query()->sole();
        DB::table('restaurants')->insert(['id' => 2, 'name' => 'Otro', 'slug' => 'otro', 'created_at' => now(), 'updated_at' => now()]);
        $foreignId = DB::table('print_jobs')->insertGetId([
            'restaurant_id' => 2, 'kind' => 'kitchen_ticket', 'order_id' => $own->order_id, 'dedupe_key' => 'foreign',
            'payload' => '{"title":"Otro"}', 'requested_by' => $this->cashier->id, 'created_at' => now()->subMinute(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->cashier);
        $this->assertSame(1, $this->getJson(route('admin.print-jobs.status'))->assertOk()->json('data.pending'));
        $this->assertSame([$own->id], array_column($this->getJson(route('admin.print-jobs.index'))->assertOk()->json('data'), 'id'));
        $this->assertSame([$own->id], array_column($this->postJson(route('admin.print-jobs.claim'))->assertOk()->json('data'), 'id'));
        $this->postJson(route('admin.print-jobs.printed', $foreignId))->assertNotFound();
        $this->postJson(route('admin.print-jobs.release', $foreignId))->assertNotFound();
        $this->postJson(route('admin.print-jobs.reprint', $foreignId))->assertNotFound();

        $this->assertSame(['pending', 0], [DB::table('print_jobs')->where('id', $foreignId)->value('status'), DB::table('print_jobs')->where('restaurant_id', 2)->where('id', '<>', $foreignId)->count()]);
        $this->assertSame(2, DB::table('print_jobs')->count());
    }

    public function test_the_print_station_page_opens_for_order_managers_with_the_restaurant_name(): void
    {
        $this->actingAs($this->cashier)->get(route('admin.print-station'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/printing/station')->where('restaurant', 'Te Pinta'));
        $this->actingAs(User::factory()->withRole(Role::Kitchen)->create())->get(route('admin.print-station'))->assertForbidden();
    }

    public function test_members_without_order_management_cannot_operate_the_station(): void
    {
        $this->round([$this->coke->id => 1]);
        $kitchen = User::factory()->withRole(Role::Kitchen)->create();

        $this->actingAs($kitchen)->postJson(route('admin.print-jobs.claim'))->assertForbidden();
        $this->actingAs($kitchen)->getJson(route('admin.print-jobs.status'))->assertForbidden();
        $this->assertSame('pending', PrintJob::query()->sole()->status);
    }

    /** @param array<int, int> $items */
    private function round(array $items, ?string $key = null, ?int $session = null): TableRound
    {
        return app(ConfirmTableRound::class)->execute(
            diningTableId: $this->table->id, expectedSessionId: $session, kind: TableRoundKind::Order,
            items: array_map(fn (int $id, int $quantity): array => ['product_id' => $id, 'quantity' => $quantity], array_keys($items), $items),
            addons: [], note: null, roundKey: $key ?? (string) Str::uuid(), actor: $this->cashier,
        );
    }

    private function publicOrder(string $idempotencyKey): Order
    {
        return app(CreateOrder::class)->execute(new OrderCheckoutData(
            items: [['product_id' => $this->coke->id, 'quantity' => 1]], addons: [], condition: OrderCondition::Cooked, deliveryType: DeliveryType::Pickup,
            deliveryPeriod: DeliveryPeriod::Night, deliveryDate: now()->toDateString(), deliveryTime: null, deliveryAddress: null, notes: null,
            customerId: null, customerName: 'Visitor', customerPhone: '+54 9 11 1234-5678', customerPhoneNormalized: '5491112345678', customerAddress: null,
            paymentStatus: PaymentStatus::Unpaid, idempotencyKey: $idempotencyKey,
        ), OrderSource::PublicCheckout);
    }

    private function counterData(): OrderCheckoutData
    {
        return new OrderCheckoutData(
            items: [['product_id' => $this->coke->id, 'quantity' => 1]], addons: [], condition: OrderCondition::Cooked, deliveryType: DeliveryType::DineIn,
            deliveryPeriod: null, deliveryDate: null, deliveryTime: null, deliveryAddress: null, notes: null,
            customerId: null, customerName: null, customerPhone: null, customerPhoneNormalized: null, customerAddress: null,
            paymentStatus: PaymentStatus::Unpaid,
        );
    }
}
