<?php

namespace App\Modules\Products\Http\Resources;

use App\Modules\Products\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin Product */
final class AdminProductResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'long_description' => $this->long_description,
            'public_ingredients' => $this->public_ingredients,
            'image_url' => $this->image_path ? Storage::disk('public')->url($this->image_path) : null,
            'sort_order' => $this->sort_order,
            'unit_price' => $this->unit_price,
            'half_dozen_price' => $this->half_dozen_price,
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
