<?php

namespace Tests\Feature\Inventory;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Actions\ConfigureCostingPolicy;
use App\Modules\Inventory\Models\CostingPolicy;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CostingFixtures;
use Tests\TestCase;

final class CostingPolicyTest extends TestCase
{
    use CostingFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->costingFixture();
    }

    #[DataProvider('validOverheads')]
    public function test_admin_creates_policy_with_exact_packaging_and_valid_overhead(int $bp): void
    {
        $this->actingAs($this->admin)->post('/admin/inventory/costing-policies', $this->policyPayload($bp))->assertRedirect('/admin/inventory/costing-policies');
        $p = CostingPolicy::query()->sole();
        $this->assertSame($bp, $p->overhead_basis_points);
        $this->assertDatabaseHas('inventory_costing_policy_packaging_components', ['inventory_item_id' => $this->box->id, 'quantity' => '2.000001', 'base_quantity' => '2.000001']);
        $this->assertDatabaseCount('inventory_stock_movements', 0);
        $this->actingAs($this->admin)->get('/admin/inventory/costing-policies')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('policies.0.presentations.0.packaging_mode', 'none')->where('policies.0.presentations.2.components.0.quantity', '2.000001'));
    }

    public static function validOverheads(): array
    {
        return [[0], [1000], [2500], [10000]];
    }

    #[DataProvider('invalidPolicies')]
    public function test_invalid_policy_is_rejected_atomically(string $case): void
    {
        $v = $this->policyPayload();
        switch ($case) {
            case 'negative': $v['overhead_basis_points'] = -1;
                break;
            case 'excessive': $v['overhead_basis_points'] = 10001;
                break;
            case 'fractional_bp': $v['overhead_basis_points'] = '1.5';
                break;
            case 'empty_name': $v['name'] = ' ';
                break;
            case 'unknown_item': $v['presentations'][1]['components'][0]['inventory_item_id'] = 999999;
                break;
            case 'duplicate_item': $v['presentations'][1]['components'][] = $v['presentations'][1]['components'][0];
                break;
            case 'zero': $v['presentations'][1]['components'][0]['quantity'] = '0';
                break;
            case 'overprecision': $v['presentations'][1]['components'][0]['quantity'] = '0.0000001';
                break;
            case 'wrong_unit': $v['presentations'][1]['components'][0]['unit_id'] = $this->gram->id;
                break;
            case 'wrong_kind': $v['presentations'][1]['components'][0]['inventory_item_id'] = $this->filling->id;
                break;
            case 'duplicate_presentation': $v['presentations'][] = $v['presentations'][0];
                break;
            case 'none_with_components': $v['presentations'][1]['packaging_mode'] = 'none';
                break;
            case 'components_without_items': $v['presentations'][1]['components'] = [];
                break;
        }
        $this->actingAs($this->admin)->post('/admin/inventory/costing-policies', $v)->assertSessionHasErrors();
        $this->assertDatabaseCount('inventory_costing_policies', 0);
        $this->assertDatabaseCount('inventory_costing_policy_packaging_components', 0);
    }

    public static function invalidPolicies(): array
    {
        return array_map(fn ($case) => [$case], ['negative', 'excessive', 'fractional_bp', 'empty_name', 'unknown_item', 'duplicate_item', 'zero', 'overprecision', 'wrong_unit', 'wrong_kind', 'duplicate_presentation', 'none_with_components', 'components_without_items']);
    }

    public function test_case_insensitive_policy_identity_and_edit_do_not_create_duplicates(): void
    {
        $p = app(ConfigureCostingPolicy::class)->execute(null, $this->policyPayload());
        $this->actingAs($this->admin)->post('/admin/inventory/costing-policies', array_replace($this->policyPayload(), ['name' => ' CURRENT ESTIMATE ']))->assertSessionHasErrors('name');
        $v = $this->policyPayload(2500);
        $v['presentations'][2]['components'][0]['quantity'] = '1';
        $this->patch('/admin/inventory/costing-policies/'.$p->id, $v)->assertRedirect();
        $this->assertSame(2500, $p->fresh()->overhead_basis_points);
        $this->assertDatabaseCount('inventory_costing_policies', 1);
        $this->assertDatabaseCount('inventory_costing_policy_packaging_components', 2);
    }

    public function test_product_assignment_can_be_replaced_and_explicitly_removed(): void
    {
        $a = app(ConfigureCostingPolicy::class)->execute(null, $this->policyPayload());
        $b = app(ConfigureCostingPolicy::class)->execute(null, array_replace($this->policyPayload(), ['name' => 'Second']));
        $url = '/admin/inventory/costing-policies/assignments/'.$this->product->id;
        foreach ([$a->id, $b->id, null] as $id) {
            $this->actingAs($this->admin)->post($url, ['costing_policy_id' => $id])->assertRedirect();
            $this->assertSame($id, DB::table('inventory_product_costing_policies')->where('product_id', $this->product->id)->value('costing_policy_id'));
        }
        $this->assertDatabaseCount('inventory_costing_policies', 2);
        $this->assertDatabaseCount('inventory_product_costing_policies', 0);
        $this->post($url, ['costing_policy_id' => 999999])->assertSessionHasErrors();
        $b->update(['is_active' => false]);
        $this->post($url, ['costing_policy_id' => $b->id])->assertSessionHasErrors();
        $this->assertDatabaseCount('inventory_product_costing_policies', 0);
    }

    #[DataProvider('nonAdmins')]
    public function test_waiter_cashier_and_guest_cannot_read_or_mutate_costing(?string $profile): void
    {
        if ($profile !== null) {
            $this->actingAs(User::factory()->create(['staff_profile' => $profile]));
        }
        foreach (['/admin/inventory/costing', '/admin/inventory/costing-policies'] as $url) {
            $r = $this->getJson($url);
            $profile === null ? $r->assertUnauthorized() : $r->assertForbidden();
        }
        foreach (['/admin/inventory/costing-policies', '/admin/inventory/costing-policies/assignments/'.$this->product->id] as $url) {
            $r = $this->postJson($url, $this->policyPayload());
            $profile === null ? $r->assertUnauthorized() : $r->assertForbidden();
        }
        $r = $this->patchJson('/admin/inventory/costing-policies/1', $this->policyPayload());
        $profile === null ? $r->assertUnauthorized() : $r->assertForbidden();
        $this->assertDatabaseCount('inventory_costing_policies', 0);
        $this->assertDatabaseCount('inventory_product_costing_policies', 0);
    }

    public static function nonAdmins(): array
    {
        return [['waiter'], ['cashier'], [null]];
    }

    public function test_postgresql_rejects_invalid_overhead_name_reference_quantity_conversion_and_duplicate(): void
    {
        $p = app(ConfigureCostingPolicy::class)->execute(null, $this->policyPayload());
        $row = DB::table('inventory_costing_policy_packaging_components')->first();
        foreach ([['inventory_costing_policies', $p->id, ['overhead_basis_points' => -1]], ['inventory_costing_policies', $p->id, ['overhead_basis_points' => 10001]], ['inventory_costing_policies', $p->id, ['name' => ' ']], ['inventory_costing_policy_packaging_components', $row->id, ['quantity' => 0]], ['inventory_costing_policy_packaging_components', $row->id, ['base_quantity' => 123]], ['inventory_costing_policy_packaging_components', $row->id, ['inventory_item_id' => 999999]]] as [$table,$id,$values]) {
            $before = DB::table($table)->where('id', $id)->first();
            try {
                DB::transaction(fn () => DB::table($table)->where('id', $id)->update($values));
                $this->fail('Invalid SQL accepted');
            } catch (QueryException) {
                $this->assertEquals($before, DB::table($table)->where('id', $id)->first());
            }
        }
        try {
            DB::transaction(fn () => DB::table('inventory_costing_policy_packaging_components')->insert(array_diff_key((array) $row, ['id' => true])));
            $this->fail('Duplicate SQL accepted');
        } catch (QueryException $e) {
            $this->assertSame('23505', $e->errorInfo[0]);
        }
        try {
            DB::transaction(fn () => DB::table('inventory_product_costing_policies')->insert(['product_id' => $this->product->id, 'costing_policy_id' => 999999]));
            $this->fail('Invalid assignment accepted');
        } catch (QueryException $e) {
            $this->assertSame('23503', $e->errorInfo[0]);
        }
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }
}
