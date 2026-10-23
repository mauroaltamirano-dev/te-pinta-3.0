<?php

namespace App\Modules\Products\Http\Controllers;

use App\Modules\Products\Http\Resources\PublicProductResource;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\RawPriceDiscount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PublicProductCatalogController
{
    public function __invoke(Request $request): JsonResponse
    {
        $products = Product::query()
            ->select('products.*')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->where('products.is_available', true)
            ->where('categories.is_active', true)
            ->whereNull('categories.deleted_at')
            ->with(['category', 'subcategory', 'tags' => fn ($query) => $query->orderBy('name')])
            ->orderBy('categories.sort_order')
            ->orderBy('products.sort_order')
            ->orderBy('products.id')
            ->get();

        return response()->json([
            'data' => $products
                ->map(fn (Product $product): array => (new PublicProductResource($product))->resolve($request))
                ->all(),
            'categories' => Category::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['name', 'slug'])
                ->map(fn (Category $category): array => [
                    'slug' => $category->slug,
                    'name' => $category->name,
                ])->all(),
            'raw_price_discounts' => RawPriceDiscount::query()->pluck('amount', 'pricing_mode'),
        ]);
    }
}
