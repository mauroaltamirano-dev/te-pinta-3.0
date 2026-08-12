<?php

namespace App\Modules\Orders\Actions;

use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Data\OrderCheckoutData;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Pricing\OrderPricingEngine;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final readonly class CreateOrder
{
    public function __construct(private OrderPricingEngine $pricing) {}

    public function execute(OrderCheckoutData $data, OrderSource $source, ?int $adminId = null): Order
    {
        $idempotencyHash = $data->idempotencyKey === null ? null : hash_hmac('sha256', $data->idempotencyKey, (string) config('app.key'));
        $fingerprint = $data->idempotencyKey === null ? null : $data->fingerprint();

        if ($idempotencyHash !== null && ($existing = Order::withTrashed()->where('public_checkout_idempotency_hash', $idempotencyHash)->first())) {
            $this->assertSameFingerprint($existing, $fingerprint);

            return $existing->load(['items.compositions', 'toppings']);
        }

        try {
            $order = DB::transaction(function () use ($data, $source, $idempotencyHash, $fingerprint): Order {
                $customer = $this->resolveCustomer($data, $source);
                $quote = $this->pricing->quote($data->items, $data->condition, $data->addons, $data->deliveryType);

                $order = Order::query()->create([
                    'customer_id' => $customer->id,
                    'source' => $source,
                    'customer_name_snapshot' => $data->customerName,
                    'customer_phone_snapshot' => $data->customerPhone,
                    'status' => $source === OrderSource::PublicCheckout ? OrderStatus::PendingConfirmation : OrderStatus::Waiting,
                    'payment_status' => $data->paymentStatus,
                    'condition' => $data->condition,
                    'delivery_type' => $data->deliveryType,
                    'delivery_period' => $data->deliveryPeriod,
                    'delivery_date' => $data->deliveryDate,
                    'delivery_time' => $data->deliveryTime,
                    'delivery_address_snapshot' => $data->deliveryType->value === 'delivery' ? $data->deliveryAddress : null,
                    'notes' => $data->notes,
                    'items_subtotal' => $quote->productsSubtotal,
                    'toppings_subtotal' => $quote->addonsSubtotal,
                    'shipping_fee_snapshot' => $quote->shippingFee,
                    'combined_dozen_savings' => $quote->savings,
                    'total' => $quote->total,
                    'public_checkout_idempotency_hash' => $idempotencyHash,
                    'public_checkout_fingerprint' => $fingerprint,
                ]);

                foreach ($quote->lines as $line) {
                    $item = $order->items()->create([
                        'product_id' => $line->productId,
                        'product_name' => $line->productName ?? 'Combined dozen',
                        'pricing_mode' => $line->pricingMode,
                        'quantity' => $line->quantity,
                        'units_count' => $line->unitsCount,
                        'unit_price' => $line->unitPrice,
                        'line_total' => $line->lineTotal,
                        'regular_price_total' => $line->regularPriceTotal,
                        'savings' => $line->savings,
                    ]);
                    foreach ($line->composition as $part) {
                        $item->compositions()->create($part);
                    }
                }
                foreach ($quote->addons as $addon) {
                    $order->toppings()->create([
                        'order_addon_id' => $addon->addonId,
                        'name' => $addon->name,
                        'quantity' => $addon->quantity,
                        'unit_price' => $addon->unitPrice,
                        'subtotal' => $addon->lineTotal,
                    ]);
                }

                return $order->refresh()->load(['items.compositions', 'toppings']);
            });
        } catch (QueryException $exception) {
            if ($idempotencyHash === null || $exception->getCode() !== '23505') {
                throw $exception;
            }
            $order = Order::withTrashed()->where('public_checkout_idempotency_hash', $idempotencyHash)->firstOrFail();
            $this->assertSameFingerprint($order, $fingerprint);
            $order->load(['items.compositions', 'toppings']);
        }

        Log::info($source === OrderSource::PublicCheckout ? '[Orders] public_order.created' : '[Orders] order.created', [
            'module' => 'Orders', 'order_id' => $order->public_id, 'admin_id' => $adminId,
        ]);

        return $order;
    }

    private function resolveCustomer(OrderCheckoutData $data, OrderSource $source): Customer
    {
        if ($source === OrderSource::Admin && $data->customerId !== null) {
            return Customer::query()->findOrFail($data->customerId);
        }

        if ($source === OrderSource::PublicCheckout && $data->customerPhoneNormalized !== null) {
            $existing = Customer::query()->where('phone_normalized', $data->customerPhoneNormalized)->first();
            if ($existing) {
                return $existing;
            }
        }

        $attributes = [
            'name' => $data->customerName,
            'phone' => $data->customerPhone,
            'phone_normalized' => $data->customerPhoneNormalized,
            'address' => $data->customerAddress,
            'notes' => null,
            'created_at' => now(), 'updated_at' => now(),
        ];

        if ($source === OrderSource::Admin) {
            return Customer::query()->create($attributes);
        }

        Customer::query()->insertOrIgnore($attributes);

        return Customer::query()
            ->when($data->customerPhoneNormalized !== null, fn ($query) => $query->where('phone_normalized', $data->customerPhoneNormalized))
            ->firstOrFail();
    }

    private function assertSameFingerprint(Order $order, ?string $fingerprint): void
    {
        if ($order->public_checkout_fingerprint !== $fingerprint) {
            throw ValidationException::withMessages(['idempotency_key' => 'This idempotency key was already used for a different checkout.']);
        }
    }
}
