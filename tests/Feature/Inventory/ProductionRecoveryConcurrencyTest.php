<?php

namespace Tests\Feature\Inventory;

use App\Core\Support\TestingDatabaseGuard;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Actions\CreateProductionBatch;
use App\Modules\Inventory\Actions\CreateRecipe;
use App\Modules\Inventory\Actions\CreateRecipeVersion;
use App\Modules\Inventory\Actions\RecordInventoryTransaction;
use App\Modules\Inventory\Actions\ReverseProductionBatch;
use App\Modules\Inventory\Data\InventoryMovementData;
use App\Modules\Inventory\Data\InventoryTransactionData;
use App\Modules\Inventory\Data\ProductionBatchData;
use App\Modules\Inventory\Data\RecipeIngredientData;
use App\Modules\Inventory\Data\RecipeVersionData;
use App\Modules\Inventory\Enums\InventorySourceType;
use App\Modules\Inventory\Enums\InventoryTransactionType;
use App\Modules\Inventory\Models\InventoryCategory;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryUnit;
use App\Modules\Inventory\Models\ProductionBatch;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ProductionRecoveryConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TestingDatabaseGuard::ensureSafe(app()->environment(), DB::connection()->getDatabaseName());
        $this->artisan('migrate:fresh')->assertExitCode(0);
        RefreshDatabaseState::$migrated = true;
        DB::connection()->unprepared("SET statement_timeout='5s'");
        config(['database.connections.production_contender' => array_replace(DB::connection()->getConfig(), ['name' => 'production_contender'])]);
        DB::connection('production_contender')->unprepared("SET lock_timeout='300ms'; SET statement_timeout='3s'");
        $this->beforeApplicationDestroyed(static function (): void {
            RefreshDatabaseState::$migrated = false;
        });
    }

    public function test_two_recipes_share_locked_inputs_then_allocate_the_committed_remaining_average(): void
    {
        [$a,$i,$o,$v,$u] = $this->fixture();
        $this->receipt($a, $i, '2', 300);
        $r = app(CreateRecipe::class)->execute('Second recipe', $a->id);
        $second = app(CreateRecipeVersion::class)->execute($r->id, new RecipeVersionData($o->id, $u->id, '1', [new RecipeIngredientData($i->id, $u->id, '1')]), $a->id);
        $main = DB::getDefaultConnection();
        DB::beginTransaction();
        try {
            $b = $this->produce($a, $v->id, $u->id);
            $this->assertSame(133, $b->total_cost);
            $data = new ProductionBatchData($second->id, $u->id, '1', CarbonImmutable::parse('2026-10-07'), (string) Str::uuid());
            $this->contends(fn () => app(CreateProductionBatch::class)->execute($data, $a->id), 'inventory_items');
            DB::commit();
            DB::setDefaultConnection('production_contender');
            $b2 = app(CreateProductionBatch::class)->execute($data, $a->id);
            $this->assertSame(134, $b2->total_cost);
            $this->assertSame('complete', $b2->cost_coverage);
            $this->assertSame('1.000000', (string) DB::scalar('SELECT SUM(quantity)::numeric(20,6)::text FROM inventory_stock_movements WHERE inventory_item_id=?', [$i->id]));
            $this->assertSame(133, (int) DB::table('inventory_cost_layers')->where('inventory_item_id', $i->id)->sum('total_cost'));
        } finally {
            $this->reset($main);
        }
    }

    public function test_canonical_uuid_retries_serialize_and_create_only_one_batch(): void
    {
        [$a,$i,$o,$v,$u] = $this->fixture();
        $key = 'a31a4d05-7d9e-4e38-920b-048a3ce81853';
        $main = DB::getDefaultConnection();
        DB::beginTransaction();
        try {
            $b = app(CreateProductionBatch::class)->execute(new ProductionBatchData($v->id, $u->id, '1', CarbonImmutable::parse('2026-10-07'), strtoupper($key)), $a->id);
            $lower = new ProductionBatchData($v->id, $u->id, '1.000000', CarbonImmutable::parse('2026-10-07'), $key);
            $this->contends(fn () => app(CreateProductionBatch::class)->execute($lower, $a->id), 'pg_advisory_xact_lock');
            DB::commit();
            DB::setDefaultConnection('production_contender');
            $this->assertSame($b->id, app(CreateProductionBatch::class)->execute($lower, $a->id)->id);
            $this->assertDatabaseCount('inventory_production_batches', 1);
            $this->assertDatabaseCount('inventory_production_cost_allocations', 1);
            $this->assertSame($key, $b->idempotency_key);
            $this->expectException(ValidationException::class);
            app(CreateProductionBatch::class)->execute(new ProductionBatchData($v->id, $u->id, '2', CarbonImmutable::parse('2026-10-07'), $key), $a->id);
        } finally {
            $this->reset($main);
        }
    }

    public function test_two_reversals_wait_on_original_batch_and_second_retry_is_rejected(): void
    {
        [$a,$i,$o,$v,$u] = $this->fixture();
        $b = $this->produce($a, $v->id, $u->id);
        $main = DB::getDefaultConnection();
        DB::beginTransaction();
        try {
            app(ReverseProductionBatch::class)->execute($b->id, CarbonImmutable::parse('2026-10-08'), 'First reversal', $a->id);
            $this->contends(fn () => app(ReverseProductionBatch::class)->execute($b->id, CarbonImmutable::parse('2026-10-08'), 'Second reversal', $a->id), 'inventory_production_batches');
            DB::commit();
            DB::setDefaultConnection('production_contender');
            try {
                app(ReverseProductionBatch::class)->execute($b->id, CarbonImmutable::parse('2026-10-08'), 'Second reversal', $a->id);
                $this->fail('Double reversal');
            } catch (ValidationException) {
                $this->assertDatabaseCount('inventory_production_reversals', 1);
            }
            $this->assertSame('0.000000', (string) DB::scalar('SELECT SUM(quantity)::numeric(20,6)::text FROM inventory_stock_movements WHERE inventory_item_id=?', [$o->id]));
        } finally {
            $this->reset($main);
        }
    }

    public function test_recipe_replacement_serializes_with_pending_production(): void
    {
        [$a,$i,$o,$v,$u] = $this->fixture();
        $main = DB::getDefaultConnection();
        DB::beginTransaction();
        try {
            $b = $this->produce($a, $v->id, $u->id);
            $formula = new RecipeVersionData($o->id, $u->id, '2', [new RecipeIngredientData($i->id, $u->id, '1')]);
            $this->contends(fn () => app(CreateRecipeVersion::class)->execute($v->recipe_id, $formula, $a->id), 'inventory_recipes');
            DB::commit();
            DB::setDefaultConnection('production_contender');
            $new = app(CreateRecipeVersion::class)->execute($v->recipe_id, $formula, $a->id);
            $this->assertSame(2, $new->version);
            $this->assertFalse($v->fresh()->is_active);
            $this->assertSame($v->id, $b->fresh()->recipe_version_id);
            $this->assertSame(100, $b->fresh()->total_cost);
        } finally {
            $this->reset($main);
        }
    }

    public function test_committed_production_cannot_receive_new_child_movements(): void
    {
        [$a,$i,$o,$v,$u] = $this->fixture();
        $b = $this->produce($a, $v->id, $u->id);
        try {
            DB::transaction(fn () => $b->transaction->movements()->create(['inventory_item_id' => $i->id, 'quantity' => -1, 'unit_id' => $u->id]));
            $this->fail('Appended committed movement');
        } catch (QueryException $e) {
            $this->assertSame('55000', $e->errorInfo[0]);
            $this->assertStringContainsString('committed production', $e->getMessage());
        }
        $this->assertSame(2, $b->transaction->movements()->count());
    }

    private function contends(callable $work, string $sql): void
    {
        $main = DB::getDefaultConnection();
        DB::setDefaultConnection('production_contender');
        try {
            $work();
            $this->fail('Expected lock contention');
        } catch (QueryException $e) {
            $this->assertSame('55P03', $e->errorInfo[0]);
            $this->assertStringContainsString($sql, $e->getSql());
        } finally {
            DB::setDefaultConnection($main);
        }
    }

    private function reset(string $main): void
    {
        DB::setDefaultConnection($main);
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }DB::purge('production_contender');
    }

    private function receipt(User $a, InventoryItem $i, string $q, int $cost): void
    {
        app(RecordInventoryTransaction::class)->execute(new InventoryTransactionData(InventoryTransactionType::Adjustment, CarbonImmutable::parse('2026-10-01'), [new InventoryMovementData($i->id, $q, $cost)], InventorySourceType::Adjustment, (string) Str::uuid()), $a->id);
    }

    private function produce(User $a, int $version, int $unit): ProductionBatch
    {
        return app(CreateProductionBatch::class)->execute(new ProductionBatchData($version, $unit, '1', CarbonImmutable::parse('2026-10-07'), (string) Str::uuid()), $a->id);
    }

    private function fixture(): array
    {
        $a = User::factory()->administrator()->create();
        $u = InventoryUnit::query()->where('code', 'g')->sole();
        $c = InventoryCategory::query()->create(['name' => 'Concurrent production']);
        $attrs = ['inventory_category_id' => $c->id, 'tracks_stock' => true, 'is_active' => true, 'unit_family' => 'weight', 'base_unit_id' => $u->id, 'default_input_unit_id' => $u->id, 'quantity_precision' => 3, 'recipe_input_enabled' => true, 'production_output_enabled' => true, 'kind' => 'semi_finished'];
        $i = InventoryItem::query()->create($attrs + ['name' => 'Input']);
        $o = InventoryItem::query()->create($attrs + ['name' => 'Output']);
        $this->receipt($a, $i, '1', 100);
        $r = app(CreateRecipe::class)->execute('Concurrent recipe', $a->id);
        $v = app(CreateRecipeVersion::class)->execute($r->id, new RecipeVersionData($o->id, $u->id, '1', [new RecipeIngredientData($i->id, $u->id, '1')]), $a->id);

        return [$a, $i, $o, $v, $u];
    }
}
