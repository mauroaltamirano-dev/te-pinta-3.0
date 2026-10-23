<?php

namespace App\Modules\Products\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A filter inside one category (for example Empanadas > Clásicas). It never affects prices or sellability.
 *
 * @property int $id
 * @property int $category_id
 * @property string $name
 * @property string $slug
 * @property int $sort_order
 * @property bool $is_active
 */
final class ProductSubcategory extends Model
{
    protected $fillable = ['category_id', 'name', 'slug', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'category_id' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'subcategory_id');
    }
}
