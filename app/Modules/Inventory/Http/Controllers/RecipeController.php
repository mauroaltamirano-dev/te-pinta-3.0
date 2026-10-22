<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Modules\Inventory\Actions\CorrectRecipe;
use App\Modules\Inventory\Actions\CreateRecipe;
use App\Modules\Inventory\Actions\CreateRecipeVersion;
use App\Modules\Inventory\Actions\RetireRecipe;
use App\Modules\Inventory\Data\RecipeIngredientData;
use App\Modules\Inventory\Data\RecipeVersionData;
use App\Modules\Inventory\Http\Requests\CorrectRecipeRequest;
use App\Modules\Inventory\Http\Requests\StoreRecipeRequest;
use App\Modules\Inventory\Http\Requests\StoreRecipeVersionRequest;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryUnit;
use App\Modules\Inventory\Models\ProductionBatch;
use App\Modules\Inventory\Models\Recipe;
use App\Modules\Inventory\Models\RecipeVersion;
use App\Modules\Inventory\Queries\BuildRecipeCurrentCost;
use App\Modules\Inventory\Support\DecimalMath;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class RecipeController
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', InventoryItem::class);
        $view = $request->query('view') === 'retired' ? 'retired' : 'active';
        $recipes = Recipe::query()->where('is_active', $view === 'active')->withCount('versions')->latest()->paginate(20)->appends(['view' => $view])
            ->through(fn (Recipe $r) => ['id' => $r->id, 'name' => $r->name, 'is_active' => $r->is_active, 'versions_count' => $r->versions_count]);

        return Inertia::render('admin/inventory/recipes/index', ['recipes' => $recipes, 'view' => $view]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', InventoryItem::class);

        return Inertia::render('admin/inventory/recipes/create', ['items' => $this->items('recipe_input_enabled'), 'outputItems' => $this->items('production_output_enabled'), 'units' => $this->units()]);
    }

    public function store(StoreRecipeRequest $request, CreateRecipe $action): RedirectResponse
    {
        $values = $request->validated();
        $recipe = DB::transaction(function () use ($request, $action, $values): Recipe {
            $recipe = $action->execute($values['name'], $request->user()->id);
            app(CreateRecipeVersion::class)->execute($recipe->id, $this->versionData($values), $request->user()->id);

            return $recipe;
        });

        return to_route('admin.inventory.recipes.show', $recipe);
    }

    public function show(Request $request, Recipe $recipe, BuildRecipeCurrentCost $currentCost): Response
    {
        Gate::authorize('viewAny', InventoryItem::class);
        $view = $request->query('view') === 'retired' ? 'retired' : 'current';
        $versions = $recipe->versions()
            ->where('is_active', $view === 'current')
            ->when($view === 'retired', fn ($query) => $query->where('is_sealed', true))
            ->with('outputItem.baseUnit', 'outputUnit', 'ingredients.item.baseUnit', 'ingredients.item.category', 'ingredients.unit')
            ->latest('version')
            ->paginate(10)
            ->appends(['view' => $view]);

        $costs = $currentCost->buildMany($versions->getCollection());
        $versions->getCollection()->transform(function (RecipeVersion $version) use ($costs): RecipeVersion {
            $version->setAttribute('current_cost', $costs[$version->id]);
            $version->setAttribute('theoretical_mass', $this->theoreticalMass($version));

            return $version;
        });

        $canCorrect = $recipe->is_active && $recipe->versions()->where('is_active', true)
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))->from((new ProductionBatch)->getTable())
                ->whereColumn('recipe_version_id', 'inventory_recipe_versions.id'))->exists();
        $needsFirstVersion = $recipe->is_active && ! $recipe->versions()->where('is_active', true)->exists();

        return Inertia::render('admin/inventory/recipes/show', ['recipe' => $recipe, 'versions' => $versions, 'version_view' => $view, 'can_correct' => $canCorrect, 'needs_first_version' => $needsFirstVersion, 'items' => $this->items('recipe_input_enabled'), 'outputItems' => $this->items('production_output_enabled'), 'units' => $this->units()]);
    }

    public function storeVersion(StoreRecipeVersionRequest $request, Recipe $recipe, CreateRecipeVersion $action): RedirectResponse
    {
        $v = $request->validated();
        $action->execute($recipe->id, $this->versionData($v), $request->user()->id);

        return to_route('admin.inventory.recipes.show', $recipe);
    }

    public function correct(CorrectRecipeRequest $request, Recipe $recipe, CorrectRecipe $action): RedirectResponse
    {
        $v = $request->validated();
        $action->execute($recipe->id, (string) $v['name'], $this->versionData($v), $request->user()->id);

        return to_route('admin.inventory.recipes.show', $recipe);
    }

    public function retire(Request $request, Recipe $recipe, RetireRecipe $action): RedirectResponse
    {
        Gate::authorize('create', InventoryItem::class);
        $action->execute($recipe->id, $request->user()->id);

        return to_route('admin.inventory.recipes.show', $recipe);
    }

    /** @param array<string,mixed> $v */
    private function versionData(array $v): RecipeVersionData
    {
        return new RecipeVersionData((int) $v['output_inventory_item_id'], (int) $v['output_unit_id'], (string) $v['expected_output_quantity'],
            array_values(array_map(static fn (array $i): RecipeIngredientData => new RecipeIngredientData((int) $i['inventory_item_id'], (int) $i['unit_id'], (string) $i['quantity']), $v['ingredients'])));
    }

    /** @return array<int, InventoryItem> */
    private function items(string $capability): array
    {
        return InventoryItem::query()->where('is_active', true)->where('tracks_stock', true)->where($capability, true)->with('baseUnit', 'category')->orderBy('name')->get()->all();
    }

    /** @return array<int, InventoryUnit> */
    private function units(): array
    {
        return InventoryUnit::query()->orderBy('family')->orderBy('code')->get()->all();
    }

    /** @return array{grams: string, comparable_count: int, total_count: int, missing_item_ids: list<int>} */
    private function theoreticalMass(RecipeVersion $version): array
    {
        $grams = '0.000000000000';
        $comparable = 0;
        $missing = [];

        foreach ($version->ingredients as $ingredient) {
            $item = $ingredient->item;
            if ($item->unit_family->value === 'weight') {
                $lineMass = DecimalMath::normalize((string) $ingredient->base_quantity, 12);
            } elseif ($item->unit_family->value === 'count' && $item->reference_mass_grams_per_unit !== null) {
                $lineMass = DecimalMath::multiply((string) $ingredient->base_quantity, (string) $item->reference_mass_grams_per_unit, 12);
                $ingredient->setAttribute('reference_mass_grams', $lineMass);
            } else {
                $missing[] = $item->id;

                continue;
            }
            $grams = DecimalMath::subtract($grams, '-'.$lineMass);
            $comparable++;
        }

        return ['grams' => $grams, 'comparable_count' => $comparable, 'total_count' => $version->ingredients->count(), 'missing_item_ids' => $missing];
    }
}
