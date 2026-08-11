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
    public function __construct(private readonly UniqueSlug $uniqueSlug) {}

    public function execute(ProductData $data, ?UploadedFile $image): Product
    {
        $imagePath = $image?->store('products', 'public');

        if ($image && ! $imagePath) {
            throw new RuntimeException('The product image could not be stored.');
        }

        try {
            return DB::transaction(function () use ($data, $imagePath): Product {
                $product = Product::query()->create([
                    'category_id' => $data->categoryId,
                    'name' => $data->name,
                    'slug' => $this->uniqueSlug->for('products', $data->name),
                    'description' => $data->description,
                    'image_path' => $imagePath ?: null,
                    'sort_order' => $data->sortOrder,
                    'unit_price' => $data->unitPrice,
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
