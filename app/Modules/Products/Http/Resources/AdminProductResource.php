<?php

namespace App\Modules\Products\Http\Resources;

use App\Modules\Products\Enums\ProductPricingFamily;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Pricing\HomogeneousHalfDozenPrice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin Product */
final class AdminProductResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $pricingFamily = $this->pricing_family ?? ProductPricingFamily::Empanada;

        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'pricing_family' => $pricingFamily->value,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'long_description' => $this->long_description,
            'public_ingredients' => $this->public_ingredients,
            'image_url' => $this->image_path ? Storage::disk('public')->url($this->image_path) : null,
            'sort_order' => $this->sort_order,
            'unit_price' => $this->unit_price,
            'half_dozen_price' => $this->half_dozen_price,
            'homogeneous_half_dozen_surcharge' => $this->pricing_family === ProductPricingFamily::Empanada
                ? HomogeneousHalfDozenPrice::surcharge($this->half_dozen_price, $this->dozen_price) : null,
            'dozen_price' => $this->dozen_price,
            'is_available' => $this->is_available,
            'tags' => $this->tags->map(fn ($tag): array => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
            ])->values()->all(),
        ];
    }
}
