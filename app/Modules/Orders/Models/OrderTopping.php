<?php

namespace App\Modules\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OrderTopping extends Model
{
    public $timestamps = false;

    protected $fillable = ['order_addon_id', 'name', 'quantity', 'unit_price', 'subtotal'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'subtotal' => 'integer',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<OrderAddon, $this> */
    public function addon(): BelongsTo
    {
        return $this->belongsTo(OrderAddon::class, 'order_addon_id');
    }
}
