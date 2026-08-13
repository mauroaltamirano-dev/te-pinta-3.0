<?php

namespace App\Modules\Orders\Models;

use App\Core\Contracts\PublicIdentifier;
use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Enums\DeliveryPeriod;
use App\Modules\Orders\Enums\DeliveryType;
use App\Modules\Orders\Enums\OrderCondition;
use App\Modules\Orders\Enums\OrderSource;
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
 * @property OrderSource $source
 * @property OrderCondition $condition
 * @property DeliveryType $delivery_type
 * @property DeliveryPeriod $delivery_period
 */
final class Order extends Model implements PublicIdentifier
{
    use SoftDeletes;

    protected $fillable = [
        'customer_id',
        'source',
        'customer_name_snapshot',
        'customer_phone_snapshot',
        'status',
        'payment_status',
        'condition',
        'delivery_type',
        'delivery_period',
        'delivery_date',
        'delivery_time',
        'delivery_address_snapshot',
        'notes',
        'items_base_subtotal',
        'cooking_surcharge',
        'items_subtotal',
        'toppings_subtotal',
        'shipping_fee_snapshot',
        'combined_dozen_savings',
        'total',
        'public_checkout_idempotency_hash',
        'public_checkout_fingerprint',
    ];

    protected function casts(): array
    {
        return [
            'source' => OrderSource::class,
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'condition' => OrderCondition::class,
            'delivery_type' => DeliveryType::class,
            'delivery_period' => DeliveryPeriod::class,
            'delivery_date' => 'date',
            'items_base_subtotal' => 'integer',
            'cooking_surcharge' => 'integer',
            'items_subtotal' => 'integer',
            'toppings_subtotal' => 'integer',
            'shipping_fee_snapshot' => 'integer',
            'combined_dozen_savings' => 'integer',
            'total' => 'integer',
        ];
    }

    public function publicIdentifier(): string
    {
        return $this->public_id;
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
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

    /** @return HasMany<OrderTopping, $this> */
    public function toppings(): HasMany
    {
        return $this->hasMany(OrderTopping::class);
    }
}
