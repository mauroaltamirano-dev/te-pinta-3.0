<?php

namespace App\Modules\Orders\Models;

use App\Core\Contracts\PublicIdentifier;
use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Enums\DeliveryPeriod;
use App\Modules\Orders\Enums\DeliveryType;
use App\Modules\Orders\Enums\DiscountType;
use App\Modules\Orders\Enums\OrderCondition;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentMethod;
use App\Modules\Orders\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $public_id
 * @property int $customer_id
 * @property int $edit_version
 * @property int|numeric-string $total
 * @property int|numeric-string|null $items_base_subtotal
 * @property int|numeric-string|null $cooking_surcharge
 * @property int|numeric-string|null $items_cooked_subtotal
 * @property int|numeric-string|null $raw_discount
 * @property int|numeric-string $items_subtotal
 * @property int|numeric-string $toppings_subtotal
 * @property int|numeric-string $shipping_fee_snapshot
 * @property int|numeric-string $combined_dozen_savings
 * @property OrderStatus $status
 * @property PaymentStatus $payment_status
 * @property PaymentMethod|null $payment_method
 * @property DiscountType|null $discount_type
 * @property int|null $discount_value
 * @property int|numeric-string $discount_amount
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
        'payment_method',
        'condition',
        'delivery_type',
        'delivery_period',
        'delivery_date',
        'delivery_time',
        'delivery_address_snapshot',
        'notes',
        'items_base_subtotal',
        'cooking_surcharge',
        'items_cooked_subtotal',
        'raw_discount',
        'items_subtotal',
        'toppings_subtotal',
        'shipping_fee_snapshot',
        'combined_dozen_savings',
        'discount_type',
        'discount_value',
        'discount_amount',
        'total',
        'public_checkout_idempotency_hash',
        'public_checkout_fingerprint',
        'edit_version',
    ];

    protected function casts(): array
    {
        return [
            'source' => OrderSource::class,
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'payment_method' => PaymentMethod::class,
            'discount_type' => DiscountType::class,
            'discount_value' => 'integer',
            'discount_amount' => 'integer',
            'condition' => OrderCondition::class,
            'delivery_type' => DeliveryType::class,
            'delivery_period' => DeliveryPeriod::class,
            'delivery_date' => 'date',
            'items_base_subtotal' => 'integer',
            'cooking_surcharge' => 'integer',
            'items_cooked_subtotal' => 'integer',
            'raw_discount' => 'integer',
            'items_subtotal' => 'integer',
            'toppings_subtotal' => 'integer',
            'shipping_fee_snapshot' => 'integer',
            'combined_dozen_savings' => 'integer',
            'total' => 'integer',
            'edit_version' => 'integer',
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

    /** @return HasMany<OrderRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(OrderRevision::class);
    }
}
