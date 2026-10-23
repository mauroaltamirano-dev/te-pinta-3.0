import { execFileSync } from 'node:child_process';

/**
 * Seeds an empanada whose filling has a recipe but no production, and whose
 * dozen box was never bought. Names carry `suffix` so each project gets its own rows.
 */
export function seedRecipeBackedEmpanada(suffix: string): {
    product: string;
    filling: string;
    box: string;
    policy: string;
} {
    const names = {
        product: `Empanada costeo ${suffix}`,
        filling: `Relleno Jamón y Queso ${suffix}`,
        box: `Caja Cartón 1 doc ${suffix}`,
        policy: `Empanadas ${suffix}`,
    };
    const code = String.raw`
        $admin = App\Modules\Identity\Models\User::where('email', 'playwright-admin@example.test')->firstOrFail()->id;
        $gram = App\Modules\Inventory\Models\InventoryUnit::where('code', 'g')->sole();
        $unit = App\Modules\Inventory\Models\InventoryUnit::where('code', 'unit')->sole();
        $category = App\Modules\Inventory\Models\InventoryCategory::query()->create(['name' => 'Costeo ${suffix}']);
        $item = fn (string $name, string $kind, bool $weight, array $flags) => App\Modules\Inventory\Models\InventoryItem::query()->create(array_merge([
            'inventory_category_id' => $category->id, 'name' => $name, 'kind' => $kind, 'tracks_stock' => true,
            'unit_family' => $weight ? 'weight' : 'count', 'base_unit_id' => $weight ? $gram->id : $unit->id,
            'default_input_unit_id' => $weight ? $gram->id : $unit->id, 'quantity_precision' => 3, 'is_active' => true,
        ], $flags));
        $ham = $item('Jamón ${suffix}', 'raw_material', true, ['recipe_input_enabled' => true]);
        $cheese = $item('Queso ${suffix}', 'raw_material', true, ['recipe_input_enabled' => true]);
        $wrapper = $item('Tapa ${suffix}', 'raw_material', false, ['order_consumption_enabled' => true]);
        $filling = $item('${names.filling}', 'semi_finished', true, ['production_output_enabled' => true, 'order_consumption_enabled' => true]);
        $box = $item('${names.box}', 'packaging', false, []);
        foreach ([[$ham, '1000', 9000], [$cheese, '1000', 8000], [$wrapper, '120', 18000]] as [$stock, $quantity, $cost]) {
            app(App\Modules\Inventory\Actions\RecordInventoryTransaction::class)->execute(new App\Modules\Inventory\Data\InventoryTransactionData(
                App\Modules\Inventory\Enums\InventoryTransactionType::Adjustment, Carbon\CarbonImmutable::parse('2026-10-20'),
                [new App\Modules\Inventory\Data\InventoryMovementData($stock->id, $quantity, $cost)],
                App\Modules\Inventory\Enums\InventorySourceType::Adjustment, (string) Illuminate\Support\Str::uuid()), $admin);
        }
        $recipe = app(App\Modules\Inventory\Actions\CreateRecipe::class)->execute('Receta ${names.filling}', $admin);
        app(App\Modules\Inventory\Actions\CreateRecipeVersion::class)->execute($recipe->id, new App\Modules\Inventory\Data\RecipeVersionData($filling->id, $gram->id, '1000', [
            new App\Modules\Inventory\Data\RecipeIngredientData($ham->id, $gram->id, '500'),
            new App\Modules\Inventory\Data\RecipeIngredientData($cheese->id, $gram->id, '500'),
        ]), $admin);
        $product = App\Modules\Products\Models\Product::query()->create([
            'category_id' => App\Modules\Products\Models\Category::where('slug', 'clasicas')->firstOrFail()->id,
            'name' => '${names.product}', 'slug' => (string) Illuminate\Support\Str::uuid(), 'pricing_family' => 'empanada',
            'unit_price' => 1700, 'half_dozen_price' => 9500, 'dozen_price' => 18000, 'is_available' => true,
        ]);
        app(App\Modules\Inventory\Actions\ConfigureProductInventoryConsumption::class)->execute($product->id, 'consume', [
            ['inventory_item_id' => $filling->id, 'quantity' => '40', 'unit_id' => $gram->id],
            ['inventory_item_id' => $wrapper->id, 'quantity' => '1', 'unit_id' => $unit->id],
        ], $admin);
        $policy = app(App\Modules\Inventory\Actions\ConfigureCostingPolicy::class)->execute(null, [
            'name' => '${names.policy}', 'overhead_basis_points' => 1000, 'is_active' => true, 'presentations' => [
                ['commercial_quantity' => 1, 'packaging_mode' => 'none', 'components' => []],
                ['commercial_quantity' => 6, 'packaging_mode' => 'none', 'components' => []],
                ['commercial_quantity' => 12, 'packaging_mode' => 'components', 'components' => [['inventory_item_id' => $box->id, 'quantity' => '1', 'unit_id' => $unit->id]]],
            ],
        ]);
        app(App\Modules\Inventory\Actions\AssignProductCostingPolicy::class)->execute($product->id, $policy->id);
    `;
    execFileSync('php', ['artisan', 'tinker', '--execute', code], {
        env: {
            ...process.env,
            APP_ENV: 'testing',
            DB_DATABASE:
                process.env.PLAYWRIGHT_DB_DATABASE ?? 'testing_playwright',
            DB_URL: '',
        },
        stdio: 'pipe',
    });

    return names;
}
