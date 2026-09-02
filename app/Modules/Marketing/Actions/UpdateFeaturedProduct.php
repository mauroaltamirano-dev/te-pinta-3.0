<?php

namespace App\Modules\Marketing\Actions;

use App\Modules\Marketing\Data\FeaturedProductData;
use App\Modules\Marketing\Models\FeaturedProduct;
use App\Modules\Marketing\Queries\SelectableFeaturedProducts;
use App\Modules\Marketing\Support\FeaturedProductOverlap;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class UpdateFeaturedProduct
{
    public function __construct(
        private FeaturedProductOverlap $overlap,
        private SelectableFeaturedProducts $selectableProducts,
    ) {}

    public function execute(FeaturedProduct $featuredProduct, FeaturedProductData $data, int $adminId): FeaturedProduct
    {
        try {
            $updated = DB::transaction(function () use ($featuredProduct, $data): FeaturedProduct {
                $locked = FeaturedProduct::query()->whereKey($featuredProduct->id)->lockForUpdate()->firstOrFail();

                if ($locked->product_id !== $data->productId) {
                    $this->selectableProducts->findOrFail($data->productId);
                }

                $this->overlap->ensureAvailable($data, $locked->id);
                $locked->update($data->toArray());

                return $locked;
            });
        } catch (QueryException $exception) {
            $this->overlap->mapConstraintViolation($exception);
        }

        Log::info('[Marketing] featured_product.updated', [
            'module' => 'Marketing',
            'featured_product_id' => $updated->id,
            'product_id' => $updated->product_id,
            'admin_id' => $adminId,
        ]);

        return $updated->refresh();
    }
}
