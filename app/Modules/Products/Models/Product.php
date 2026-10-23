<?php

namespace App\Modules\Products\Models;

use App\Modules\Products\Enums\ProductPricingFamily;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $category_id
 * @property int|null $subcategory_id
 * @property ProductPricingFamily $pricing_family
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $long_description
 * @property string|null $public_ingredients
 * @property string|null $image_path
 * @property int $sort_order
 * @property int $unit_price
 * @property int $half_dozen_price
 * @property int $dozen_price
 * @property bool $is_available
 */
final class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id', 'subcategory_id', 'pricing_family', 'name', 'slug', 'description', 'long_description', 'public_ingredients', 'image_path', 'sort_order',
        'unit_price', 'half_dozen_price', 'dozen_price', 'is_available',
    ];

    protected function casts(): array
    {
        return [
            'pricing_family' => ProductPricingFamily::class,
            'sort_order' => 'integer',
            'unit_price' => 'integer',
            'half_dozen_price' => 'integer',
            'dozen_price' => 'integer',
            'is_available' => 'boolean',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<ProductSubcategory, $this> */
    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(ProductSubcategory::class, 'subcategory_id');
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }
}
