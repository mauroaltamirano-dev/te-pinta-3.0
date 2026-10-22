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
            if (Recipe::query()->where('is_active', true)->whereRaw('lower(btrim(name)) = lower(btrim(?))', [$name])->exists()) {
                throw ValidationException::withMessages(['name' => 'Ya existe una receta activa con ese nombre. Elegí otro nombre o retirá la otra receta.']);
            }

            return Recipe::query()->create(['name' => $name, 'created_by' => $adminId]);
        });
    }
}
