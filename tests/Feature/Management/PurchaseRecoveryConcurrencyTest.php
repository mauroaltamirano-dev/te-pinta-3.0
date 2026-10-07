<?php

namespace Tests\Feature\Management;

use App\Core\Support\TestingDatabaseGuard;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\InventoryCategory;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryUnit;
use App\Modules\Management\Actions\CreatePurchase;
use App\Modules\Management\Actions\ReversePurchase;
use App\Modules\Management\Data\PurchaseData;
use App\Modules\Management\Data\PurchaseLineData;
use App\Modules\Management\Data\ReversalData;
use App\Modules\Management\Enums\PurchasePriceMode;
use App\Modules\Management\Models\Purchase;
use App\Modules\Management\Models\Wallet;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class PurchaseRecoveryConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TestingDatabaseGuard::ensureSafe(app()->environment(), DB::connection()->getDatabaseName());
        $this->beforeApplicationDestroyed(static function (): void {
            RefreshDatabaseState::$migrated = false;
        });
        $this->artisan('migrate:fresh')->assertExitCode(0);
        RefreshDatabaseState::$migrated = true;
        config(['database.connections.purchase_contender' => DB::connection()->getConfig()]);
        DB::connection('purchase_contender')->unprepared("SET lock_timeout='300ms'; SET statement_timeout='2s'");
    }

    public function test_pending_create_serializes_identical_key_then_retry_returns_one_aggregate(): void
    {
        [$data, $admin] = $this->fixture();
        $main = DB::getDefaultConnection();
        DB::beginTransaction();
        try {
            $p = app(CreatePurchase::class)->execute($data, $admin->id);
            DB::setDefaultConnection('purchase_contender');
            try {
                app(CreatePurchase::class)->execute($data, $admin->id);
                $this->fail('Expected pending key lock');
            } catch (QueryException $e) {
                $this->assertSame('55P03', $e->errorInfo[0]);
            } finally {
                DB::setDefaultConnection($main);
            }
            DB::commit();
            DB::setDefaultConnection('purchase_contender');
            $this->assertSame($p->id, app(CreatePurchase::class)->execute($data, $admin->id)->id);
            $this->assertDatabaseCount('purchases', 1);
            $this->assertDatabaseCount('ledger_entries', 1);
            $this->assertDatabaseCount('inventory_cost_layers', 1);
        } finally {
            DB::setDefaultConnection($main);
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            DB::purge('purchase_contender');
        }
    }

    public function test_uppercase_uuid_http_purchase_commits_canonical_identity_and_retries_once(): void
    {
        [$data, $admin] = $this->fixture('d91aa0c6-07fb-4b3c-809f-9b7293ebf23a');
        $payload = [
            'idempotency_key' => strtoupper($data->idempotencyKey),
            'wallet_id' => $data->walletId,
            'effective_date' => $data->effectiveDate->toDateString(),
            'supplier_name' => $data->supplierName,
            'shipping_amount' => $data->shippingAmount,
            'lines' => [[
                'inventory_item_id' => $data->lines[0]->inventoryItemId,
                'input_unit_id' => $data->lines[0]->inputUnitId,
                'quantity' => $data->lines[0]->quantity,
                'price_mode' => $data->lines[0]->priceMode->value,
                'price_amount' => $data->lines[0]->priceAmount,
            ]],
        ];
        $this->withoutExceptionHandling()->actingAs($admin)
            ->post(route('admin.management.purchases.store'), $payload)->assertRedirect();
        $purchase = Purchase::query()->sole();
        $this->assertSame($data->idempotencyKey, $purchase->idempotency_key);
        $this->assertSame($data->idempotencyKey, $purchase->inventoryTransaction->source_id);

        foreach ([$data->idempotencyKey, strtoupper($data->idempotencyKey)] as $key) {
            $payload['idempotency_key'] = $key;
            $this->post(route('admin.management.purchases.store'), $payload)
                ->assertRedirect(route('admin.management.purchases.show', $purchase));
        }
        $this->assertDatabaseCount('purchases', 1);
        $this->assertDatabaseCount('purchase_lines', 1);
        $this->assertDatabaseCount('financial_transactions', 1);
        $this->assertDatabaseCount('ledger_entries', 1);
        $this->assertDatabaseCount('inventory_transactions', 1);
        $this->assertDatabaseCount('inventory_stock_movements', 1);
        $this->assertDatabaseCount('inventory_cost_layers', 1);
        $this->assertSame(-2150, (int) DB::table('ledger_entries')->sum('amount'));
        $this->assertSame('1000.000000', (string) DB::table('inventory_stock_movements')->sum('quantity'));
    }

    /** @return array<string, array{bool}> */
    public static function uuidCaseOrders(): array
    {
        return ['lowercase then uppercase' => [false], 'uppercase then lowercase' => [true]];
    }

    #[DataProvider('uuidCaseOrders')]
    public function test_case_variant_retry_contends_on_same_advisory_lock_then_returns_one_aggregate(bool $uppercaseFirst): void
    {
        [$lowercase, $admin] = $this->fixture('d91aa0c6-07fb-4b3c-809f-9b7293ebf23a');
        $uppercase = new PurchaseData(strtoupper($lowercase->idempotencyKey), $lowercase->walletId,
            $lowercase->effectiveDate, $lowercase->supplierName, $lowercase->description,
            $lowercase->shippingAmount, $lowercase->lines);
        [$first, $retry] = $uppercaseFirst ? [$uppercase, $lowercase] : [$lowercase, $uppercase];
        $main = DB::getDefaultConnection();
        DB::beginTransaction();
        try {
            $purchase = app(CreatePurchase::class)->execute($first, $admin->id);
            DB::setDefaultConnection('purchase_contender');
            try {
                app(CreatePurchase::class)->execute($retry, $admin->id);
                $this->fail('Expected the same pending UUID advisory lock');
            } catch (QueryException $exception) {
                $this->assertSame('55P03', $exception->errorInfo[0]);
                $this->assertStringContainsString('pg_advisory_xact_lock', $exception->getSql());
            } finally {
                DB::setDefaultConnection($main);
            }
            DB::commit();
            DB::setDefaultConnection('purchase_contender');
            $replayed = app(CreatePurchase::class)->execute($retry, $admin->id);
            $this->assertSame($purchase->id, $replayed->id);
            $this->assertSame($lowercase->idempotencyKey, $replayed->idempotency_key);
            $this->assertSame($lowercase->idempotencyKey, $replayed->inventoryTransaction->source_id);
            $this->assertDatabaseCount('purchases', 1);
            $this->assertDatabaseCount('ledger_entries', 1);
            $this->assertDatabaseCount('inventory_cost_layers', 1);
            $this->assertSame(-2150, (int) DB::table('ledger_entries')->sum('amount'));
            $this->assertSame('1000.000000', (string) DB::table('inventory_stock_movements')->sum('quantity'));
        } finally {
            DB::setDefaultConnection($main);
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            DB::purge('purchase_contender');
        }
    }

    public function test_pending_reversal_serializes_then_second_reversal_is_rejected(): void
    {
        [$data, $admin] = $this->fixture();
        $p = app(CreatePurchase::class)->execute($data, $admin->id);
        $reason = new ReversalData(CarbonImmutable::parse('2026-10-07'), 'Duplicate');
        $main = DB::getDefaultConnection();
        DB::beginTransaction();
        try {
            app(ReversePurchase::class)->execute($p, $reason, $admin->id);
            DB::setDefaultConnection('purchase_contender');
            try {
                app(ReversePurchase::class)->execute($p, $reason, $admin->id);
                $this->fail('Expected pending purchase lock');
            } catch (QueryException $e) {
                $this->assertSame('55P03', $e->errorInfo[0]);
            } finally {
                DB::setDefaultConnection($main);
            }
            DB::commit();
            DB::setDefaultConnection('purchase_contender');
            try {
                app(ReversePurchase::class)->execute($p, $reason, $admin->id);
                $this->fail('Expected duplicate reversal rejection');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('purchase', $e->errors());
            }
            $this->assertDatabaseCount('purchase_reversals', 1);
            $this->assertSame(0, (int) DB::table('ledger_entries')->sum('amount'));
            $this->assertSame('0.000000', (string) DB::table('inventory_stock_movements')->sum('quantity'));
        } finally {
            DB::setDefaultConnection($main);
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            DB::purge('purchase_contender');
        }
    }

    public function test_committed_purchase_cannot_receive_new_line_even_if_zero_cost(): void
    {
        [$data, $admin] = $this->fixture();
        $p = app(CreatePurchase::class)->execute($data, $admin->id);
        $line = $p->lines->sole()->getAttributes();
        unset($line['id']);
        try {
            DB::table('purchase_lines')->insert($line);
            $this->fail('Expected append protection');
        } catch (QueryException $e) {
            $this->assertSame('55000', $e->errorInfo[0]);
        }
        $this->assertDatabaseCount('purchase_lines', 1);
    }

    /** @return array{PurchaseData, User} */
    private function fixture(?string $key = null): array
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Concurrency bank']);
        $category = InventoryCategory::query()->create(['name' => 'Concurrency ingredients', 'is_active' => true]);
        $unit = InventoryUnit::query()->where('code', 'g')->sole();
        $item = InventoryItem::query()->create(['inventory_category_id' => $category->id, 'name' => 'Onion', 'tracks_stock' => true, 'unit_family' => 'weight', 'base_unit_id' => $unit->id, 'default_input_unit_id' => $unit->id, 'quantity_precision' => 3, 'is_active' => true]);

        return [new PurchaseData($key ?? (string) Str::uuid(), $wallet->id, CarbonImmutable::parse('2026-10-07'), 'Supplier', null, 0, [new PurchaseLineData($item->id, null, $unit->id, '1000', PurchasePriceMode::Total, 2150)]), $admin];
    }
}
