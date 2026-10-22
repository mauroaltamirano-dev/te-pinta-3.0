<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\Recipe;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateRecipe
{
    public function execute(string $name, int $adminId): Recipe
    {
        $name = trim($name);
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Ingresá el nombre de la receta.']);
        }

        return DB::transaction(function () use ($name, $adminId): Recipe {
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['inventory:recipe-name']);
            if (Recipe::query()->whereRaw('lower(btrim(name)) = lower(btrim(?))', [$name])->exists()) {
                throw ValidationException::withMessages(['name' => 'Ya existe una receta con ese nombre (puede estar retirada). Elegí otro nombre.']);
            }

            return Recipe::query()->create(['name' => $name, 'created_by' => $adminId]);
        });
    }
}
