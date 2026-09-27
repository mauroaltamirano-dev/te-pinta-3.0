<?php

namespace App\Modules\Products\Http\Resources;

use App\Modules\Products\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin Product */
final class PublicProductResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'long_description' => $this->long_description,
            'public_ingredients' => $this->public_ingredients,
            'image_url' => $this->image_path ? Storage::disk('public')->url($this->image_path) : null,
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
                'half_dozen' => $this->half_dozen_price,
                'dozen' => $this->dozen_price,
            ],
        ];
    }
}
