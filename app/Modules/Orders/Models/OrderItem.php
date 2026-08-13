<?php

namespace App\Modules\Orders\Models;

use App\Modules\Orders\Enums\PricingMode;
use App\Modules\Products\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class OrderItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'product_name',
        'pricing_mode',
        'quantity',
        'units_count',
        'unit_price',
        'line_total',
        'base_unit_price',
        'base_line_total',
        'cooking_surcharge',
        'regular_price_total',
        'savings',
    ];

    protected function casts(): array
    {
        return [
            'pricing_mode' => PricingMode::class,
            'quantity' => 'integer',
            'units_count' => 'integer',
            'unit_price' => 'integer',
            'line_total' => 'integer',
            'base_unit_price' => 'integer',
            'base_line_total' => 'integer',
            'cooking_surcharge' => 'integer',
            'regular_price_total' => 'integer',
            'savings' => 'integer',
        ];
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

    /** @return HasMany<OrderItemComposition, $this> */
    public function compositions(): HasMany
    {
        return $this->hasMany(OrderItemComposition::class);
    }
}
