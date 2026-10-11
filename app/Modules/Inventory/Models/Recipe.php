<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Recipe extends Model
{
    protected $table = 'inventory_recipes';

    protected $fillable = ['name', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return HasMany<RecipeVersion,$this> */
    public function versions(): HasMany
    {
        return $this->hasMany(RecipeVersion::class);
    }
}
