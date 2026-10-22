<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Data\RecipeVersionData;
use App\Modules\Inventory\Models\ProductionBatch;
use App\Modules\Inventory\Models\Recipe;
use App\Modules\Inventory\Models\RecipeIngredient;
use App\Modules\Inventory\Models\RecipeVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Replaces a current formula that production never used, so an accidental save leaves
 * no history. A used formula is immutable and changes only through a new version.
 */
final class CorrectRecipe
{
    public function __construct(private readonly CreateRecipeVersion $createVersion) {}

    public function execute(int $recipeId, string $name, RecipeVersionData $data, int $adminId): RecipeVersion
    {
        $name = trim($name);
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Ingresá el nombre de la receta.']);
        }

        return DB::transaction(function () use ($recipeId, $name, $data, $adminId): RecipeVersion {
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['inventory:recipe-name']);
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['inventory:recipe-graph']);
            $recipe = Recipe::query()->lockForUpdate()->findOrFail($recipeId);
            if (! $recipe->is_active) {
                throw ValidationException::withMessages(['recipe' => 'Esta receta está retirada y no se puede corregir.']);
            }
            $current = RecipeVersion::query()->where('recipe_id', $recipe->id)->where('is_active', true)->lockForUpdate()->first();
            if ($current === null || ProductionBatch::query()->where('recipe_version_id', $current->id)->exists()) {
                throw ValidationException::withMessages(['recipe' => 'Esta versión ya se usó en producción y no se puede corregir. Usá «Crear nueva versión» para cambiar la fórmula.']);
            }
            if (Recipe::query()->whereKeyNot($recipe->id)->where('is_active', true)->whereRaw('lower(btrim(name)) = lower(btrim(?))', [$name])->exists()) {
                throw ValidationException::withMessages(['name' => 'Ya existe una receta activa con ese nombre. Elegí otro nombre o retirá la otra receta.']);
            }

            RecipeIngredient::query()->where('recipe_version_id', $current->id)->delete();
            $current->delete();
            $recipe->update(['name' => $name]);
            $version = $this->createVersion->execute($recipe->id, $data, $adminId);
            logger()->info('[Inventory] recipe.corrected', ['recipe_id' => $recipe->id, 'replaced_version_id' => $current->id, 'recipe_version_id' => $version->id, 'admin_id' => $adminId]);

            return $version;
        });
    }
}
