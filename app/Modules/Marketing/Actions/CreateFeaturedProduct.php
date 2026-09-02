<?php

namespace App\Modules\Marketing\Actions;

use App\Modules\Marketing\Data\FeaturedProductData;
use App\Modules\Marketing\Models\FeaturedProduct;
use App\Modules\Marketing\Queries\SelectableFeaturedProducts;
use App\Modules\Marketing\Support\FeaturedProductOverlap;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class CreateFeaturedProduct
{
    public function __construct(
        private FeaturedProductOverlap $overlap,
        private SelectableFeaturedProducts $selectableProducts,
    ) {}

    public function execute(FeaturedProductData $data, int $adminId): FeaturedProduct
    {
        try {
            $featuredProduct = DB::transaction(function () use ($data): FeaturedProduct {
                $this->selectableProducts->findOrFail($data->productId);
                $this->overlap->ensureAvailable($data);

                return FeaturedProduct::query()->create($data->toArray());
            });
        } catch (QueryException $exception) {
            $this->overlap->mapConstraintViolation($exception);
        }

        Log::info('[Marketing] featured_product.created', [
            'module' => 'Marketing',
            'featured_product_id' => $featuredProduct->id,
            'product_id' => $featuredProduct->product_id,
            'admin_id' => $adminId,
        ]);

        return $featuredProduct->refresh();
    }
}
