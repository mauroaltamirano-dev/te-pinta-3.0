<?php

namespace App\Modules\Products\Http\Controllers;

use App\Modules\Products\Http\Resources\PublicProductResource;
use App\Modules\Products\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PublicProductDetailController
{
    public function __invoke(Request $request, string $slug): Response
    {
        $product = Product::query()
            ->where('slug', $slug)
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->with(['category', 'tags' => fn ($query) => $query->orderBy('name')])
            ->firstOrFail();

        $publicProduct = (new PublicProductResource($product))->resolve($request);
        $description = $product->description ?: "Conocé {$product->name} y hacé tu pedido en Te Pinta.";
        $url = route('public.products.show', ['slug' => $product->slug]);
        $image = $publicProduct['image_url'] ? url($publicProduct['image_url']) : null;
        $seo = [
            'title' => "{$product->name} | Te Pinta",
            'description' => $description,
            'url' => $url,
            'image' => $image,
        ];

        return Inertia::render('public/product-detail', [
            'product' => [...$publicProduct, 'is_available' => $product->is_available],
            'seo' => $seo,
        ])->withViewData('publicSeo', $seo);
    }
}
