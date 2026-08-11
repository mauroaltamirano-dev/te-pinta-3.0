<?php

namespace App\Modules\Orders\Models;

use App\Core\Contracts\PublicIdentifier;
use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Enums\DeliverySlot;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $public_id
 * @property OrderStatus $status
 * @property PaymentStatus $payment_status
 * @property DeliverySlot $delivery_slot
 */
final class Order extends Model implements PublicIdentifier
{
    use SoftDeletes;

    protected $fillable = ['customer_id', 'delivery_slot', 'delivery_date', 'delivery_time'];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'delivery_slot' => DeliverySlot::class,
            'delivery_date' => 'date',
            'total' => 'decimal:2',
        ];
    }

    public function publicIdentifier(): string
    {
        return $this->public_id;
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
