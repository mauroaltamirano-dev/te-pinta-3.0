<?php

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Data\OrderCheckoutData;
use App\Modules\Orders\Enums\DeliveryType;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentMethod;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Events\OrderPaymentConfirmed;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Pricing\OrderPricingEngine;
use App\Modules\Orders\Support\OrderBusinessDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final readonly class UpdateOrder
{
    public function __construct(
        private OrderPricingEngine $pricing,
        private ResolveOrderCustomer $customers,
        private PersistOrderQuote $quotes,
        private BuildAdminOrderFormOptions $formOptions,
        private ResolveOrderPayment $payments,
        private OrderBusinessDate $businessDate,
    ) {}

    public function execute(
        Order $order,
        OrderCheckoutData $data,
        OrderStatus $status,
        int $expectedVersion,
        int $adminId,
        ?string $closedEditReason,
    ): Order {
        $result = DB::transaction(function () use ($order, $data, $status, $expectedVersion, $adminId, $closedEditReason): Order {
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            if ((int) $locked->edit_version !== $expectedVersion) {
                throw ValidationException::withMessages(['expected_version' => 'El pedido fue modificado desde que lo abriste. Recargá antes de continuar.']);
            }
            if ($locked->status->isClosed() && $closedEditReason === null) {
                throw ValidationException::withMessages(['closed_edit' => 'Pedido cerrado — edición bloqueada. Habilitá la edición antes de continuar.']);
            }

            $data->assertContext($locked->delivery_type === DeliveryType::DineIn ? $locked->source : OrderSource::Admin);
            $locked->load(['items.compositions', 'toppings']);
            $wasPaid = $locked->payment_status === PaymentStatus::Paid;
            $oldItems = $this->formOptions->itemQuantities($locked);
            $oldAddons = $this->formOptions->addonQuantities($locked);
            $newItems = $this->canonicalSelections($data->items, 'product_id');
            $newAddons = $this->canonicalSelections($data->addons, 'addon_id');
            $economicChange = $oldItems !== $newItems
                || $oldAddons !== $newAddons
                || $locked->condition !== $data->condition
                || $locked->delivery_type !== $data->deliveryType
                || $locked->discount_type !== $data->discountType
                || $locked->discount_value !== $data->discountValue;
            if ($wasPaid && $economicChange) {
                throw ValidationException::withMessages([
                    'payment_status' => 'Un pago confirmado no permite modificar el importe; revertí el movimiento financiero antes de corregirlo.',
                ]);
            }
            $beforeTotal = (int) $locked->total;
            $quote = $economicChange
                ? $this->pricing->quote(
                    $data->items,
                    $data->condition,
                    $data->addons,
                    $data->deliveryType,
                    $data->discountType,
                    $data->discountValue,
                )
                : null;
            $paymentTotal = $economicChange ? $quote->total : $beforeTotal;
            $payment = $this->payments->forTotal($paymentTotal, $data->paymentStatus, $data->paymentMethod);
            $changedFields = $this->changedFields($locked, $data, $status, $payment['status'], $payment['method'], $oldItems, $newItems, $oldAddons, $newAddons);

            $customerAttributes = [];
            if ($data->customerId !== $locked->customer_id || ($locked->customer_id === null && ($data->customerName !== $locked->customer_name_snapshot || $data->customerPhone !== $locked->customer_phone_snapshot))) {
                $customer = $this->customers->execute($data, $locked->source === OrderSource::Pos ? OrderSource::Pos : OrderSource::Admin);
                $customerAttributes = [
                    'customer_id' => $customer?->id,
                    'customer_name_snapshot' => $data->customerName,
                    'customer_phone_snapshot' => $data->customerPhone,
                ];
            }

            $attributes = [
                ...$customerAttributes,
                'status' => $status,
                'payment_status' => $payment['status'],
                'payment_method' => $payment['method'],
                'condition' => $data->condition,
                'delivery_type' => $data->deliveryType,
                'delivery_period' => $data->deliveryPeriod,
                'delivery_date' => $data->deliveryDate,
                'delivery_time' => $data->deliveryTime,
                'delivery_address_snapshot' => $data->deliveryType->value === 'delivery' ? $data->deliveryAddress : null,
                'notes' => $data->notes,
                'edit_version' => $expectedVersion + 1,
            ];

            if ($economicChange) {
                // Current sellability is deliberately revalidated here. Historical unavailable
                // selections can survive only while the economic selection remains unchanged.
                assert($quote !== null);
                $attributes = [...$attributes, ...$this->quotes->totals($quote)];
                $locked->update($attributes);
                $this->quotes->execute($locked, $quote, true);
            } else {
                $locked->update($attributes);
            }

            $locked->revisions()->create([
                'admin_id' => $adminId,
                'order_status' => $locked->status->value,
                'total_before' => $beforeTotal,
                'total_after' => (int) $locked->total,
                'changed_fields' => ['fields' => $changedFields, 'economic_change' => $economicChange],
                'reason' => $closedEditReason,
                'created_at' => now(),
            ]);

            if (! $wasPaid && $locked->payment_status === PaymentStatus::Paid) {
                event(new OrderPaymentConfirmed(
                    orderId: (int) $locked->id,
                    amount: (int) $locked->total,
                    confirmedOn: $this->businessDate->today(),
                    adminId: $adminId,
                ));
            }

            return $locked->refresh()->load(['items.compositions', 'toppings']);
        });

        Log::info('[Orders] order.updated', [
            'module' => 'Orders',
            'order_id' => $result->public_id,
            'admin_id' => $adminId,
            'status' => $result->status->value,
            'total_before' => (int) $order->total,
            'total_after' => (int) $result->total,
        ]);

        return $result;
    }

    /** @param array<int, array<string, int>> $selections
     * @return array<int, int>
     */
    private function canonicalSelections(array $selections, string $idKey): array
    {
        $canonical = [];
        foreach ($selections as $selection) {
            $canonical[$selection[$idKey]] = $selection['quantity'];
        }
        ksort($canonical);

        return $canonical;
    }

    /** @param array<int, int> $oldItems
     * @param  array<int, int>  $newItems
     * @param  array<int, int>  $oldAddons
     * @param  array<int, int>  $newAddons
     * @return array<int, string>
     */
    private function changedFields(Order $order, OrderCheckoutData $data, OrderStatus $status, PaymentStatus $paymentStatus, ?PaymentMethod $paymentMethod, array $oldItems, array $newItems, array $oldAddons, array $newAddons): array
    {
        $changes = [];
        $comparisons = [
            'customer' => $order->customer_id !== $data->customerId
                || ($order->customer_id === null && ($order->customer_name_snapshot !== $data->customerName || $order->customer_phone_snapshot !== $data->customerPhone)),
            'items' => $oldItems !== $newItems,
            'addons' => $oldAddons !== $newAddons,
            'condition' => $order->condition !== $data->condition,
            'delivery_type' => $order->delivery_type !== $data->deliveryType,
            'delivery_period' => $order->delivery_period !== $data->deliveryPeriod,
            'delivery_date' => ($order->delivery_date === null ? null : CarbonImmutable::parse($order->delivery_date)->toDateString()) !== $data->deliveryDate,
            'delivery_time' => ($order->delivery_time === null ? null : CarbonImmutable::parse($order->delivery_time)->format('H:i')) !== $data->deliveryTime,
            'delivery_address' => ($order->delivery_address_snapshot ?? null) !== $data->deliveryAddress,
            'notes' => ($order->notes ?? null) !== $data->notes,
            'payment_status' => $order->payment_status !== $paymentStatus,
            'payment_method' => $order->payment_method !== $paymentMethod,
            'discount' => $order->discount_type !== $data->discountType || $order->discount_value !== $data->discountValue,
            'status' => $order->status !== $status,
        ];
        foreach ($comparisons as $field => $changed) {
            if ($changed) {
                $changes[] = $field;
            }
        }

        return $changes;
    }
}
