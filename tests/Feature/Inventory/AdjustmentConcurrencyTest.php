<?php

namespace Tests\Feature\Inventory;

use App\Core\Support\TestingDatabaseGuard;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Actions\CreateInventoryAdjustment;
use App\Modules\Inventory\Actions\RecordInventoryTransaction;
use App\Modules\Inventory\Data\InventoryAdjustmentData;
use App\Modules\Inventory\Data\InventoryMovementData;
use App\Modules\Inventory\Data\InventoryTransactionData;
use App\Modules\Inventory\Enums\InventorySourceType;
use App\Modules\Inventory\Enums\InventoryTransactionType;
use App\Modules\Inventory\Models\InventoryAdjustment;
use App\Modules\Inventory\Models\InventoryCategory;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryUnit;
use App\Modules\Inventory\Support\DecimalMath;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class AdjustmentConcurrencyTest extends TestCase
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

    public function test_live_concurrent_identical_counts_do_not_apply_delta_twice(): void
    {
        [$a,$i,$u] = $this->fixture();
        $this->schedule($a, $i, $u, 'count_correction', '8', [(string) Str::uuid(), (string) Str::uuid()]);
        $this->assertSame('8000.000000', $this->stock($i));
        $this->assertDatabaseCount('inventory_adjustments', 1);
        $r = InventoryAdjustment::query()->sole();
        $this->assertSame('10000.000000', $r->stock_before);
        $this->assertSame('-2000.000000', $r->delta);
        $this->assertSame(-8000, $r->cost_total);
    }

    public function test_live_canonical_uuid_retries_create_one_historical_adjustment(): void
    {
        [$a,$i,$u] = $this->fixture();
        $key = (string) Str::uuid();
        $this->schedule($a, $i, $u, 'waste', '2', [$key, strtoupper($key)]);
        $this->assertDatabaseCount('inventory_adjustments', 1);
        $this->assertSame('8000.000000', $this->stock($i));
    }

    public function test_live_competing_reversals_restore_history_only_once(): void
    {
        [$a,$i,$u] = $this->fixture();
        $b = app(CreateInventoryAdjustment::class)->execute(new InventoryAdjustmentData($i->id, $u->id, 'waste', '2', CarbonImmutable::parse('2026-10-11'), (string) Str::uuid()), $a->id);
        $this->schedule($a, $i, $u, 'reverse', (string) $b->id, [(string) Str::uuid(), (string) Str::uuid()]);
        $this->assertSame('10000.000000', $this->stock($i));
        $this->assertSame(1, $b->reversal()->count());
        $this->assertDatabaseCount('inventory_adjustments', 2);
    }

    public function test_direct_sql_stale_count_snapshot_and_late_append_are_rejected(): void
    {
        [$a,$i,$u] = $this->fixture();
        $b = app(CreateInventoryAdjustment::class)->execute(new InventoryAdjustmentData($i->id, $u->id, 'count_correction', '8', CarbonImmutable::parse('2026-10-11'), (string) Str::uuid()), $a->id);
        $attrs = $b->getAttributes();
        unset($attrs['id'],$attrs['creation_xid']);
        $attrs['idempotency_key'] = (string) Str::uuid();
        $attrs['inventory_transaction_id'] = null;
        try {
            DB::transaction(fn () => DB::table('inventory_adjustments')->insert($attrs));
            $this->fail('Stale count snapshot');
        } catch (QueryException $e) {
            $this->assertSame('23514', $e->errorInfo[0]);
            $this->assertStringContainsString('locked current stock', $e->getMessage());
        }
        try {
            DB::transaction(fn () => $b->transaction->movements()->create(['inventory_item_id' => $i->id, 'quantity' => '1', 'unit_id' => $i->base_unit_id]));
            $this->fail('Late append');
        } catch (QueryException $e) {
            $this->assertSame('55000', $e->errorInfo[0]);
        }
    }

    private function schedule(User $a, InventoryItem $i, InventoryUnit $u, string $type, string $q, array $keys): void
    {
        $processes = [];
        $names = [];
        DB::beginTransaction();
        DB::table('inventory_items')->where('id', $i->id)->lockForUpdate()->first();
        try {
            foreach ($keys as $key) {
                $name = 'aob4-'.Str::uuid();
                $names[] = $name;
                $payload = base64_encode(json_encode([$a->id, $i->id, $u->id, $type, $q, $key, $name], JSON_THROW_ON_ERROR));
                $code = <<<'PHP'
require 'vendor/autoload.php';$app=require 'bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
App\Core\Support\TestingDatabaseGuard::ensureSafe($app->environment(),Illuminate\Support\Facades\DB::connection()->getDatabaseName());
[$actor,$item,$unit,$type,$q,$key,$name]=json_decode(base64_decode($argv[1]),true,512,JSON_THROW_ON_ERROR);
Illuminate\Support\Facades\DB::select("SELECT set_config('application_name', ?, false)",[$name]);Illuminate\Support\Facades\DB::unprepared("SET statement_timeout='8s'");
try {
 if($type==='reverse'){$r=app(App\Modules\Inventory\Actions\ReverseInventoryAdjustment::class)->execute((int)$q,Carbon\CarbonImmutable::parse('2026-10-12'),'Concurrent reversal',$actor);}
 else {$r=app(App\Modules\Inventory\Actions\CreateInventoryAdjustment::class)->execute(new App\Modules\Inventory\Data\InventoryAdjustmentData($item,$unit,$type,$q,Carbon\CarbonImmutable::parse('2026-10-11'),$key),$actor);}
 echo json_encode(['id'=>$r->id]);
}catch(Illuminate\Validation\ValidationException $e){echo json_encode(['validation'=>$e->errors()]);}
PHP;
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
                }usleep(10000);
            } while (microtime(true) < $deadline);
            $this->assertSame(2, $blocked, 'Both independent processes must wait on PostgreSQL locks before release.');
            DB::commit();
            $success = 0;
            $validation = 0;
            foreach ($processes as $p) {
                $p->wait();
                $this->assertTrue($p->isSuccessful(), $p->getErrorOutput().$p->getOutput());
                $result = json_decode($p->getOutput(), true, 512, JSON_THROW_ON_ERROR);
                $success += isset($result['id']) ? 1 : 0;
                $validation += isset($result['validation']) ? 1 : 0;
            }
            $this->assertSame($type === 'waste' ? 2 : 1, $success);
            $this->assertSame($type === 'waste' ? 0 : 1, $validation);
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }foreach ($processes as $p) {
                if ($p->isRunning()) {
                    $p->stop(0);
                }
            }
        }
    }

    private function fixture(): array
    {
        $a = User::factory()->administrator()->create();
        $g = InventoryUnit::query()->where('code', 'g')->sole();
        $u = InventoryUnit::query()->where('code', 'kg')->sole();
        $c = InventoryCategory::query()->create(['name' => 'Concurrent count']);
        $i = InventoryItem::query()->create(['inventory_category_id' => $c->id, 'name' => 'Onion', 'tracks_stock' => true, 'unit_family' => 'weight', 'base_unit_id' => $g->id, 'default_input_unit_id' => $u->id, 'quantity_precision' => 3, 'is_active' => true, 'kind' => 'raw_material']);
        app(RecordInventoryTransaction::class)->execute(new InventoryTransactionData(InventoryTransactionType::Adjustment, CarbonImmutable::parse('2026-10-01'), [new InventoryMovementData($i->id, '10000', 40000)], InventorySourceType::Adjustment, (string) Str::uuid()), $a->id);

        return [$a, $i, $u];
    }

    private function stock(InventoryItem $i): string
    {
        return DecimalMath::normalize((string) $i->stockMovements()->sum('quantity'), 6);
    }
}
