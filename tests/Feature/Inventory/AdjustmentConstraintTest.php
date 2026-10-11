<?php

namespace Tests\Feature\Inventory;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Actions\CreateInventoryAdjustment;
use App\Modules\Inventory\Actions\RecordInventoryTransaction;
use App\Modules\Inventory\Data\InventoryAdjustmentData;
use App\Modules\Inventory\Data\InventoryMovementData;
use App\Modules\Inventory\Data\InventoryTransactionData;
use App\Modules\Inventory\Enums\InventorySourceType;
use App\Modules\Inventory\Enums\InventoryTransactionType;
use App\Modules\Inventory\Models\InventoryCategory;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryTransaction;
use App\Modules\Inventory\Models\InventoryUnit;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AdjustmentConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_rejects_negative_observed_input_even_with_valid_delta_and_snapshot(): void
    {
        [$a,$i,$u,$b] = $this->fixture();
        $attrs = $b->getAttributes();
        unset($attrs['id'],$attrs['creation_xid']);
        $attrs = array_replace($attrs, ['type' => 'count_correction', 'quantity' => '-7', 'stock_before' => '8000', 'physical_count' => '7000', 'delta' => '-1000', 'cost_total' => -4000, 'cost_basis_quantity' => '8000', 'cost_basis_total' => 32000, 'inventory_transaction_id' => null, 'idempotency_key' => (string) Str::uuid()]);
        $this->rejected(fn () => DB::table('inventory_adjustments')->insert($attrs), '23514');
    }

    public function test_history_basis_linkage_orphan_and_empty_rollback_boundary(): void
    {
        [$a,$i,$u,$b] = $this->fixture();
        $this->rejected(fn () => $b->update(['cost_total' => -1]), '55000');
        $this->rejected(fn () => $b->delete(), '55000');
        $attrs = $b->getAttributes();
        unset($attrs['id'],$attrs['creation_xid']);
        $attrs = array_replace($attrs, ['stock_before' => '8000', 'inventory_transaction_id' => null, 'idempotency_key' => (string) Str::uuid(), 'cost_total' => -1]);
        $this->rejected(fn () => DB::table('inventory_adjustments')->insert($attrs), '23514');
        $this->rejected(function () use ($a): void {
            InventoryTransaction::query()->create(['type' => 'adjustment', 'source_type' => 'adjustment', 'source_id' => 'inventory-adjustment:999', 'effective_date' => '2026-10-11', 'created_by' => $a->id]);
            DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        }, '23514');
        $migration = require database_path('migrations/2026_10_11_000000_create_inventory_adjustments.php');
        try {
            $migration->down();
            $this->fail('Populated rollback');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('empty aggregate', $e->getMessage());
            $this->assertDatabaseCount('inventory_adjustments', 1);
        }
    }

    private function rejected(callable $work, string $state): void
    {
        try {
            DB::transaction($work);
            $this->fail('Expected database rejection');
        } catch (QueryException $e) {
            $this->assertSame($state, $e->errorInfo[0]);
        }
    }

    private function fixture(): array
    {
        $a = User::factory()->administrator()->create();
        $g = InventoryUnit::query()->where('code', 'g')->sole();
        $u = InventoryUnit::query()->where('code', 'kg')->sole();
        $c = InventoryCategory::query()->create(['name' => 'Adjustment constraints']);
        $i = InventoryItem::query()->create(['inventory_category_id' => $c->id, 'name' => 'Onion', 'tracks_stock' => true, 'unit_family' => 'weight', 'base_unit_id' => $g->id, 'default_input_unit_id' => $u->id, 'quantity_precision' => 3, 'is_active' => true, 'kind' => 'raw_material']);
        app(RecordInventoryTransaction::class)->execute(new InventoryTransactionData(InventoryTransactionType::Adjustment, CarbonImmutable::parse('2026-10-01'), [new InventoryMovementData($i->id, '10000', 40000)], InventorySourceType::Adjustment, (string) Str::uuid()), $a->id);
        $b = app(CreateInventoryAdjustment::class)->execute(new InventoryAdjustmentData($i->id, $u->id, 'waste', '2', CarbonImmutable::parse('2026-10-11'), (string) Str::uuid()), $a->id);

        return [$a, $i, $u, $b];
    }
}
