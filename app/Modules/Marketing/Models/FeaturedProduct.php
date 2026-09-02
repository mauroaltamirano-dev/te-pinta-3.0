<?php

namespace App\Modules\Marketing\Models;

use App\Modules\Marketing\Enums\FeaturedProductStatus;
use App\Modules\Products\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $product_id
 * @property string|null $headline
 * @property string|null $short_copy
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable|null $ends_at
 * @property bool $is_enabled
 * @property-read Product $product
 */
final class FeaturedProduct extends Model
{
    protected $table = 'marketing_featured_products';

    protected $fillable = [
        'product_id',
        'headline',
        'short_copy',
        'starts_at',
        'ends_at',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'is_enabled' => 'boolean',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function statusAt(CarbonImmutable $now): FeaturedProductStatus
    {
        if (! $this->is_enabled) {
            return FeaturedProductStatus::Inactive;
        }

        if ($this->ends_at !== null && $this->ends_at->lessThanOrEqualTo($now)) {
            return FeaturedProductStatus::Finished;
        }

        if ($this->starts_at->isAfter($now)) {
            return FeaturedProductStatus::Scheduled;
        }

        return FeaturedProductStatus::Active;
    }
}
