<?php

namespace App\Modules\Products\Http\Controllers;

use App\Modules\Orders\Enums\OrderSettingKey;
use App\Modules\Orders\Models\OrderSetting;
use App\Modules\Products\Actions\CreateProduct;
use App\Modules\Products\Actions\DeleteProduct;
use App\Modules\Products\Actions\UpdateProduct;
use App\Modules\Products\Data\ProductData;
use App\Modules\Products\Http\Requests\StoreProductRequest;
use App\Modules\Products\Http\Requests\UpdateProductRequest;
use App\Modules\Products\Http\Resources\AdminProductResource;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\RawPriceDiscount;
use App\Modules\Products\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class ProductManagementController
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Product::class);

        $products = Product::query()
            ->select('products.*')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->with(['category', 'tags' => fn ($query) => $query->orderBy('name')])
            ->orderBy('categories.sort_order')
            ->orderBy('products.sort_order')
            ->orderBy('products.id')
            ->get();

        return Inertia::render('admin/products/index', [
            'categories' => Category::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'name', 'slug', 'sort_order', 'is_active']),
            'products' => $products
                ->map(fn (Product $product): array => (new AdminProductResource($product))->resolve($request))
                ->all(),
            'tags' => Tag::query()->orderBy('name')->get(['id', 'name', 'slug']),
            'rawPriceDiscounts' => RawPriceDiscount::query()->pluck('amount', 'pricing_mode'),
            'empanadaUnitSurchargePercent' => OrderSetting::integer(OrderSettingKey::EmpanadaUnitSurchargePercent),
        ]);
    }

    public function store(StoreProductRequest $request, CreateProduct $createProduct): RedirectResponse
    {
        $createProduct->execute(ProductData::fromRequest($request), $request->file('image'));

        return to_route('admin.products.index')->with('success', 'Producto creado.');
    }

    public function update(UpdateProductRequest $request, Product $product, UpdateProduct $updateProduct): RedirectResponse
    {
        $updateProduct->execute($product, ProductData::fromRequest($request), $request->file('image'));

        return to_route('admin.products.index')->with('success', 'Producto actualizado.');
    }

    public function destroy(Product $product, DeleteProduct $deleteProduct): RedirectResponse
    {
        Gate::authorize('delete', $product);
        $deleteProduct->execute($product);

        return to_route('admin.products.index')->with('success', 'Producto eliminado.');
    }
}
