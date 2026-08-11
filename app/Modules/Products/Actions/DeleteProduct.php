<?php

namespace App\Modules\Products\Actions;

use App\Modules\Products\Models\Product;

final class DeleteProduct
{
    public function execute(Product $product): void
    {
        $product->delete();
    }
}
