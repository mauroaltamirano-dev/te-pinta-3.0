<?php

namespace Tests\Feature\Management;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Actions\CreateInventoryCategory;
use App\Modules\Inventory\Actions\CreateInventoryItem;
use App\Modules\Inventory\Actions\CreatePurchasePresentation;
use App\Modules\Inventory\Actions\RecordInventoryTransaction;
use App\Modules\Inventory\Data\InventoryItemData;
use App\Modules\Inventory\Data\InventoryMovementData;
use App\Modules\Inventory\Data\InventoryTransactionData;
use App\Modules\Inventory\Data\PurchasePresentationData;
use App\Modules\Inventory\Enums\InventorySourceType;
use App\Modules\Inventory\Enums\InventoryTransactionType;
use App\Modules\Inventory\Enums\UnitFamily;
use App\Modules\Inventory\Models\InventoryCostLayer;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryUnit;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Management\Actions\CreatePurchase;
use App\Modules\Management\Actions\ReverseFinancialTransaction;
use App\Modules\Management\Actions\ReversePurchase;
use App\Modules\Management\Data\PurchaseData;
use App\Modules\Management\Data\PurchaseLineData;
use App\Modules\Management\Data\ReversalData;
use App\Modules\Management\Enums\FinancialTransactionType;
use App\Modules\Management\Enums\PurchasePriceMode;
use App\Modules\Management\Models\FinancialTransaction;
use App\Modules\Management\Models\LedgerEntry;
use App\Modules\Management\Models\Purchase;
use App\Modules\Management\Models\Wallet;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

