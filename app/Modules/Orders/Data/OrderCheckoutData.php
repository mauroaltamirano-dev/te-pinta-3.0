<?php

namespace App\Modules\Orders\Data;

use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Enums\DeliveryPeriod;
use App\Modules\Orders\Enums\DeliveryType;
use App\Modules\Orders\Enums\DiscountType;
use App\Modules\Orders\Enums\OrderCondition;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\PaymentMethod;
use App\Modules\Orders\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

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
        public ?DeliveryPeriod $deliveryPeriod,
        public ?string $deliveryDate,
        public ?string $deliveryTime,
        public ?string $deliveryAddress,
        public ?string $notes,
        public ?int $customerId,
        public ?string $customerName,
        public ?string $customerPhone,
        public ?string $customerPhoneNormalized,
        public ?string $customerAddress,
        public PaymentStatus $paymentStatus,
        public ?PaymentMethod $paymentMethod = null,
        public ?DiscountType $discountType = null,
        public ?int $discountValue = null,
        public ?string $idempotencyKey = null,
    ) {}

    /** @param array<int, array{product_id: int, quantity: int}>|null $resolvedItems
     * @param  array<int, array{addon_id: int, quantity: int}>|null  $resolvedAddons
     * @param  array{date: string, period: DeliveryPeriod}|null  $orderingWindow  public checkout: the server sets the schedule
     */
    public static function fromRequest(FormRequest $request, bool $public, ?array $resolvedItems = null, ?array $resolvedAddons = null, ?array $orderingWindow = null): self
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
            deliveryPeriod: $orderingWindow['period'] ?? ($request->validated('delivery_period') === null ? null : DeliveryPeriod::from($request->validated('delivery_period'))),
            deliveryDate: $orderingWindow['date'] ?? $request->validated('delivery_date'),
            deliveryTime: $request->validated('delivery_time'),
            deliveryAddress: $request->validated('delivery_address'),
            notes: $request->validated('notes'),
            customerId: $public ? null : $request->validated('customer_id'),
            customerName: $customerName,
            customerPhone: $customerPhone,
            customerPhoneNormalized: $customerPhoneNormalized,
            customerAddress: $request->validated('delivery_type') === DeliveryType::DineIn->value ? null : $customerAddress,
            paymentStatus: $public ? PaymentStatus::Unpaid : PaymentStatus::from($request->validated('payment_status', 'unpaid')),
            paymentMethod: $public ? null : ($request->validated('payment_method') === null ? null : PaymentMethod::from($request->validated('payment_method'))),
            discountType: $public || $request->validated('discount_type') === null ? null : DiscountType::from($request->validated('discount_type')),
            discountValue: $public ? null : $request->validated('discount_value'),
            idempotencyKey: $public ? $request->validated('idempotency_key') : null,
        );
    }

    public function assertContext(OrderSource $source): void
    {
        $dineIn = $this->deliveryType === DeliveryType::DineIn;
        $public = $source === OrderSource::PublicCheckout;
        Validator::make([
            'source' => $source->value,
            'idempotency_key' => $this->idempotencyKey,
            'customer_id' => $this->customerId,
            'customer' => ['name' => $this->customerName, 'phone' => $this->customerPhone, 'phone_normalized' => $this->customerPhoneNormalized, 'address' => $this->customerAddress],
            'delivery_date' => $this->deliveryDate,
            'delivery_period' => $this->deliveryPeriod?->value,
            'delivery_time' => $this->deliveryTime,
            'delivery_address' => $this->deliveryAddress,
        ], [
            'source' => [Rule::in($dineIn ? [OrderSource::Pos->value] : array_column(OrderSource::cases(), 'value'))],
            'idempotency_key' => $public ? ['nullable', 'string'] : ['prohibited'],
            'customer_id' => $public ? ['prohibited'] : ['nullable', 'integer', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'customer.name' => [$dineIn ? 'nullable' : 'required', 'string', 'max:160'],
            'customer.phone' => [$public ? 'required' : 'nullable', 'string', 'max:40'],
            'customer.phone_normalized' => [$public ? 'required' : 'nullable', 'string', 'max:40'],
            'customer.address' => [$dineIn ? 'prohibited' : 'nullable', 'nullable', 'string', 'max:1000'],
            'delivery_date' => [$dineIn ? 'prohibited' : 'required', 'nullable', 'date_format:Y-m-d'],
            'delivery_period' => [$dineIn ? 'prohibited' : 'required', 'nullable', Rule::enum(DeliveryPeriod::class)],
            'delivery_time' => [$dineIn ? 'prohibited' : 'nullable', 'nullable', 'date_format:H:i'],
            'delivery_address' => [$dineIn ? 'prohibited' : ($this->deliveryType === DeliveryType::Delivery ? 'required' : 'nullable'), 'nullable', 'string', 'max:1000'],
        ])->validate();
    }

    public function fingerprint(): string
    {
        $payload = get_object_vars($this);
        unset($payload['idempotencyKey']);

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
