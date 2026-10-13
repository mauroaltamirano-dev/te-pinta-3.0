<?php

namespace Tests\Feature\Inventory;

use App\Core\Support\TestingDatabaseGuard;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Actions\ConfigureProductInventoryConsumption;
use App\Modules\Inventory\Actions\RecordInventoryTransaction;
use App\Modules\Inventory\Data\InventoryMovementData;
use App\Modules\Inventory\Data\InventoryTransactionData;
use App\Modules\Inventory\Enums\InventorySourceType;
use App\Modules\Inventory\Enums\InventoryTransactionType;
use App\Modules\Inventory\Models\InventoryCategory;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryUnit;
use App\Modules\Inventory\Models\OrderInventoryConsumption;
use App\Modules\Inventory\Support\DecimalMath;
use App\Modules\Orders\Actions\CreateOrder;
use App\Modules\Orders\Actions\UpdateOrderStatus;
use App\Modules\Orders\Data\OrderCheckoutData;
use App\Modules\Orders\Enums\DeliveryType;
use App\Modules\Orders\Enums\OrderCondition;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class OrderConsumptionConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TestingDatabaseGuard::ensureSafe(app()->environment(), DB::connection()->getDatabaseName());
        $this->artisan('migrate:fresh')->assertExitCode(0);
        RefreshDatabaseState::$migrated = true;
        $this->beforeApplicationDestroyed(static function (): void {
            RefreshDatabaseState::$migrated = false;
        });
    }

    public function test_live_duplicate_delivered_events_and_listener_retries_create_one_fact(): void
    {
        [$a,$i,$orders] = $this->fixture();
        $this->schedule($a, $i, [$orders[0]->id, $orders[0]->id], 'delivered', 'orders', $orders[0]->id);
        $this->assertDatabaseCount('order_inventory_consumptions', 1);
        $this->schedule($a, $i, [$orders[0]->id, $orders[0]->id], 'retry', 'orders', $orders[0]->id);
        $this->assertDatabaseCount('order_inventory_consumptions', 1);
        $this->assertSame('835.000000', $this->stock($i));
    }

    public function test_live_distinct_orders_serialize_shared_valuation_layers(): void
    {
        [$a,$i,$orders] = $this->fixture();
        $this->schedule($a, $i, [$orders[0]->id, $orders[1]->id], 'delivered', 'inventory_items', $i->id);
        $this->assertDatabaseCount('order_inventory_consumptions', 2);
        $this->assertSame('670.000000', $this->stock($i));
        $this->assertSame(3300, (int) DB::table('order_inventory_consumptions')->sum('total_cost'));
    }

    public function test_live_concurrent_cancellations_and_reversal_retries_restore_once(): void
    {
        [$a,$i,$orders] = $this->fixture();
        app(UpdateOrderStatus::class)->execute($orders[0], OrderStatus::Delivered, $a->id);
        $this->schedule($a, $i, [$orders[0]->id, $orders[0]->id], 'cancelled', 'orders', $orders[0]->id);
        $this->schedule($a, $i, [$orders[0]->id, $orders[0]->id], 'reverse', 'orders', $orders[0]->id);
        $this->assertDatabaseCount('order_inventory_consumption_reversals', 1);
        $this->assertSame('1000.000000', $this->stock($i));
        $this->assertSame(10000, (int) $i->costLayers()->sum('total_cost'));
    }

    public function test_committed_history_rejects_late_children_and_mutation(): void
    {
        [$a,$i,$orders] = $this->fixture();
        app(UpdateOrderStatus::class)->execute($orders[0], OrderStatus::Delivered, $a->id);
        $c = OrderInventoryConsumption::query()->sole();
        foreach ([fn () => $c->update(['total_cost' => 1]), fn () => $c->transaction->movements()->create(['inventory_item_id' => $i->id, 'unit_id' => $i->base_unit_id, 'quantity' => '-1']), fn () => $c->delete()] as $work) {
            try {
                DB::transaction($work);
                $this->fail('Committed history changed');
            } catch (QueryException $e) {
                $this->assertSame('55000', $e->errorInfo[0]);
            }
        }
    }

    /** @param list<int> $ids */
    private function schedule(User $a, InventoryItem $i, array $ids, string $operation, string $table, int $holder): void
    {
        $processes = [];
        $names = [];
        DB::beginTransaction();
        DB::table($table)->where('id', $holder)->lockForUpdate()->first();
        try {
            foreach ($ids as $id) {
                $name = 'aob5-'.Str::uuid();
                $names[] = $name;
                $payload = base64_encode(json_encode([$a->id, $id, $operation, $name], JSON_THROW_ON_ERROR));
                $code = <<<'CODE'
require 'vendor/autoload.php';$app=require 'bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
App\Core\Support\TestingDatabaseGuard::ensureSafe($app->environment(),Illuminate\Support\Facades\DB::connection()->getDatabaseName());
[$actor,$id,$operation,$name]=json_decode(base64_decode($argv[1]),true,512,JSON_THROW_ON_ERROR);
Illuminate\Support\Facades\DB::select("SELECT set_config('application_name', ?, false)",[$name]);Illuminate\Support\Facades\DB::unprepared("SET statement_timeout='8s'");
if($operation==='retry'){$r=app(App\Modules\Inventory\Actions\ConsumeDeliveredOrder::class)->execute($id,Carbon\CarbonImmutable::now(),$actor);}
elseif($operation==='reverse'){$r=app(App\Modules\Inventory\Actions\ReverseOrderInventoryConsumption::class)->execute($id,Carbon\CarbonImmutable::now(),$actor);}
else {$order=App\Modules\Orders\Models\Order::query()->findOrFail($id);$r=app(App\Modules\Orders\Actions\UpdateOrderStatus::class)->execute($order,App\Modules\Orders\Enums\OrderStatus::from($operation),$actor);}
echo json_encode(['id'=>$r->id]);
CODE;
                $p = new Process([PHP_BINARY, '-r', $code, $payload], base_path(), ['APP_ENV' => 'testing', 'DB_DATABASE' => DB::connection()->getDatabaseName(), 'DB_URL' => '']);
                $p->setTimeout(12);
                $p->start();
                $processes[] = $p;
            }
            $deadline = microtime(true) + 6;
            $blocked = 0;
            do {
                DB::select('SELECT pg_stat_clear_snapshot()');
                $blocked = DB::table('pg_stat_activity')->whereIn('application_name', $names)->where('wait_event_type', 'Lock')->count();
                if ($blocked === 2) {
                    break;
                } usleep(10000);
            } while (microtime(true) < $deadline);
            $this->assertSame(2, $blocked, 'Both independent processes must be observed waiting on PostgreSQL locks.');
            DB::commit();
            foreach ($processes as $p) {
                $p->wait();
                $this->assertTrue($p->isSuccessful(), $p->getErrorOutput().$p->getOutput());
                $this->assertArrayHasKey('id', json_decode($p->getOutput(), true, 512, JSON_THROW_ON_ERROR));
            }
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            } foreach ($processes as $p) {
                if ($p->isRunning()) {
                    $p->stop(0);
                }
            }
        }
    }

    /** @return array{User,InventoryItem,list<Order>} */
    private function fixture(): array
    {
        $a = User::factory()->administrator()->create();
        $u = InventoryUnit::query()->where('code', 'g')->sole();
        $category = InventoryCategory::query()->create(['name' => 'Concurrent sale']);
        $i = InventoryItem::query()->create(['inventory_category_id' => $category->id, 'name' => 'Produced filling', 'tracks_stock' => true, 'unit_family' => 'weight', 'base_unit_id' => $u->id, 'default_input_unit_id' => $u->id, 'quantity_precision' => 6, 'is_active' => true, 'kind' => 'semi_finished', 'order_consumption_enabled' => true]);
        app(RecordInventoryTransaction::class)->execute(new InventoryTransactionData(InventoryTransactionType::Adjustment, now(), [new InventoryMovementData($i->id, '1000', 10000)], InventorySourceType::Adjustment, (string) Str::uuid()), $a->id);
        $c = Category::query()->create(['name' => 'Sale', 'slug' => 'sale']);
        $p = Product::query()->create(['category_id' => $c->id, 'name' => 'Filling sale', 'slug' => 'sale', 'pricing_family' => 'unit', 'unit_price' => 2500, 'half_dozen_price' => 2500, 'dozen_price' => 2500, 'is_available' => true]);
        app(ConfigureProductInventoryConsumption::class)->execute($p->id, 'consume', [['inventory_item_id' => $i->id, 'unit_id' => $u->id, 'quantity' => '55']], $a->id);
        $orders = [];
        for ($n = 0; $n < 2; $n++) {
            $orders[] = app(CreateOrder::class)->execute(new OrderCheckoutData(items: [['product_id' => $p->id, 'quantity' => 3]], addons: [], condition: OrderCondition::Cooked, deliveryType: DeliveryType::DineIn, deliveryPeriod: null, deliveryDate: null, deliveryTime: null, deliveryAddress: null, notes: null, customerId: null, customerName: null, customerPhone: null, customerPhoneNormalized: null, customerAddress: null, paymentStatus: PaymentStatus::Unpaid), OrderSource::Pos, $a->id);
        }

        return [$a, $i, $orders];
    }

    private function stock(InventoryItem $i): string
    {
        return DecimalMath::normalize((string) $i->stockMovements()->sum('quantity'), 6);
    }
}
