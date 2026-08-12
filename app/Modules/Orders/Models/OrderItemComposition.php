<?php

namespace App\Modules\Orders\Models;

use App\Modules\Products\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OrderItemComposition extends Model
{
    public $timestamps = false;

    protected $fillable = ['product_id', 'product_name', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
