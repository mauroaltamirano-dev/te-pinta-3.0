<?php

namespace App\Modules\Orders\Actions;

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
    public function __construct(
        private OrderPricingEngine $pricing,
        private ResolveOrderCustomer $customers,
        private PersistOrderQuote $quotes,
    ) {}

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
                $customer = $this->customers->execute($data, $source);
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
                    ...$this->quotes->totals($quote),
                    'public_checkout_idempotency_hash' => $idempotencyHash,
                    'public_checkout_fingerprint' => $fingerprint,
                ]);

                $this->quotes->execute($order, $quote);

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

    private function assertSameFingerprint(Order $order, ?string $fingerprint): void
    {
        if ($order->public_checkout_fingerprint !== $fingerprint) {
            throw ValidationException::withMessages(['idempotency_key' => 'This idempotency key was already used for a different checkout.']);
        }
    }
}
