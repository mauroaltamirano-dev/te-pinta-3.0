<?php

namespace App\Modules\Marketing\Queries;

use App\Modules\Marketing\Models\FeaturedProduct;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Support\PublicProductImageUrl;

final class BuildFeaturedProductPublicView
{
    /** @return array{headline: ?string, short_copy: ?string, product: array{slug: string, name: string, description: ?string, image_url: ?string, prices: array{unit: int, half_dozen: int, dozen: int}}} */
    public function execute(FeaturedProduct $featuredProduct): array
    {
        $featuredProduct->loadMissing('product');
        $product = $featuredProduct->product;

        return [
            'headline' => $featuredProduct->headline,
            'short_copy' => $featuredProduct->short_copy,
            'product' => $this->product($product),
        ];
    }

    /** @return array{slug: string, name: string, description: ?string, image_url: ?string, prices: array{unit: int, half_dozen: int, dozen: int}} */
    public function product(Product $product): array
    {
        return [
            'slug' => $product->slug,
            'name' => $product->name,
            'description' => $product->description,
            'image_url' => PublicProductImageUrl::fromPath($product->image_path),
            'prices' => [
                'unit' => (int) $product->unit_price,
                'half_dozen' => (int) $product->half_dozen_price,
                'dozen' => (int) $product->dozen_price,
            ],
        ];
    }
}
