<?php

namespace App\Modules\Products\Actions;

use App\Modules\Products\Data\ProductData;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Support\UniqueSlug;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class CreateProduct
{
    public function __construct(
        private readonly UniqueSlug $uniqueSlug,
        private readonly RecalculateEmpanadaUnitPrices $unitPrices,
    ) {}

    public function execute(ProductData $data, ?UploadedFile $image): Product
    {
        $imagePath = $image?->store('products', 'public');

        if ($image && ! $imagePath) {
            throw new RuntimeException('No se pudo guardar la imagen del producto.');
        }

        try {
            return DB::transaction(function () use ($data, $imagePath): Product {
                $product = Product::query()->create([
                    'category_id' => $data->categoryId,
                    'pricing_family' => $data->pricingFamily,
                    'name' => $data->name,
                    'slug' => $data->slug ?? $this->uniqueSlug->for('products', $data->name),
                    'description' => $data->description,
                    'long_description' => $data->longDescription,
                    'public_ingredients' => $data->publicIngredients,
                    'image_path' => $imagePath ?: null,
                    'sort_order' => $data->sortOrder,
                    'unit_price' => $data->unitPrice ?? $this->unitPrices->forDozen($data->dozenPrice),
                    'half_dozen_price' => $data->halfDozenPrice,
                    'dozen_price' => $data->dozenPrice,
                    'is_available' => $data->isAvailable,
                ]);

                $product->tags()->sync($data->tagIds);

                return $product;
            });
        } catch (Throwable $throwable) {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }

            throw $throwable;
        }
    }
}