final class PurchaseRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_simple_purchase_normalizes_units_and_coordinates_financial_inventory_and_cost(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Bank']);
        $beef = $this->item('Roast beef', UnitFamily::Weight, 'g', 'kg', 3);

        $purchase = $this->create($admin, $wallet, [new PurchaseLineData(
            $beef->id, null, $this->unit('kg')->id, '4', PurchasePriceMode::Total, 40000,
        )]);

        $this->assertSame(40000, $purchase->subtotal);
        $this->assertSame(40000, $purchase->total);
        $this->assertSame(FinancialTransactionType::Purchase, $purchase->financialTransaction->type);
        $this->assertSame(-40000, (int) $purchase->financialTransaction->entries->sole()->amount);
        $this->assertSame('4000.000000', $purchase->lines->sole()->base_quantity);
        $this->assertSame('4000.000000', StockMovement::query()->sole()->quantity);
        $this->assertSame(40000, InventoryCostLayer::query()->sole()->total_cost);
    }

    public function test_multi_line_presentation_unit_price_shipping_and_non_stockable_line_are_normalized(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Cash']);
        $egg = $this->item('Egg', UnitFamily::Count, 'unit', 'unit', 0);
        $tray = app(CreatePurchasePresentation::class)->execute(new PurchasePresentationData($egg->id, 'Tray', '30', $this->unit('unit')->id));
        $service = $this->item('Delivery material', UnitFamily::Count, 'unit', 'unit', 0, false);

        $purchase = $this->create($admin, $wallet, [
            new PurchaseLineData($egg->id, $tray->id, null, '2', PurchasePriceMode::Unit, 6500),
            new PurchaseLineData($service->id, null, $this->unit('unit')->id, '1', PurchasePriceMode::Total, 1000),
        ], shipping: 500);

        $this->assertSame(14000, $purchase->subtotal);
        $this->assertSame(14500, $purchase->total);
        $this->assertSame(-14500, (int) LedgerEntry::query()->sole()->amount);
        $this->assertDatabaseCount('inventory_transactions', 1);
        $this->assertDatabaseCount('inventory_stock_movements', 1);
        $this->assertDatabaseCount('inventory_cost_layers', 1);
        $this->assertSame('60.000000', $purchase->lines->firstWhere('inventory_item_id', $egg->id)->base_quantity);
        $this->assertNull($purchase->lines->firstWhere('inventory_item_id', $service->id)->stock_movement_id);
        $this->assertSame(13000, InventoryCostLayer::query()->sole()->total_cost);
    }

    public function test_non_stockable_only_purchase_has_no_inventory_transaction(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Cash']);
        $item = $this->item('Occasional packaging', UnitFamily::Count, 'unit', 'unit', 0, false);

        $purchase = $this->create($admin, $wallet, [new PurchaseLineData(
            $item->id, null, $this->unit('unit')->id, '2', PurchasePriceMode::Total, 2000,
        )]);

        $this->assertNull($purchase->inventory_transaction_id);
        $this->assertDatabaseCount('inventory_transactions', 0);
        $this->assertDatabaseCount('inventory_stock_movements', 0);
        $this->assertDatabaseCount('inventory_cost_layers', 0);
    }

    public function test_idempotent_retry_returns_same_aggregate_and_conflicting_reuse_is_rejected(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Cash']);
        $item = $this->item('Onion', UnitFamily::Weight, 'g', 'kg', 3);
        $key = 'd91aa0c6-07fb-4b3c-809f-9b7293ebf23a';
        $data = $this->data($wallet, [new PurchaseLineData($item->id, null, $this->unit('kg')->id, '20', PurchasePriceMode::Total, 30000)], key: $key);

        $first = app(CreatePurchase::class)->execute($data, $admin->id);
        $retry = app(CreatePurchase::class)->execute($data, $admin->id);
        $this->assertSame($first->id, $retry->id);
        $this->assertDatabaseCount('purchases', 1);
        $this->assertDatabaseCount('financial_transactions', 1);
        $this->assertDatabaseCount('inventory_transactions', 1);

        try {
            app(CreatePurchase::class)->execute($this->data($wallet, [new PurchaseLineData(
                $item->id, null, $this->unit('kg')->id, '21', PurchasePriceMode::Total, 30000,
            )], key: $key), $admin->id);
            $this->fail('Expected conflicting idempotency reuse.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('idempotency_key', $exception->errors());
        }
    }

    public function test_failure_after_financial_and_inventory_writes_rolls_back_everything(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Cash']);
        $item = $this->item('Potato', UnitFamily::Weight, 'g', 'kg', 3);
        $event = 'eloquent.creating: '.Purchase::class;
        Event::listen($event, static fn (): never => throw new RuntimeException('purchase failure'));

        try {
            $this->create($admin, $wallet, [new PurchaseLineData($item->id, null, $this->unit('kg')->id, '2', PurchasePriceMode::Total, 5000)]);
            $this->fail('Expected failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('purchase failure', $exception->getMessage());
        } finally {
            Event::forget($event);
        }

        foreach (['purchases', 'purchase_lines', 'financial_transactions', 'ledger_entries', 'inventory_transactions', 'inventory_stock_movements', 'inventory_cost_layers'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_purchase_reversal_compensates_money_stock_and_cost_without_mutating_history(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Bank']);
        $item = $this->item('Mozzarella', UnitFamily::Weight, 'g', 'kg', 3);
        $purchase = $this->create($admin, $wallet, [new PurchaseLineData($item->id, null, $this->unit('kg')->id, '4', PurchasePriceMode::Total, 40000)]);
        $originalMovement = StockMovement::query()->sole();
        $originalLayer = InventoryCostLayer::query()->sole();

        $reversal = app(ReversePurchase::class)->execute($purchase, new ReversalData(CarbonImmutable::parse('2026-09-03'), 'Duplicate invoice'), $admin->id);

        $this->assertSame(0, (int) $wallet->ledgerEntries()->sum('amount'));
        $this->assertSame('0.000000', (string) StockMovement::query()->where('inventory_item_id', $item->id)->sum('quantity'));
        $this->assertDatabaseHas('inventory_stock_movements', ['id' => $originalMovement->id, 'quantity' => '4000.000000']);
        $this->assertDatabaseHas('inventory_cost_layers', ['id' => $originalLayer->id, 'total_cost' => 40000]);
        $compensation = InventoryCostLayer::query()->where('reversal_of_id', $originalLayer->id)->sole();
        $this->assertSame(-40000, $compensation->total_cost);
        $this->assertSame('-4000.000000', $compensation->quantity);
        $this->assertSame($purchase->inventory_transaction_id, $reversal->inventoryTransaction->reversal_of_id);
        $this->assertDatabaseCount('purchase_reversals', 1);

        $this->expectException(ValidationException::class);
        app(ReversePurchase::class)->execute($purchase, new ReversalData(CarbonImmutable::parse('2026-09-04'), 'Again'), $admin->id);
    }

    public function test_generic_financial_reversal_rejects_purchase(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Bank']);
        $item = $this->item('Tomato', UnitFamily::Weight, 'g', 'kg', 3);
        $purchase = $this->create($admin, $wallet, [new PurchaseLineData($item->id, null, $this->unit('kg')->id, '2', PurchasePriceMode::Total, 5000)]);

        $this->expectException(ValidationException::class);
        app(ReverseFinancialTransaction::class)->execute($purchase->financialTransaction, new ReversalData(CarbonImmutable::parse('2026-09-03'), 'Wrong flow'), $admin->id);
    }

    public function test_http_requires_admin_and_rejects_duplicate_items(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Cash']);
        $item = $this->item('Pepper', UnitFamily::Weight, 'g', 'kg', 3);
        $payload = ['idempotency_key' => '3717a00b-fb65-44d3-87a8-306f5fc58d34', 'wallet_id' => $wallet->id,
            'effective_date' => '2026-08-28', 'shipping_amount' => 0, 'lines' => [
                ['inventory_item_id' => $item->id, 'input_unit_id' => $this->unit('kg')->id, 'quantity' => '1', 'price_mode' => 'total', 'price_amount' => 1000],
                ['inventory_item_id' => $item->id, 'input_unit_id' => $this->unit('g')->id, 'quantity' => '500', 'price_mode' => 'total', 'price_amount' => 500],
            ]];

        $this->actingAs($user)->post('/admin/management/purchases', $payload)->assertForbidden();
        $this->actingAs($admin)->post('/admin/management/purchases', $payload)->assertSessionHasErrors('lines.1.inventory_item_id');
        $this->assertDatabaseCount('purchases', 0);
    }

    public function test_inventory_history_can_deep_link_to_the_purchase_transaction(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Cash']);
        $item = $this->item('Flour', UnitFamily::Weight, 'g', 'kg', 3);
        $purchase = $this->create($admin, $wallet, [new PurchaseLineData(
            $item->id, null, $this->unit('kg')->id, '1', PurchasePriceMode::Total, 1200,
        )]);

        $this->actingAs($admin)->get('/admin/inventory?transaction_id='.$purchase->inventory_transaction_id)
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('filters.transaction_id', (string) $purchase->inventory_transaction_id)
            ->has('transactions.data', 1)
            ->where('transactions.data.0.id', $purchase->inventory_transaction_id));
    }

    public function test_postgresql_rejects_orphan_purchase_financial_and_inventory_sources(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Cash']);

        try {
            DB::transaction(function () use ($admin, $wallet): void {
                $transaction = FinancialTransaction::query()->create(['type' => FinancialTransactionType::Purchase,
                    'effective_date' => '2026-09-01', 'created_by' => $admin->id]);
                $transaction->entries()->create(['wallet_id' => $wallet->id, 'amount' => -100]);
                DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
            });
            $this->fail('Expected orphan purchase financial transaction to fail.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('requires its Purchase source', $exception->getMessage());
        }

        $item = $this->item('Oil', UnitFamily::Volume, 'ml', 'L', 3);
        try {
            DB::transaction(function () use ($admin, $item): void {
                app(RecordInventoryTransaction::class)->execute(new InventoryTransactionData(
                    InventoryTransactionType::PurchaseReceipt, CarbonImmutable::parse('2026-09-01'),
                    [new InventoryMovementData($item->id, '1000', 1000)], InventorySourceType::Purchase,
                    '9b8ed0c5-6e58-45ce-b347-f982f8a4bb84',
                ), $admin->id);
                DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
            });
            $this->fail('Expected orphan purchase inventory transaction to fail.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('requires its Purchase source', $exception->getMessage());
        }
    }

    public function test_purchase_pages_and_snapshots_remain_operable_after_catalog_edits(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Cash']);
        $item = $this->item('Original onion', UnitFamily::Weight, 'g', 'kg', 3);
        $purchase = $this->create($admin, $wallet, [new PurchaseLineData($item->id, null, $this->unit('kg')->id, '1.25', PurchasePriceMode::Total, 2150)]);
        $item->update(['name' => 'Renamed onion']);
        $this->actingAs($admin)->get('/admin/management/purchases')->assertOk()->assertInertia(fn (Assert $p) => $p->component('admin/management/purchases/index')->where('purchases.total', 1));
        $this->get('/admin/management/purchases/create')->assertOk()->assertInertia(fn (Assert $p) => $p->component('admin/management/purchases/create'));
        $this->get('/admin/management/purchases/'.$purchase->id)->assertOk()->assertInertia(fn (Assert $p) => $p->component('admin/management/purchases/show')->where('purchase.lines.0.item_name', 'Original onion')->where('purchase.lines.0.base_quantity', '1250.000000'));
        $this->get('/admin/purchases')->assertNotFound();
    }

    public function test_non_stockable_purchase_reversal_and_known_free_line_preserve_truth(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Cash']);
        $item = $this->item('Service', UnitFamily::Count, 'unit', 'unit', 0, false);
        $purchase = $this->create($admin, $wallet, [new PurchaseLineData($item->id, null, $this->unit('unit')->id, '1', PurchasePriceMode::Total, 0)], 300);
        $reversal = app(ReversePurchase::class)->execute($purchase, new ReversalData(CarbonImmutable::parse('2026-09-03'), 'Duplicate'), $admin->id);
        $this->assertNull($reversal->inventory_transaction_id);
        $this->assertSame(0, (int) $wallet->ledgerEntries()->sum('amount'));
        $this->assertDatabaseCount('inventory_transactions', 0);
        $this->actingAs($admin)->get('/admin/management/purchases?status=confirmed')->assertInertia(fn (Assert $p) => $p->where('purchases.total', 0));
        $this->get('/admin/management/purchases?status=reversed')->assertInertia(fn (Assert $p) => $p->where('purchases.total', 1));
    }

    public function test_archived_wallet_and_inactive_item_reject_without_partial_writes(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Archived', 'archived_at' => now()]);
        $item = $this->item('Salt', UnitFamily::Weight, 'g', 'kg', 3);
        try {
            $this->create($admin, $wallet, [new PurchaseLineData($item->id, null, $this->unit('kg')->id, '1', PurchasePriceMode::Total, 100)]);
            $this->fail('Expected archived wallet rejection');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('wallet_id', $e->errors());
        }
        $wallet->update(['archived_at' => null]);
        $item->update(['is_active' => false]);
        $this->expectException(ValidationException::class);
        $this->create($admin, $wallet, [new PurchaseLineData($item->id, null, $this->unit('kg')->id, '1', PurchasePriceMode::Total, 100)]);
    }

    public function test_fractional_unit_price_cannot_round_money(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Cash']);
        $item = $this->item('Onion', UnitFamily::Weight, 'g', 'kg', 3);
        $this->expectException(ValidationException::class);
        $this->create($admin, $wallet, [new PurchaseLineData($item->id, null, $this->unit('kg')->id, '0.5', PurchasePriceMode::Unit, 2151)]);
    }

    public function test_direct_sql_cannot_reverse_purchase_money_without_coordinated_source(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Cash']);
        $item = $this->item('Rice', UnitFamily::Weight, 'g', 'kg', 3);
        $purchase = $this->create($admin, $wallet, [new PurchaseLineData($item->id, null, $this->unit('kg')->id, '1', PurchasePriceMode::Total, 300)]);
        try {
            DB::transaction(function () use ($purchase, $admin, $wallet): void {
                $r = FinancialTransaction::query()->create(['type' => 'reversal', 'reversal_of_id' => $purchase->financial_transaction_id, 'created_by' => $admin->id, 'effective_date' => '2026-09-03']);
                $r->entries()->create(['wallet_id' => $wallet->id, 'amount' => 300]);
                DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
            });
            $this->fail('Expected coordinated source constraint');
        } catch (QueryException $e) {
            $this->assertSame('23514', $e->errorInfo[0]);
        }
        $this->assertDatabaseCount('financial_transactions', 1);
    }

    public function test_direct_sql_cannot_append_money_to_purchase_or_its_reversal(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Cash']);
        $other = Wallet::query()->create(['name' => 'Other']);
        $item = $this->item('Rice', UnitFamily::Weight, 'g', 'kg', 3);
        $p = $this->create($admin, $wallet, [new PurchaseLineData($item->id, null, $this->unit('kg')->id, '1', PurchasePriceMode::Total, 300)]);
        $r = app(ReversePurchase::class)->execute($p, new ReversalData(CarbonImmutable::parse('2026-09-03'), 'Duplicate'), $admin->id);
        foreach ([$p->financial_transaction_id, $r->financial_transaction_id] as $id) {
            try {
                DB::transaction(function () use ($id, $other): void {
                    LedgerEntry::query()->create(['financial_transaction_id' => $id, 'wallet_id' => $other->id, 'amount' => 1]);
                    DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
                });
                $this->fail('Expected exact entry cardinality');
            } catch (QueryException $e) {
                $this->assertSame('23514', $e->errorInfo[0]);
            }
        }
    }

    public function test_purchase_history_mutation_is_rejected_by_database(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Cash']);
        $item = $this->item('Rice', UnitFamily::Weight, 'g', 'kg', 3);
        $p = $this->create($admin, $wallet, [new PurchaseLineData($item->id, null, $this->unit('kg')->id, '1', PurchasePriceMode::Total, 300)]);
        foreach ([fn () => $p->update(['total' => 301]), fn () => $p->lines->sole()->update(['item_name' => 'Forgery']), fn () => $p->delete()] as $mutation) {
            try {
                DB::transaction($mutation);
                $this->fail('Expected immutable history');
            } catch (QueryException $e) {
                $this->assertSame('55000', $e->errorInfo[0]);
            }
        }
    }

    public function test_waiter_cashier_and_non_admin_cannot_operate_any_purchase_route(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Cash']);
        $item = $this->item('Rice', UnitFamily::Weight, 'g', 'kg', 3);
        $p = $this->create($admin, $wallet, [new PurchaseLineData($item->id, null, $this->unit('kg')->id, '1', PurchasePriceMode::Total, 300)]);
        foreach ([null, 'waiter', 'cashier'] as $profile) {
            $user = ($profile === null ? User::factory() : User::factory()->withRole(Role::from($profile)))->create();
            $this->actingAs($user)->get('/admin/management/purchases')->assertForbidden();
            $this->get('/admin/management/purchases/create')->assertForbidden();
            $this->get('/admin/management/purchases/'.$p->id)->assertForbidden();
            $this->post('/admin/management/purchases', [])->assertForbidden();
            $this->post('/admin/management/purchases/'.$p->id.'/reverse', [])->assertForbidden();
            $this->get('/admin/management/transactions/'.$p->financial_transaction_id.'/attachments')->assertForbidden();
        }
    }

    public function test_presentation_locks_have_deterministic_id_order(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Cash']);
        $item = $this->item('Egg', UnitFamily::Count, 'unit', 'unit', 0);
        $pack = app(CreatePurchasePresentation::class)->execute(new PurchasePresentationData($item->id, 'Tray', '30', $this->unit('unit')->id));
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        $this->create($admin, $wallet, [new PurchaseLineData($item->id, $pack->id, null, '1', PurchasePriceMode::Total, 300)]);
        $locks = array_values(array_filter($queries, fn ($sql) => str_contains($sql, 'inventory_purchase_presentations') && str_contains($sql, 'for update')));
        $this->assertNotEmpty($locks);
        $this->assertStringContainsString('order by "id" asc', $locks[0]);
    }

    public function test_purchase_creation_uses_full_transaction_identity_through_nested_savepoints(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Cash']);
        $item = $this->item('Rice', UnitFamily::Weight, 'g', 'kg', 3);
        $p = $this->create($admin, $wallet, [new PurchaseLineData($item->id, null, $this->unit('kg')->id, '1', PurchasePriceMode::Total, 300)]);
        $xid = DB::selectOne('SELECT pg_current_xact_id()::text AS value')->value;
        $this->assertSame($xid, DB::table('purchases')->where('id', $p->id)->value('creation_xid'));
    }

    public function test_existing_management_operating_outflow_and_purchase_filter_include_rich_purchase_reversal(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Cash']);
        $item = $this->item('Rice', UnitFamily::Weight, 'g', 'kg', 3);
        $p = $this->create($admin, $wallet, [new PurchaseLineData($item->id, null, $this->unit('kg')->id, '1', PurchasePriceMode::Total, 2150)], 300);
        $this->actingAs($admin)->get('/admin/management?from=2026-08-01&to=2026-09-30&type=purchase')->assertInertia(fn (Assert $page) => $page->where('summary.period_operating_outflow', 2450)->where('transactions.total', 1));
        app(ReversePurchase::class)->execute($p, new ReversalData(CarbonImmutable::parse('2026-09-03'), 'Duplicate'), $admin->id);
        $this->get('/admin/management?from=2026-08-01&to=2026-09-30&type=purchase')->assertInertia(fn (Assert $page) => $page->where('summary.period_operating_outflow', 0)->where('transactions.total', 2));
    }

    private function create(User $admin, Wallet $wallet, array $lines, int $shipping = 0): Purchase
    {
        return app(CreatePurchase::class)->execute($this->data($wallet, $lines, $shipping), $admin->id);
    }

    private function data(Wallet $wallet, array $lines, int $shipping = 0, string $key = '024e31dc-90ed-44dd-933a-2afadbb2f57f'): PurchaseData
    {
        return new PurchaseData($key, $wallet->id, CarbonImmutable::parse('2026-08-28'), 'Supplier', 'Weekly purchase', $shipping, $lines);
    }

    private function item(string $name, UnitFamily $family, string $base, string $input, int $precision, bool $stock = true): InventoryItem
    {
        $category = app(CreateInventoryCategory::class)->execute($name.' category');

        return app(CreateInventoryItem::class)->execute(new InventoryItemData($category->id, $name, $stock, $family,
            $this->unit($base)->id, $this->unit($input)->id, $precision));
    }

    private function unit(string $code): InventoryUnit
    {
        return InventoryUnit::query()->where('code', $code)->firstOrFail();
    }
}
