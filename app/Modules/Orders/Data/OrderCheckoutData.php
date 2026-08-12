<?php

namespace App\Modules\Orders\Data;

use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Enums\DeliveryPeriod;
use App\Modules\Orders\Enums\DeliveryType;
use App\Modules\Orders\Enums\OrderCondition;
use App\Modules\Orders\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;

final readonly class OrderCheckoutData
{
    /** @param array<int, array{product_id: int, quantity: int}> $items
     * @param  array<int, array{addon_id: int, quantity: int}>  $addons
     */
    public function __construct(
        public array $items,
        public array $addons,
        public OrderCondition $condition,
        public DeliveryType $deliveryType,
        public DeliveryPeriod $deliveryPeriod,
        public string $deliveryDate,
        public ?string $deliveryTime,
        public ?string $deliveryAddress,
        public ?string $notes,
        public ?int $customerId,
        public string $customerName,
        public ?string $customerPhone,
        public ?string $customerPhoneNormalized,
        public ?string $customerAddress,
        public PaymentStatus $paymentStatus,
        public ?string $idempotencyKey = null,
    ) {}

    /** @param array<int, array{product_id: int, quantity: int}>|null $resolvedItems
     * @param  array<int, array{addon_id: int, quantity: int}>|null  $resolvedAddons
     */
    public static function fromRequest(FormRequest $request, bool $public, ?array $resolvedItems = null, ?array $resolvedAddons = null): self
    {
        /** @var Customer|null $selectedCustomer */
        $selectedCustomer = ! $public && $request->validated('customer_id') !== null
            ? Customer::query()->findOrFail($request->validated('customer_id'))
            : null;

        $customerName = $selectedCustomer !== null ? $selectedCustomer->name : $request->validated('customer.name');
        $customerPhone = $selectedCustomer !== null ? $selectedCustomer->phone : $request->validated('customer.phone');
        $customerPhoneNormalized = $selectedCustomer !== null ? $selectedCustomer->phone_normalized : $request->validated('customer.phone_normalized');
        $customerAddress = $selectedCustomer !== null ? $selectedCustomer->address : $request->validated('customer.address');

        return new self(
            items: $resolvedItems ?? $request->validated('items'),
            addons: $resolvedAddons ?? $request->validated('addons', []),
            condition: OrderCondition::from($request->validated('condition')),
            deliveryType: DeliveryType::from($request->validated('delivery_type')),
            deliveryPeriod: DeliveryPeriod::from($request->validated('delivery_period')),
            deliveryDate: $request->validated('delivery_date'),
            deliveryTime: $request->validated('delivery_time'),
            deliveryAddress: $request->validated('delivery_address'),
            notes: $request->validated('notes'),
            customerId: $public ? null : $request->validated('customer_id'),
            customerName: $customerName,
            customerPhone: $customerPhone,
            customerPhoneNormalized: $customerPhoneNormalized,
            customerAddress: $customerAddress,
            paymentStatus: $public ? PaymentStatus::Unpaid : PaymentStatus::from($request->validated('payment_status', 'unpaid')),
            idempotencyKey: $public ? $request->validated('idempotency_key') : null,
        );
    }

    public function fingerprint(): string
    {
        $payload = get_object_vars($this);
        unset($payload['idempotencyKey']);

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
