<?php

namespace Tests\Feature\Inventory;

use App\Modules\Identity\Enums\StaffProfile;
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
use App\Modules\Inventory\Models\InventoryTransaction;
use App\Modules\Inventory\Models\InventoryUnit;
use App\Modules\Inventory\Queries\InventoryStockBalanceQuery;
use App\Modules\Inventory\Queries\InventoryValuationReadModel;
use App\Modules\Inventory\Support\DecimalMath;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class InventoryFoundationRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_capabilities_are_explicit_and_do_not_infer_classification_from_names(): void
    {
        $category = app(CreateInventoryCategory::class)->execute('Packaging');
        $unit = InventoryUnit::query()->where('code', 'unit')->sole();
        $item = app(CreateInventoryItem::class)->execute(new InventoryItemData(
            $category->id, 'Relleno', true, UnitFamily::Count, $unit->id, $unit->id, 0,
            kind: 'semi_finished', recipeInputEnabled: true, productionOutputEnabled: true,
            orderConsumptionEnabled: true,
        ));
        $this->assertSame('semi_finished', $item->kind);
        $this->assertTrue($item->recipe_input_enabled);
        $this->assertTrue($item->production_output_enabled);
        $this->assertTrue($item->order_consumption_enabled);
        $this->assertSame(0, DB::table('inventory_stock_movements')->count());
    }

    public function test_unknown_kind_remains_pending_and_invalid_kinds_are_rejected(): void
    {
        $item = $this->item();
        $this->assertNull($item->kind);
        $this->assertFalse($item->recipe_input_enabled);
        $this->expectException(ValidationException::class);
        app(CreateInventoryItem::class)->execute(new InventoryItemData(
            $item->inventory_category_id, 'Invalid kind', true, UnitFamily::Weight,
            $item->base_unit_id, $item->default_input_unit_id, 3, kind: 'pizza',
        ));
    }

    public function test_postgresql_rejects_invalid_kind_and_reference_mass(): void
    {
        $item = $this->item();
        foreach ([['kind' => 'pizza'], ['reference_mass_grams_per_unit' => '60']] as $attributes) {
            try {
                DB::transaction(fn () => DB::table('inventory_items')->where('id', $item->id)->update($attributes));
                $this->fail('Expected an inventory classification constraint violation.');
            } catch (QueryException $exception) {
                $this->assertSame('23514', $exception->errorInfo[0]);
            }
        }
    }

    public function test_valuation_distinguishes_known_zero_unknown_partial_negative_and_covered_stock(): void
    {
        $valuation = app(InventoryValuationReadModel::class);
        foreach ([
            [true, '0', '0', 0, 'zero', 0],
            [false, '0', '0', 0, 'zero', 0],
            [true, '1000', '0', 0, 'unknown', null],
            [true, '2000', '1000', 14500, 'partial', null],
            [true, '-1000', '1000', 14500, 'negative', null],
            [true, '1000', '1000', 14500, 'fully_valued', 14500],
            [true, '1000', '1000', 0, 'fully_valued', 0],
        ] as [$tracksStock, $stock, $quantity, $cost, $coverage, $value]) {
            $this->assertSame(['coverage' => $coverage, 'value' => $value], $valuation->classify($tracksStock, $stock, $quantity, $cost));
        }
    }

    public function test_valuation_is_current_integer_pesos_and_never_rewrites_history(): void
    {
        $item = $this->item();
        $admin = User::factory()->administrator()->create();
        $transaction = $this->record($item, $admin, '1000', 14500, 'receipt');
        $valuation = app(InventoryValuationReadModel::class)->summarize(app(InventoryStockBalanceQuery::class)->forItems());
        $this->assertSame(14500, $valuation['by_item'][$item->id]['value']);
        $this->assertSame(14500, $valuation['summary']['confident_value']);
        $this->record($item, $admin, '1000', null, 'unvalued');
        $valuation = app(InventoryValuationReadModel::class)->summarize(app(InventoryStockBalanceQuery::class)->forItems());
        $this->assertSame('partial', $valuation['by_item'][$item->id]['coverage']);
        $this->assertNull($valuation['by_item'][$item->id]['value']);
        $this->assertSame(0, $valuation['summary']['confident_value']);
        $this->assertSame(14500, $transaction->fresh()->costLayers->sole()->total_cost);
        $this->assertSame('1000.000000', $transaction->fresh()->movements->sole()->quantity);
    }

    public function test_foundation_admin_does_not_grant_waiter_or_cashier_access(): void
    {
        $this->withoutVite();
        foreach ([StaffProfile::Waiter, StaffProfile::Cashier] as $profile) {
            $staff = User::factory()->create(['is_admin' => false, 'staff_profile' => $profile]);
            $this->actingAs($staff)->get('/admin/inventory')->assertForbidden();
            $this->actingAs($staff)->post('/admin/inventory/categories', ['name' => 'Denied'])->assertForbidden();
        }
        $this->assertDatabaseCount('inventory_categories', 0);
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin)->get('/admin/inventory')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('admin/inventory/index')->has('valuation')->where('items', []));
    }

    public function test_exact_decimals_and_integer_rounding_do_not_use_floating_point(): void
    {
        $this->assertSame('99999999999999.000001', DecimalMath::normalize('99999999999999.000001', 6));
        $this->assertSame('1000.125000', DecimalMath::multiply('1.000125', '1000', 6));
        $this->assertSame(8325, DecimalMath::roundIntegerTimesRatio(16650, '1', '2'));
        $this->assertSame([1, 1, 0], DecimalMath::allocateIntegerByRatios(2, ['1', '1', '1']));
        $this->expectException(\InvalidArgumentException::class);
        DecimalMath::normalize('0.0000001', 6);
    }

    private function item(): InventoryItem
    {
        $category = app(CreateInventoryCategory::class)->execute('Ingredients');
        $unit = InventoryUnit::query()->where('code', 'g')->sole();

        return app(CreateInventoryItem::class)->execute(new InventoryItemData(
            $category->id, 'Roast beef', true, UnitFamily::Weight, $unit->id, $unit->id, 3,
        ));
    }

    private function record(InventoryItem $item, User $admin, string $quantity, ?int $cost, string $source): InventoryTransaction
    {
        return app(RecordInventoryTransaction::class)->execute(new InventoryTransactionData(
            InventoryTransactionType::Adjustment,
            CarbonImmutable::parse('2026-10-06'), [new InventoryMovementData($item->id, $quantity, $cost)],
            InventorySourceType::Adjustment, $source,
        ), $admin->id);
    }
}
