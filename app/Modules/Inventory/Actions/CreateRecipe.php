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
            throw ValidationException::withMessages(['name' => 'Recipe name is required.']);
        }

        return DB::transaction(function () use ($name, $adminId): Recipe {
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['inventory:recipe-name']);
            if (Recipe::query()->whereRaw('lower(btrim(name)) = lower(btrim(?))', [$name])->exists()) {
                throw ValidationException::withMessages(['name' => 'A recipe with this name already exists.']);
            }

            return Recipe::query()->create(['name' => $name, 'created_by' => $adminId]);
        });
    }
}
