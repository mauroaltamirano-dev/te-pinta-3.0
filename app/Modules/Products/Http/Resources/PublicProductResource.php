<?php

namespace App\Modules\Products\Http\Resources;

use App\Modules\Products\Enums\ProductPricingFamily;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Support\PublicProductImageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
final class PublicProductResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $pricingFamily = $this->pricing_family ?? ProductPricingFamily::Empanada;

        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'pricing_family' => $pricingFamily->value,
            'description' => $this->description,
            'long_description' => $this->long_description,
            'public_ingredients' => $this->public_ingredients,
            'image_url' => PublicProductImageUrl::fromPath($this->image_path),
            'category' => [
                'slug' => $this->category->slug,
                'name' => $this->category->name,
            ],
            'tags' => $this->tags->map(fn ($tag): array => [
                'slug' => $tag->slug,
                'name' => $tag->name,
            ])->values()->all(),
            'prices' => [
                'unit' => $this->unit_price,
                'half_dozen' => $pricingFamily->value === 'empanada' ? $this->half_dozen_price : null,
                'dozen' => $pricingFamily->value === 'empanada' ? $this->dozen_price : null,
            ],
        ];
    }
}
