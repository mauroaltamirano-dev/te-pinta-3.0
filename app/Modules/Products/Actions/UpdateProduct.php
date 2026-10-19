<?php

namespace App\Modules\Products\Actions;

use App\Modules\Products\Data\ProductData;
use App\Modules\Products\Enums\ProductPricingFamily;
use App\Modules\Products\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class UpdateProduct
{
    public function __construct(private readonly RecalculateEmpanadaUnitPrices $unitPrices) {}

    public function execute(Product $product, ProductData $data, ?UploadedFile $image): Product
    {
        $oldImagePath = $product->image_path;
        $newImagePath = $image?->store('products', 'public');

        if ($image && ! $newImagePath) {
            throw new RuntimeException('No se pudo guardar la imagen del producto.');
        }

        $removeImage = $data->removeImage && ! $newImagePath;

        try {
            DB::transaction(function () use ($product, $data, $newImagePath, $removeImage): void {
                // An empanada keeps its stored unit price unless its dozen changes or it becomes an empanada.
                $unitPrice = $data->unitPrice ?? ($product->pricing_family === ProductPricingFamily::Empanada && $product->dozen_price === $data->dozenPrice
                    ? $product->unit_price
                    : $this->unitPrices->forDozen($data->dozenPrice));
                $product->update([
                    'category_id' => $data->categoryId,
                    'pricing_family' => $data->pricingFamily,
                    'name' => $data->name,
                    'slug' => $data->slug ?? $product->slug,
                    'description' => $data->description,
                    'long_description' => $data->longDescription,
                    'public_ingredients' => $data->publicIngredients,
                    'image_path' => $newImagePath ?: ($removeImage ? null : $product->image_path),
                    'sort_order' => $data->sortOrder,
                    'unit_price' => $unitPrice,
                    'half_dozen_price' => $data->halfDozenPrice,
                    'dozen_price' => $data->dozenPrice,
                    'is_available' => $data->isAvailable,
                ]);

                $product->tags()->sync($data->tagIds);
            });
        } catch (Throwable $throwable) {
            if ($newImagePath) {
                Storage::disk('public')->delete($newImagePath);
            }

            throw $throwable;
        }

        if (($newImagePath || $removeImage) && $this->isManagedImage($oldImagePath) && ! Storage::disk('public')->delete($oldImagePath)) {
            Log::warning('[Products] product.image_cleanup_failed', ['product_id' => $product->id]);
        }

        return $product->refresh();
    }

    private function isManagedImage(?string $path): bool
    {
        return $path !== null
            && str_starts_with($path, 'products/')
            && ! str_contains($path, '..');
    }
}
