<?php

namespace Tests\Feature\Inventory;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Actions\CreateInventoryAdjustment;
use App\Modules\Inventory\Actions\PreviewInventoryAdjustment;
use App\Modules\Inventory\Actions\RecordInventoryTransaction;
use App\Modules\Inventory\Actions\ReverseInventoryAdjustment;
use App\Modules\Inventory\Data\InventoryAdjustmentData;
use App\Modules\Inventory\Data\InventoryMovementData;
use App\Modules\Inventory\Data\InventoryTransactionData;
use App\Modules\Inventory\Enums\InventorySourceType;
use App\Modules\Inventory\Enums\InventoryTransactionType;
use App\Modules\Inventory\Models\InventoryAdjustment;
use App\Modules\Inventory\Models\InventoryCategory;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryTransaction;
use App\Modules\Inventory\Models\InventoryUnit;
use App\Modules\Inventory\Support\DecimalMath;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class AdjustmentRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_count_negative_positive_and_unchanged_preview(): void
    {
        [$a,$i,$u] = $this->fixture();
        $p = app(PreviewInventoryAdjustment::class)->execute($this->data($i, $u, 'count_correction', '10.5'));
        $this->assertSame('12000.000000', $p['current_stock']);
        $this->assertSame('-1500.000000', $p['delta']);
        $this->assertSame('10500.000000', $p['result_stock']);
        $this->assertSame(-6000, $p['cost_total']);
        $this->assertDatabaseCount('inventory_adjustments', 0);
        $b = $this->create($a, $this->data($i, $u, 'count_correction', '10.5'));
        $this->assertSame('-1500.000000', $b->delta);
        $this->assertSame('12000.000000', $b->stock_before);
        $this->assertSame('10500.000000', $b->physical_count);
        $p = app(PreviewInventoryAdjustment::class)->execute($this->data($i, $u, 'count_correction', '10.5'));
        $this->assertFalse($p['can_confirm']);
        $this->assertSame('0.000000', $p['delta']);
        try {
            $this->create($a, $this->data($i, $u, 'count_correction', '10.5'));
            $this->fail('No-change count creates no movement');
        } catch (ValidationException) {
        }
        $plus = $this->create($a, $this->data($i, $u, 'count_correction', '13'));
        $this->assertSame('2500.000000', $plus->delta);
        $this->assertSame(10000, $plus->cost_total);
        $this->assertSame('estimated', $plus->cost_status);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_preview_is_not_authority_when_stock_changes(): void
    {
        [$a,$i,$u] = $this->fixture();
        $d = $this->data($i, $u, 'count_correction', '10');
        $this->assertSame('-2000.000000', app(PreviewInventoryAdjustment::class)->execute($d)['delta']);
        $this->receipt($a, $i, '1000', 4000);
        $b = $this->create($a, $d);
        $this->assertSame('13000.000000', $b->stock_before);
        $this->assertSame('-3000.000000', $b->delta);
        $this->assertSame('10000.000000', $this->stock($i));
    }

    public function test_each_loss_and_manual_negative_uses_historical_allocator_without_cash(): void
    {
        [$a,$i,$u] = $this->fixture();
        $before = DB::table('financial_transactions')->count();
        $ledgerBefore = DB::table('ledger_entries')->count();
        foreach (['waste', 'damage', 'expiry', 'manual_adjustment'] as $type) {
            $b = $this->create($a, $this->data($i, $u, $type, $type === 'manual_adjustment' ? '-2' : '2'));
            $this->assertSame('-2000.000000', $b->delta);
            $this->assertSame(-8000, $b->cost_total);
            $this->assertSame('allocated', $b->cost_status);
            $this->assertSame('moving_weighted_average', $b->cost_method);
            $this->assertSame(-8000, $b->transaction->costLayers->sole()->total_cost);
        }
        $this->assertSame($before, DB::table('financial_transactions')->count());
        $this->assertSame($ledgerBefore, DB::table('ledger_entries')->count());
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_positive_explicit_free_reliable_and_unknown_are_distinct(): void
    {
        [$a,$i,$u] = $this->fixture();
        foreach ([[10000, 'explicit', 10000], [0, 'explicit', 0], [null, 'estimated', 8000]] as [$cost,$status,$expected]) {
            $item = $this->newItem('Cost '.Str::uuid());
            if ($cost === null) {
                $this->receipt($a, $item, '1000', 4000);
            }
            $b = $this->create($a, $this->data($item, $u, 'manual_adjustment', '2', $cost));
            $this->assertSame($status, $b->cost_status);
            $this->assertSame($expected, $b->cost_total);
            $this->assertSame($expected, $b->transaction->costLayers->sole()->total_cost);
        }
        $item = $this->newItem('Unknown');
        $b = $this->create($a, $this->data($item, $u, 'manual_adjustment', '2'));
        $this->assertNull($b->cost_total);
        $this->assertSame('unknown', $b->cost_coverage);
        $this->assertCount(0, $b->transaction->costLayers);
        $negative = $this->create($a, $this->data($item, $u, 'waste', '1'));
        $this->assertNull($negative->cost_total);
        $this->assertSame('partial_valuation', $negative->cost_fallback);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_existing_unreliable_cost_is_not_used_for_positive_estimate_and_propagates_loss(): void
    {
        [$a, $i, $u] = $this->fixture();
        $i = $this->newItem('Unreliable stock');
        $t = InventoryTransaction::query()->create(['type' => 'adjustment', 'source_type' => 'adjustment', 'source_id' => (string) Str::uuid(), 'effective_date' => '2026-10-01', 'created_by' => $a->id]);
        $m = $t->movements()->create(['inventory_item_id' => $i->id, 'quantity' => '1000', 'unit_id' => $i->base_unit_id]);
        $t->costLayers()->create(['inventory_item_id' => $i->id, 'stock_movement_id' => $m->id, 'quantity' => '1000', 'total_cost' => 4000, 'effective_date' => '2026-10-01', 'valuation_warning' => 'unreliable_input']);
        $p = app(PreviewInventoryAdjustment::class)->execute($this->data($i, $u, 'manual_adjustment', '1'));
        $this->assertNull($p['cost_total']);
        $this->assertSame('unreliable', $p['current_coverage']);
        $b = $this->create($a, $this->data($i, $u, 'waste', '0.5'));
        $this->assertSame(-2000, $b->cost_total);
        $this->assertSame('unreliable', $b->cost_coverage);
        $this->assertSame('unreliable_input', $b->cost_fallback);
        $r = app(ReverseInventoryAdjustment::class)->execute($b->id, CarbonImmutable::parse('2026-10-12'), 'Restore', $a->id);
        $this->assertSame('unreliable_input', $r->transaction->costLayers->sole()->valuation_warning);
        $positive = $this->create($a, $this->data($i, $u, 'manual_adjustment', '1'));
        $this->assertNull($positive->cost_total);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_negative_fallback_warns_and_unknown_never_becomes_zero(): void
    {
        [$a,$i,$u] = $this->fixture();
        $b = $this->create($a, $this->data($i, $u, 'waste', '14'));
        $this->assertSame(-56000, $b->cost_total);
        $this->assertSame('unreliable', $b->cost_coverage);
        $this->assertSame('negative_stock_latest_basis', $b->cost_fallback);
        $unknown = $this->create($a, $this->data($this->newItem('No cost'), $u, 'waste', '1'));
        $this->assertNull($unknown->cost_total);
        $this->assertSame('no_cost_history', $unknown->cost_fallback);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_reversal_is_exact_historical_known_and_unknown_and_single(): void
    {
        [$a,$i,$u] = $this->fixture();
        foreach ([$i, $this->newItem('Unknown reverse')] as $item) {
            $b = $this->create($a, $this->data($item, $u, 'waste', '2'));
            $this->receipt($a, $item, '1000', 6000);
            $r = app(ReverseInventoryAdjustment::class)->execute($b->id, CarbonImmutable::parse('2026-10-12'), 'Duplicate', $a->id);
            $this->assertSame('2000.000000', $r->delta);
            $this->assertSame($b->id, $r->reversal_of_id);
            $this->assertSame($b->cost_total === null ? null : -$b->cost_total, $r->cost_total);
            $this->assertSame($b->inventory_transaction_id, $r->transaction->reversal_of_id);
            $this->assertSame($b->cost_total === null ? 0 : 1, $r->transaction->costLayers->count());
            if ($b->cost_total !== null) {
                $this->assertSame($b->transaction->costLayers->sole()->id, $r->transaction->costLayers->sole()->reversal_of_id);
            }
            try {
                app(ReverseInventoryAdjustment::class)->execute($b->id, CarbonImmutable::parse('2026-10-12'), 'Again', $a->id);
                $this->fail('Double reversal');
            } catch (ValidationException) {
            }
            $this->assertSame(1, $b->reversal()->count());
        }
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_canonical_idempotency_and_conflicting_payload(): void
    {
        [$a,$i,$u] = $this->fixture();
        $key = (string) Str::uuid();
        $b = $this->create($a, $this->data($i, $u, 'waste', '1', null, strtoupper($key)));
        $same = $this->create($a, $this->data($i, $u, 'waste', '1.000000', null, $key));
        $this->assertSame($b->id, $same->id);
        $this->assertSame($key, $b->idempotency_key);
        $this->expectException(ValidationException::class);
        $this->create($a, $this->data($i, $u, 'waste', '2', null, $key));
    }

    public function test_invalid_quantity_precision_loss_sign_cost_and_item_rejected(): void
    {
        [$a,$i,$u] = $this->fixture();
        foreach ([['waste', '0', null], ['damage', '-1', null], ['expiry', '1', 100], ['manual_adjustment', '-1', 10], ['count_correction', '-1', null], ['manual_adjustment', '0.0000001', null], ['manual_adjustment', '1', -1]] as [$type,$q,$cost]) {
            try {
                $this->create($a, $this->data($i, $u, $type, $q, $cost));
                $this->fail('Invalid input');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
        $i->update(['is_active' => false]);
        $this->expectException(ValidationException::class);
        $this->create($a, $this->data($i, $u, 'waste', '1'));
    }

    public function test_administrator_preview_store_detail_reverse_and_staff_forbidden(): void
    {
        [$a,$i,$u] = $this->fixture();
        $values = ['inventory_item_id' => $i->id, 'unit_id' => $u->id, 'type' => 'count_correction', 'quantity' => '10.5', 'effective_date' => '2026-10-11', 'idempotency_key' => (string) Str::uuid(), 'note' => 'Physical count'];
        $this->actingAs($a)->postJson('/admin/inventory/adjustments/preview', $values)->assertOk()->assertJsonPath('data.delta', '-1500.000000');
        $this->post('/admin/inventory/adjustments', $values + ['delta' => '-9000'])->assertSessionHasErrors('delta');
        $this->post('/admin/inventory/adjustments', $values)->assertRedirect();
        $b = InventoryAdjustment::query()->sole();
        $this->get('/admin/inventory/adjustments/'.$b->id)->assertOk();
        $this->post('/admin/inventory/adjustments/'.$b->id.'/reverse', ['effective_date' => '2026-10-12', 'note' => 'Duplicate'])->assertRedirect();
        foreach (['waiter', 'cashier'] as $profile) {
            $this->actingAs(User::factory()->withRole(Role::from($profile))->create());
            foreach (['/admin/inventory/adjustments', '/admin/inventory/adjustments/'.$b->id] as $path) {
                $this->get($path)->assertForbidden();
            }
            foreach (['/admin/inventory/adjustments', '/admin/inventory/adjustments/preview', '/admin/inventory/adjustments/'.$b->id.'/reverse'] as $path) {
                $this->post($path, [])->assertForbidden();
            }
        }
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    private function fixture(): array
    {
        $a = User::factory()->administrator()->create();
        $i = $this->newItem('Onion');
        $u = InventoryUnit::query()->where('code', 'kg')->sole();
        $this->receipt($a, $i, '12000', 48000);

        return [$a, $i, $u];
    }

    private function newItem(string $name): InventoryItem
    {
        $u = InventoryUnit::query()->where('code', 'g')->sole();
        $c = InventoryCategory::query()->firstOrCreate(['name' => 'Adjustment inputs']);

        return InventoryItem::query()->create(['inventory_category_id' => $c->id, 'name' => $name, 'tracks_stock' => true, 'unit_family' => 'weight', 'base_unit_id' => $u->id, 'default_input_unit_id' => $u->id, 'quantity_precision' => 3, 'is_active' => true, 'kind' => 'raw_material']);
    }

    private function data(InventoryItem $i, InventoryUnit $u, string $type, string $q, ?int $cost = null, ?string $key = null): InventoryAdjustmentData
    {
        return new InventoryAdjustmentData($i->id, $u->id, $type, $q, CarbonImmutable::parse('2026-10-11'), $key ?? (string) Str::uuid(), $cost, 'Inventory observation');
    }

    private function create(User $a, InventoryAdjustmentData $d): InventoryAdjustment
    {
        return app(CreateInventoryAdjustment::class)->execute($d, $a->id);
    }

    private function receipt(User $a, InventoryItem $i, string $q, ?int $cost): void
    {
        app(RecordInventoryTransaction::class)->execute(new InventoryTransactionData(InventoryTransactionType::Adjustment, CarbonImmutable::parse('2026-10-01'), [new InventoryMovementData($i->id, $q, $cost)], InventorySourceType::Adjustment, (string) Str::uuid()), $a->id);
    }

    private function stock(InventoryItem $i): string
    {
        return DecimalMath::normalize((string) $i->stockMovements()->sum('quantity'), 6);
    }
}
