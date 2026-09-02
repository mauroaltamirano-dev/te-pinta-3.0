<?php

namespace App\Modules\Marketing\Queries;

use App\Modules\Marketing\Models\FeaturedProduct;
use App\Modules\Marketing\Support\MarketingClock;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final readonly class FeaturedProductResolver
{
    public function __construct(private MarketingClock $clock) {}

    public function temporallyActive(?CarbonImmutable $now = null): ?FeaturedProduct
    {
        $instant = $now ?? $this->clock->now();

        return $this->withAdministrativeProduct()
            ->where('is_enabled', true)
            ->where('starts_at', '<=', $instant)
            ->where(fn (Builder $query) => $query
                ->whereNull('ends_at')
                ->orWhere('ends_at', '>', $instant))
            ->first();
    }

    public function publiclyVisible(?CarbonImmutable $now = null): ?FeaturedProduct
    {
        $instant = $now ?? $this->clock->now();

        return $this->withAdministrativeProduct()
            ->where('is_enabled', true)
            ->where('starts_at', '<=', $instant)
            ->where(fn (Builder $query) => $query
                ->whereNull('ends_at')
                ->orWhere('ends_at', '>', $instant))
            ->whereHas('product', fn (Builder $product) => $product
                ->whereNull('products.deleted_at')
                ->where('products.is_available', true)
                ->whereHas('category', fn (Builder $category) => $category
                    ->where('categories.is_active', true)
                    ->whereNull('categories.deleted_at')))
            ->first();
    }

    public function next(?CarbonImmutable $now = null): ?FeaturedProduct
    {
        $instant = $now ?? $this->clock->now();

        return $this->withAdministrativeProduct()
            ->where('is_enabled', true)
            ->where('starts_at', '>', $instant)
            ->orderBy('starts_at')
            ->orderBy('id')
            ->first();
    }

    public function visibilityReason(FeaturedProduct $featuredProduct): ?string
    {
        $featuredProduct->loadMissing([
            'product' => fn ($product) => $product->withTrashed()->with([
                'category' => fn ($category) => $category->withTrashed(),
            ]),
        ]);
        $product = $featuredProduct->product;

        if ($product->trashed()) {
            return 'product_deleted';
        }

        if (! $product->is_available) {
            return 'product_unavailable';
        }

        if ($product->category === null || $product->category->trashed() || ! $product->category->is_active) {
            return 'category_unavailable';
        }

        return null;
    }

    /** @return Builder<FeaturedProduct> */
    public function withAdministrativeProduct(): Builder
    {
        return FeaturedProduct::query()->with([
            'product' => fn ($product) => $product->withTrashed()->with([
                'category' => fn ($category) => $category->withTrashed(),
            ]),
        ]);
    }
}
