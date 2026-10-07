<?php

namespace Tests\Feature\Inventory;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Actions\CreateInventoryCategory;
use App\Modules\Inventory\Actions\CreateInventoryItem;
use App\Modules\Inventory\Actions\RecordInventoryTransaction;
use App\Modules\Inventory\Data\InventoryItemData;
use App\Modules\Inventory\Data\InventoryMovementData;
use App\Modules\Inventory\Data\InventoryTransactionData;
use App\Modules\Inventory\Enums\InventorySourceType;
use App\Modules\Inventory\Enums\InventoryTransactionType;
use App\Modules\Inventory\Enums\UnitFamily;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryUnit;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class InventoryDatabaseConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_postgresql_defines_inventory_constraints_and_triggers(): void
    {
        $constraints = DB::table('pg_constraint')->whereIn('conname', [
            'inventory_items_quantity_precision_valid', 'inventory_transactions_type_valid',
            'inventory_transactions_source_type_valid', 'inventory_stock_movements_quantity_nonzero',
            'inventory_cost_layers_reversal_shape_valid',
            'inventory_cost_layers_stock_movement_id_unique',
        ])->pluck('conname')->all();
        $this->assertEqualsCanonicalizing([
            'inventory_items_quantity_precision_valid', 'inventory_transactions_type_valid',
            'inventory_transactions_source_type_valid', 'inventory_stock_movements_quantity_nonzero',
            'inventory_cost_layers_reversal_shape_valid',
            'inventory_cost_layers_stock_movement_id_unique',
        ], $constraints);

        $triggers = DB::table('pg_trigger')->whereIn('tgname', [
            'inventory_units_system_immutable', 'inventory_items_units_valid', 'inventory_presentations_valid',
            'inventory_transactions_immutable', 'inventory_stock_movements_valid',
            'inventory_stock_movements_immutable', 'inventory_cost_layers_valid',
            'inventory_cost_layers_immutable', 'inventory_transactions_require_movements',
        ])->where('tgisinternal', false)->pluck('tgname')->all();
        $this->assertCount(9, $triggers);
    }

    public function test_postgresql_rejects_incompatible_item_and_presentation_units(): void
    {
        $categoryId = DB::table('inventory_categories')->insertGetId([
            'name' => 'Ingredients', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectQueryFailure(fn () => DB::table('inventory_items')->insert([
            'inventory_category_id' => $categoryId, 'name' => 'Invalid oil', 'tracks_stock' => true,
            'unit_family' => 'weight', 'base_unit_id' => $this->unit('ml')->id,
            'default_input_unit_id' => $this->unit('kg')->id, 'quantity_precision' => 3,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]));

        $item = $this->createItem('Egg', UnitFamily::Count, 'unit', 0);
        $this->expectQueryFailure(fn () => DB::table('inventory_purchase_presentations')->insert([
            'inventory_item_id' => $item->id, 'name' => 'Liter box', 'content_quantity' => '1',
            'content_unit_id' => $this->unit('L')->id, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]));
    }

    public function test_postgresql_rejects_a_source_incompatible_with_the_transaction_type(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->expectQueryFailure(fn () => DB::table('inventory_transactions')->insert([
            'type' => 'purchase_receipt', 'effective_date' => '2026-09-02',
            'source_type' => 'order', 'source_id' => 'wrong-source',
            'created_by' => $admin->id, 'created_at' => now(), 'updated_at' => now(),
        ]));
        $this->expectQueryFailure(fn () => DB::table('inventory_transactions')->insert([
            'type' => 'adjustment', 'effective_date' => '2026-09-02',
            'source_type' => null, 'source_id' => null,
            'created_by' => $admin->id, 'created_at' => now(), 'updated_at' => now(),
        ]));
    }

    public function test_postgresql_keeps_system_units_and_historical_item_measurement_identity_immutable(): void
    {
        $admin = User::factory()->administrator()->create();
        $item = $this->createItem('Flour', UnitFamily::Weight, 'g', 3);
        app(RecordInventoryTransaction::class)->execute(new InventoryTransactionData(
            InventoryTransactionType::Adjustment, CarbonImmutable::parse('2026-09-02'),
            [new InventoryMovementData($item->id, '1000')], InventorySourceType::Adjustment, 'flour-stock',
        ), $admin->id);

        $this->expectQueryFailure(fn () => DB::table('inventory_units')->where('code', 'kg')->update(['factor_to_base' => '999']));
        $this->expectQueryFailure(fn () => DB::table('inventory_units')->where('code', 'kg')->delete());
        $this->expectQueryFailure(fn () => DB::table('inventory_items')->where('id', $item->id)->update([
            'base_unit_id' => $this->unit('kg')->id,
        ]));

        $this->assertSame('g', $item->fresh()->baseUnit->code);
    }

    public function test_postgresql_prevents_item_measurement_changes_that_would_invalidate_presentations(): void
    {
        $item = $this->createItem('Egg', UnitFamily::Count, 'unit', 0);
        DB::table('inventory_purchase_presentations')->insert([
            'inventory_item_id' => $item->id, 'name' => 'Maple', 'content_quantity' => '30',
            'content_unit_id' => $this->unit('unit')->id, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectQueryFailure(fn () => DB::table('inventory_items')->where('id', $item->id)->update([
            'unit_family' => 'weight', 'base_unit_id' => $this->unit('g')->id,
            'default_input_unit_id' => $this->unit('g')->id,
        ]));
    }

    public function test_postgresql_rejects_non_base_imprecise_and_non_stockable_movements(): void
    {
        $admin = User::factory()->administrator()->create();
        $egg = $this->createItem('Egg', UnitFamily::Count, 'unit', 0);
        $nonStockable = $this->createItem('Occasional tray', UnitFamily::Count, 'unit', 0, false);

        foreach ([
            [$egg, '1', $this->unit('dozen')->id],
            [$egg, '1.5', $this->unit('unit')->id],
            [$nonStockable, '1', $this->unit('unit')->id],
        ] as [$item, $quantity, $unitId]) {
            $this->expectQueryFailure(function () use ($admin, $item, $quantity, $unitId): void {
                DB::transaction(function () use ($admin, $item, $quantity, $unitId): void {
                    $transactionId = $this->insertTransaction($admin->id, uniqid('source-', true));
                    DB::table('inventory_stock_movements')->insert([
                        'inventory_transaction_id' => $transactionId, 'inventory_item_id' => $item->id,
                        'quantity' => $quantity, 'unit_id' => $unitId, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                });
            });
        }
    }

    public function test_postgresql_requires_a_movement_and_prevents_history_mutation(): void
    {
        $admin = User::factory()->administrator()->create();
        $item = $this->createItem('Cumin', UnitFamily::Weight, 'g', 3);

        $this->expectQueryFailure(function () use ($admin): void {
            DB::transaction(function () use ($admin): void {
                $this->insertTransaction($admin->id, 'orphan');
                DB::statement('SET CONSTRAINTS inventory_transactions_require_movements IMMEDIATE');
            });
        });

        $transaction = app(RecordInventoryTransaction::class)->execute(new InventoryTransactionData(
            InventoryTransactionType::Adjustment, CarbonImmutable::parse('2026-09-02'),
            [new InventoryMovementData($item->id, '10')], InventorySourceType::Adjustment, 'immutable',
        ), $admin->id);
        $movement = $transaction->movements->sole();

        foreach ([
            fn () => DB::table('inventory_transactions')->where('id', $transaction->id)->update(['description' => 'changed']),
            fn () => DB::table('inventory_transactions')->where('id', $transaction->id)->delete(),
            fn () => DB::table('inventory_stock_movements')->where('id', $movement->id)->update(['quantity' => '20']),
            fn () => DB::table('inventory_stock_movements')->where('id', $movement->id)->delete(),
        ] as $mutation) {
            $this->expectQueryFailure($mutation);
        }
    }

    public function test_postgresql_requires_cost_layer_to_exactly_match_a_positive_movement(): void
    {
        $admin = User::factory()->administrator()->create();
        $item = $this->createItem('Mozzarella', UnitFamily::Weight, 'g', 3);
        $transaction = app(RecordInventoryTransaction::class)->execute(new InventoryTransactionData(
            InventoryTransactionType::Adjustment, CarbonImmutable::parse('2026-09-02'),
            [new InventoryMovementData($item->id, '100')], InventorySourceType::Adjustment, 'layer-source',
        ), $admin->id);
        $movement = $transaction->movements->sole();

        foreach ([
            ['quantity' => '99', 'total_cost' => 1000, 'effective_date' => '2026-09-02'],
            ['quantity' => '100', 'total_cost' => -1, 'effective_date' => '2026-09-02'],
            ['quantity' => '100', 'total_cost' => 1000, 'effective_date' => '2026-09-01'],
        ] as $override) {
            $this->expectQueryFailure(fn () => DB::table('inventory_cost_layers')->insert(array_merge([
                'inventory_item_id' => $item->id, 'inventory_transaction_id' => $transaction->id,
                'stock_movement_id' => $movement->id, 'created_at' => now(), 'updated_at' => now(),
            ], $override)));
        }

        DB::table('inventory_cost_layers')->insert([
            'inventory_item_id' => $item->id, 'inventory_transaction_id' => $transaction->id,
            'stock_movement_id' => $movement->id, 'quantity' => '100', 'total_cost' => 1000,
            'effective_date' => '2026-09-02', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $layerId = DB::table('inventory_cost_layers')->value('id');
        $this->expectQueryFailure(fn () => DB::table('inventory_cost_layers')->where('id', $layerId)->update(['total_cost' => 1200]));
        $this->expectQueryFailure(fn () => DB::table('inventory_cost_layers')->where('id', $layerId)->delete());
    }

    private function createItem(
        string $name,
        UnitFamily $family,
        string $baseCode,
        int $precision,
        bool $tracksStock = true,
    ): InventoryItem {
        $category = app(CreateInventoryCategory::class)->execute($name.' category');

        return app(CreateInventoryItem::class)->execute(new InventoryItemData(
            $category->id, $name, $tracksStock, $family,
            $this->unit($baseCode)->id, $this->unit($baseCode)->id, $precision,
        ));
    }

    private function unit(string $code): InventoryUnit
    {
        return InventoryUnit::query()->where('code', $code)->firstOrFail();
    }

    private function insertTransaction(int $adminId, string $sourceId): int
    {
        return DB::table('inventory_transactions')->insertGetId([
            'type' => 'adjustment', 'effective_date' => '2026-09-02',
            'source_type' => 'adjustment', 'source_id' => $sourceId,
            'created_by' => $adminId, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @param Closure(): mixed $callback */
    private function expectQueryFailure(Closure $callback): void
    {
        try {
            DB::transaction($callback);
            $this->fail('Expected PostgreSQL to reject the write.');
        } catch (QueryException $exception) {
            $this->assertContains($exception->errorInfo[0], ['23514', '23505', '55000']);
        }
    }
}
