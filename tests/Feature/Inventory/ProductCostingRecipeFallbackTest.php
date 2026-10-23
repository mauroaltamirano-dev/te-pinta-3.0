<?php

namespace Tests\Feature\Inventory;

use App\Modules\Inventory\Actions\AssignProductCostingPolicy;
use App\Modules\Inventory\Actions\ConfigureCostingPolicy;
use App\Modules\Inventory\Actions\CreateRecipe;
use App\Modules\Inventory\Actions\CreateRecipeVersion;
use App\Modules\Inventory\Actions\RetireRecipe;
use App\Modules\Inventory\Data\RecipeIngredientData;
use App\Modules\Inventory\Data\RecipeVersionData;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Queries\BuildProductUnitEconomics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CostingFixtures;
use Tests\TestCase;

final class ProductCostingRecipeFallbackTest extends TestCase
{
    use CostingFixtures, RefreshDatabase;

    private InventoryItem $onion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->costingFixture();
        $this->filling->update(['production_output_enabled' => true]);
        $this->onion = $this->filling->replicate();
        $this->onion->fill(['name' => 'Onion', 'kind' => 'raw_material', 'recipe_input_enabled' => true, 'production_output_enabled' => false, 'order_consumption_enabled' => false])->save();
        $this->consumption();
        $policy = app(ConfigureCostingPolicy::class)->execute(null, $this->policyPayload());
        app(AssignProductCostingPolicy::class)->execute($this->product->id, $policy->id);
    }

    /** 1000 g of onion yield 500 g of filling. */
    private function recipe(): int
    {
        $recipe = app(CreateRecipe::class)->execute('Filling formula', $this->admin->id);
        app(CreateRecipeVersion::class)->execute($recipe->id, new RecipeVersionData($this->filling->id, $this->gram->id, '500', [new RecipeIngredientData($this->onion->id, $this->gram->id, '1000')]), $this->admin->id);

        return $recipe->id;
    }

    /** @return array<string, mixed> */
    private function estimate(): array
    {
        return app(BuildProductUnitEconomics::class)->build($this->product);
    }

    /** @return list<string> */
    private function steps(array $economics): array
    {
        return array_column($economics['next_steps'], 'text');
    }

    public function test_semi_finished_without_production_uses_its_active_recipe_estimate(): void
    {
        $this->receive($this->onion, '1000', 2150);
        $this->recipe();
        $tables = ['inventory_stock_movements', 'inventory_cost_layers', 'inventory_transactions', 'financial_transactions', 'ledger_entries'];
        $snapshot = fn () => array_map(fn ($table) => DB::table($table)->orderBy('id')->get()->toJson(), $tables);
        $state = $snapshot();

        $r = $this->estimate();
        [$unit, , $dozen] = $r['presentations'];

        // 2150 pesos for 500 g of filling; 55 g per empanada = 236.5, rounded once.
        $this->assertSame('complete', $unit['status']);
        $this->assertSame(237, $unit['direct_cost']);
        $this->assertSame('recipe', $unit['direct_components'][0]['cost_source']);
        $this->assertSame(237, $unit['direct_components'][0]['estimated_cost']);
        $this->assertTrue($unit['estimated_by_recipe']);
        $this->assertSame(24, $unit['overhead_estimate']);
        $this->assertSame(261, $unit['estimated_total_cost']);
        $this->assertSame($unit['selling_price'] - 261, $unit['estimated_margin']);
        $this->assertSame(2838, $dozen['direct_cost']);
        $this->assertNull($dozen['estimated_total_cost'], 'The never bought box still blocks the dozen.');
        $this->assertSame(['Finished filling'], $r['recipe_estimated_items']);
        $this->assertSame(['Registrá una compra de Cardboard box.'], $this->steps($r));
        $this->assertSame('/admin/management/purchases/create', $r['next_steps'][0]['href']);
        $this->assertSame($state, $snapshot(), 'The estimate writes nothing.');

        $this->actingAs($this->admin)->get('/admin/inventory/costing?product_id='.$this->product->id)->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('economics.presentations.0.direct_cost', 237)->where('economics.recipe_estimated_items.0', 'Finished filling'));
    }

    public function test_recipe_fallback_works_inside_a_read_only_transaction(): void
    {
        $this->receive($this->onion, '1000', 2150);
        $this->recipe();
        DB::beginTransaction();
        DB::statement('SET LOCAL transaction_read_only = on');
        try {
            $this->assertSame(237, $this->estimate()['presentations'][0]['direct_cost']);
        } finally {
            DB::rollBack();
        }
    }

    /** @return array<string, array{0: list<array{0: string, 1: ?int}>}> */
    public static function unreliableFillingStock(): array
    {
        return [
            'unknown cost (manual adjustment without total)' => [[['1000', null]]],
            'partial cost' => [[['100', 500], ['100', null]]],
            'negative stock' => [[['-100', null]]],
        ];
    }

    /** @param list<array{0: string, 1: ?int}> $receipts */
    #[DataProvider('unreliableFillingStock')]
    public function test_semi_finished_stock_without_reliable_cost_uses_the_recipe(array $receipts): void
    {
        $this->receive($this->onion, '1000', 2150);
        foreach ($receipts as [$quantity, $cost]) {
            $this->receive($this->filling, $quantity, $cost);
        }
        $this->recipe();

        $unit = $this->estimate()['presentations'][0];
        $this->assertSame('complete', $unit['status']);
        $this->assertSame(237, $unit['direct_cost']);
        $this->assertSame('recipe', $unit['direct_components'][0]['cost_source']);
    }

    public function test_reliable_semi_finished_stock_is_preferred_over_the_recipe(): void
    {
        $this->receive($this->onion, '1000', 2150);
        $this->receive($this->filling, '10000', 100000);
        $this->recipe();

        $unit = $this->estimate()['presentations'][0];
        $this->assertSame(550, $unit['direct_cost']);
        $this->assertSame('stock', $unit['direct_components'][0]['cost_source']);
        $this->assertFalse($unit['estimated_by_recipe']);
    }

    public function test_incomplete_recipe_keeps_the_cost_unknown_and_asks_for_the_missing_ingredient(): void
    {
        $this->recipe();

        $r = $this->estimate();
        $unit = $r['presentations'][0];
        $this->assertSame('incomplete', $unit['status']);
        $this->assertNull($unit['direct_cost']);
        $this->assertNull($unit['direct_components'][0]['estimated_cost']);
        $this->assertNull($unit['direct_components'][0]['cost_source']);
        $this->assertNull($unit['estimated_total_cost']);
        $this->assertSame([], $r['recipe_estimated_items']);
        $this->assertContains('Registrá una compra de Onion.', $this->steps($r));
    }

    public function test_semi_finished_without_an_active_recipe_asks_for_one(): void
    {
        $this->receive($this->onion, '1000', 2150);
        app(RetireRecipe::class)->execute($this->recipe(), $this->admin->id);

        $r = $this->estimate();
        $this->assertNull($r['presentations'][0]['direct_cost']);
        $this->assertContains('Creá la receta de Finished filling.', $this->steps($r));
    }

    public function test_purchased_item_has_no_recipe_fallback(): void
    {
        $this->receive($this->onion, '1000', 2150);
        $this->recipe();
        $this->filling->update(['kind' => 'raw_material']);

        $r = $this->estimate();
        $unit = $r['presentations'][0];
        $this->assertNull($unit['direct_cost']);
        $this->assertNull($unit['direct_components'][0]['cost_source']);
        $this->assertSame('incomplete', $unit['status']);
        $this->assertContains('Registrá una compra de Finished filling.', $this->steps($r));
    }
}
