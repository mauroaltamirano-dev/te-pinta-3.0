<?php

namespace App\Modules\Marketing\Queries;

use App\Modules\Products\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

final class SelectableFeaturedProducts
{
    /** @return Builder<Product> */
    public function query(): Builder
    {
        return Product::query()
            ->where('is_available', true)
            ->whereHas('category', fn (Builder $category) => $category->where('is_active', true));
    }

    public function findOrFail(int $productId): Product
    {
        $product = $this->query()->find($productId);

        if ($product === null) {
            throw ValidationException::withMessages([
                'product_id' => 'Seleccioná un producto disponible en la tienda pública.',
            ]);
        }

        return $product;
    }
}
