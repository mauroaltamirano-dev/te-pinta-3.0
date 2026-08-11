<?php

namespace App\Modules\Orders\Models;

use App\Modules\Orders\Enums\PricingMode;
use App\Modules\Products\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OrderItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['product_id', 'product_name', 'pricing_mode', 'quantity', 'unit_price', 'line_total'];

    protected function casts(): array
    {
        return ['pricing_mode' => PricingMode::class, 'unit_price' => 'decimal:2', 'line_total' => 'decimal:2'];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
