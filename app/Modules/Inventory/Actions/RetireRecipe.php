<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\Recipe;
use App\Modules\Inventory\Models\RecipeVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Deactivates a recipe and its current version; every version stays readable as history. */
final class RetireRecipe
{
    public function execute(int $recipeId, int $adminId): Recipe
    {
        return DB::transaction(function () use ($recipeId, $adminId): Recipe {
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['inventory:recipe-graph']);
            $recipe = Recipe::query()->lockForUpdate()->findOrFail($recipeId);
            if (! $recipe->is_active) {
                throw ValidationException::withMessages(['recipe' => 'Esta receta ya está retirada.']);
            }
            RecipeVersion::query()->where('recipe_id', $recipe->id)->where('is_active', true)->update(['is_active' => false]);
            $recipe->update(['is_active' => false]);
            logger()->info('[Inventory] recipe.retired', ['recipe_id' => $recipe->id, 'admin_id' => $adminId]);

            return $recipe;
        });
    }
}
