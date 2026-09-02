<?php

namespace Tests\Feature\Orders;

use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Management\Models\ManagementOrderPayment;
use App\Modules\Management\Models\Wallet;
use App\Modules\Orders\Actions\UpdateOrder;
use App\Modules\Orders\Data\OrderCheckoutData;
use App\Modules\Orders\Enums\DeliveryPeriod;
use App\Modules\Orders\Enums\DeliveryType;
use App\Modules\Orders\Enums\OrderCondition;
use App\Modules\Orders\Enums\OrderSettingKey;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderAddon;
use App\Modules\Orders\Models\OrderSetting;
use App\Modules\Products\Enums\PriceTier;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\RawPriceDiscount;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class AdminOrderEditingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Customer $customer;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Wallet::query()->create(['name' => 'Order receipts', 'is_order_payment_default' => true]);
        $this->admin = User::factory()->administrator()->create();
        $this->customer = Customer::query()->create(['name' => 'Original customer', 'phone' => '111', 'address' => 'Private customer address']);
        $category = Category::query()->create(['name' => 'Empanadas', 'slug' => 'empanadas-edit', 'sort_order' => 1, 'is_active' => true]);
        $this->product = Product::query()->create([
            'category_id' => $category->id, 'name' => 'Salteña', 'slug' => 'saltena-edit',
            'unit_price' => 1800, 'half_dozen_price' => 10000, 'dozen_price' => 18000,
            'is_available' => true, 'sort_order' => 1,
        ]);
        RawPriceDiscount::query()->insert([
            ['pricing_mode' => PriceTier::Unit->value, 'amount' => 200],
            ['pricing_mode' => PriceTier::HalfDozen->value, 'amount' => 1000],
            ['pricing_mode' => PriceTier::Dozen->value, 'amount' => 2000],
        ]);
    }

    public function test_edit_hydrates_real_quantities_and_active_statuses_update_without_changing_id(): void
    {
        $publicIds = [];
        foreach ([OrderStatus::PendingConfirmation, OrderStatus::Waiting, OrderStatus::Prepared] as $status) {
            $order = $this->createOrder($status);
            $publicId = $order->public_id;
            $publicIds[] = $publicId;
            $this->actingAs($this->admin)->get(route('admin.orders.edit', $order))->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('admin/orders/edit')
                    ->where('order.id', $order->public_id)
                    ->where('order.draft.items.0.quantity', 6)
                    ->where('order.closed_edit_required', false));

            $payload = $this->updatePayload($order);
            $payload['items'][0]['quantity'] = 7;
            $this->actingAs($this->admin)->put(route('admin.orders.update', $order), $payload)->assertRedirect(route('admin.orders.show', $order));
            $order->refresh();
            $this->assertSame($status, $order->status);
            $this->assertSame($publicId, $order->public_id);
            $this->assertSame(10600, (int) $order->total);
            $this->assertSame(2, $order->edit_version);
            $this->assertSame(6, (int) $order->items->firstWhere('pricing_mode', PriceTier::HalfDozen)?->units_count);
        }
        $this->assertCount(3, array_unique($publicIds));
        foreach ($publicIds as $publicId) {
            $this->assertMatchesRegularExpression('/^TP-\d{6}$/', $publicId);
        }
    }

    public function test_closed_order_requires_version_bound_reason_grant_and_consumes_it(): void
    {
        foreach ([OrderStatus::Delivered, OrderStatus::Cancelled] as $status) {
            $order = $this->createOrder($status);
            $this->actingAs($this->admin)->put(route('admin.orders.update', $order), $this->updatePayload($order))
                ->assertSessionHasErrors('closed_edit');
            $this->actingAs($this->admin)->post(route('admin.orders.enable-edit', $order), ['expected_version' => 1, 'reason' => ''])
                ->assertSessionHasErrors('reason');
            $this->actingAs($this->admin)->post(route('admin.orders.enable-edit', $order), ['expected_version' => 1, 'reason' => 'Administrative correction'])
                ->assertRedirect(route('admin.orders.edit', $order));
            $payload = $this->updatePayload($order);
            $payload['notes'] = 'Corrected note';
            $this->actingAs($this->admin)->put(route('admin.orders.update', $order), $payload)->assertRedirect();
            $this->assertSame($status, $order->refresh()->status);
            $this->assertDatabaseHas('order_revisions', ['order_id' => $order->id, 'reason' => 'Administrative correction', 'total_before' => 9000, 'total_after' => 9000]);
            $payload['expected_version'] = 2;
            $this->actingAs($this->admin)->put(route('admin.orders.update', $order), $payload)->assertSessionHasErrors('closed_edit');
        }
    }

    public function test_repricing_customer_snapshots_delivery_payment_and_audit_are_backend_authoritative(): void
    {
        $order = $this->createOrder(OrderStatus::Waiting);
        $other = Customer::query()->create(['name' => 'Other customer', 'phone' => '222', 'address' => 'Other private address']);
        $payload = $this->updatePayload($order);
        $payload['items'][0]['quantity'] = 7;
        $payload['condition'] = 'cooked';
        $payload['delivery_type'] = 'delivery';
        $payload['delivery_address'] = 'Order-only corrected address';
        $payload['customer_id'] = $other->id;
        $payload['payment_status'] = 'paid';
        $payload['payment_method'] = 'debit_card';
        $payload['notes'] = 'Private corrected note';
        $payload['delivery_period'] = 'midday';
        $payload['delivery_time'] = '12:30';
        $payload['total'] = 1;

        $this->actingAs($this->admin)->put(route('admin.orders.update', $order), $payload)->assertSessionHasErrors('total');
        unset($payload['total']);
        $this->actingAs($this->admin)->put(route('admin.orders.update', $order), $payload)->assertRedirect();
        $order->refresh();
        $this->assertSame(11800, (int) $order->total);
        $this->assertSame('Other customer', $order->customer_name_snapshot);
        $this->assertSame('Order-only corrected address', $order->delivery_address_snapshot);
        $this->assertSame('Other private address', $other->fresh()->address);
        $this->assertSame('paid', $order->payment_status->value);
        $payment = ManagementOrderPayment::query()->sole();
        $this->assertSame($order->id, $payment->order_id);
        $this->assertSame(11800, (int) $payment->amount);
        $revision = $order->revisions()->sole();
        $this->assertSame(['fields' => ['customer', 'items', 'condition', 'delivery_type', 'delivery_period', 'delivery_time', 'delivery_address', 'notes', 'payment_status', 'payment_method'], 'economic_change' => true], $revision->changed_fields);
        $this->assertStringNotContainsString('Order-only corrected address', json_encode($revision->changed_fields, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('Private corrected note', json_encode($revision->changed_fields, JSON_THROW_ON_ERROR));
    }

    public function test_selected_customer_hydration_does_not_trigger_its_own_phone_unique_rule(): void
    {
        $this->customer->update(['phone_normalized' => '111']);
        $order = $this->createOrder(OrderStatus::Waiting);
        $payload = $this->updatePayload($order);
        $payload['customer'] = [
            'name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'address' => $this->customer->address,
        ];
        $payload['delivery_type'] = 'delivery';
        $payload['delivery_address'] = 'Address used only by this order';

        $this->actingAs($this->admin)->put(route('admin.orders.update', $order), $payload)
            ->assertRedirect(route('admin.orders.show', $order));

        $this->assertSame('Address used only by this order', $order->fresh()->delivery_address_snapshot);
        $this->assertSame('Private customer address', $this->customer->fresh()->address);
    }

    public function test_concurrency_and_unavailable_historical_policy_are_enforced(): void
    {
        $order = $this->createOrder(OrderStatus::Waiting);
        $this->product->update(['is_available' => false]);
        $this->actingAs($this->admin)->get(route('admin.orders.edit', $order))->assertInertia(fn (Assert $page) => $page
            ->where('products.0.is_sellable', false)
            ->where('products.0.historical_quantity', 6));

        $payload = $this->updatePayload($order);
        $payload['notes'] = 'Safe non-economic edit';
        $this->actingAs($this->admin)->put(route('admin.orders.update', $order), $payload)->assertRedirect();
        $payload['expected_version'] = 2;
        $payload['items'][0]['quantity'] = 7;
        $this->actingAs($this->admin)->put(route('admin.orders.update', $order), $payload)->assertSessionHasErrors('items');

        $this->actingAs($this->admin)->patch(route('admin.orders.payment-status.update', $order), ['payment_status' => 'paid', 'payment_method' => 'cash'])->assertRedirect();
        $payload['items'][0]['quantity'] = 6;
        $this->actingAs($this->admin)->put(route('admin.orders.update', $order), $payload)->assertSessionHasErrors('expected_version');
        $this->assertSame(3, $order->refresh()->edit_version);
    }

    public function test_edit_reprices_toppings_both_condition_directions_and_both_delivery_directions(): void
    {
        $addon = OrderAddon::query()->create(['slug' => 'salsa-edit', 'name' => 'Salsa', 'price' => 500, 'is_available' => true, 'sort_order' => 1]);
        OrderSetting::query()->where('key', OrderSettingKey::DeliveryFee->value)->update(['integer_value' => 2500]);
        $order = $this->createOrder(OrderStatus::Waiting);

        $payload = $this->updatePayload($order);
        $payload['condition'] = 'cooked';
        $payload['addons'] = [['addon_id' => $addon->id, 'quantity' => 2]];
        $payload['delivery_type'] = 'delivery';
        $payload['delivery_address'] = 'Order delivery address';
        $this->actingAs($this->admin)->put(route('admin.orders.update', $order), $payload)->assertRedirect();
        $order->refresh();
        $this->assertSame([10000, 0, 1000, 2500, 13500], [
            (int) $order->items_cooked_subtotal, (int) $order->raw_discount,
            (int) $order->toppings_subtotal, (int) $order->shipping_fee_snapshot, (int) $order->total,
        ]);

        $payload['expected_version'] = 2;
        $payload['condition'] = 'raw';
        $payload['delivery_type'] = 'pickup';
        $payload['delivery_address'] = null;
        $this->actingAs($this->admin)->put(route('admin.orders.update', $order), $payload)->assertRedirect();
        $order->refresh();
        $this->assertSame([10000, 1000, 1000, 0, 10000], [
            (int) $order->items_cooked_subtotal, (int) $order->raw_discount,
            (int) $order->toppings_subtotal, (int) $order->shipping_fee_snapshot, (int) $order->total,
        ]);
    }

    public function test_edit_reprices_discount_and_versions_payment_method_changes(): void
    {
        $order = $this->createOrder(OrderStatus::Waiting);
        $payload = $this->updatePayload($order);
        $payload['discount_type'] = 'fixed_amount';
        $payload['discount_value'] = 2000;
        $payload['payment_status'] = 'paid';
        $payload['payment_method'] = 'bank_transfer';

        $this->actingAs($this->admin)->put(route('admin.orders.update', $order), $payload)->assertRedirect();
        $order->refresh();
        $this->assertSame(7000, (int) $order->total);
        $this->assertSame(2000, (int) $order->discount_amount);
        $this->assertSame('bank_transfer', $order->payment_method->value);
        $this->assertSame(['fields' => ['payment_status', 'payment_method', 'discount'], 'economic_change' => true], $order->revisions()->sole()->changed_fields);

        $payload['expected_version'] = 2;
        $payload['payment_method'] = 'cash';
        $this->actingAs($this->admin)->put(route('admin.orders.update', $order), $payload)->assertRedirect();
        $order->refresh();
        $this->assertSame(7000, (int) $order->total);
        $this->assertSame(3, $order->edit_version);
        $this->assertSame('cash', $order->payment_method->value);
        $this->assertFalse($order->revisions()->latest('id')->firstOrFail()->changed_fields['economic_change']);

        $this->actingAs($this->admin)->patch(route('admin.orders.payment-status.update', $order), ['payment_status' => 'unpaid'])->assertRedirect();
        $order->refresh();
        $this->assertNull($order->payment_method);
        $this->assertSame(4, $order->edit_version);
    }

    public function test_economic_edit_to_zero_total_clears_payment_without_fabricating_one(): void
    {
        $order = $this->createOrder(OrderStatus::Waiting);
        $payload = $this->updatePayload($order);
        $payload['discount_type'] = 'percentage';
        $payload['discount_value'] = 100;
        $payload['payment_status'] = 'paid';
        $payload['payment_method'] = 'cash';

        $this->actingAs($this->admin)->put(route('admin.orders.update', $order), $payload)->assertRedirect();

        $order->refresh();
        $this->assertSame(0, (int) $order->total);
        $this->assertSame('not_required', $order->payment_status->value);
        $this->assertNull($order->payment_method);
    }

    public function test_current_configuration_is_snapshotted_only_when_economic_edit_is_confirmed(): void
    {
        $edited = $this->createOrder(OrderStatus::Waiting);
        $untouched = $this->createOrder(OrderStatus::Waiting);
        $this->product->update(['half_dozen_price' => 12000]);
        RawPriceDiscount::query()->where('pricing_mode', PriceTier::HalfDozen->value)->update(['amount' => 3000]);

        $this->actingAs($this->admin)->get(route('admin.orders.show', $edited))->assertOk();
        $this->assertSame(9000, (int) $edited->fresh()->total);
        $payload = $this->updatePayload($edited);
        $payload['condition'] = 'cooked';
        $this->actingAs($this->admin)->put(route('admin.orders.update', $edited), $payload)->assertRedirect();

        $this->assertSame(12000, (int) $edited->fresh()->total);
        $this->assertSame(9000, (int) $untouched->fresh()->total);
        $this->assertSame(12000, (int) $edited->fresh()->items_cooked_subtotal);
        $this->assertSame(0, (int) $edited->fresh()->raw_discount);
    }

    public function test_failed_revision_write_rolls_back_order_items_and_totals(): void
    {
        $order = $this->createOrder(OrderStatus::Waiting);
        $data = new OrderCheckoutData(
            items: [['product_id' => $this->product->id, 'quantity' => 7]],
            addons: [],
            condition: OrderCondition::Raw,
            deliveryType: DeliveryType::Pickup,
            deliveryPeriod: DeliveryPeriod::Night,
            deliveryDate: now()->toDateString(),
            deliveryTime: null,
            deliveryAddress: null,
            notes: 'Changed note',
            customerId: $this->customer->id,
            customerName: $this->customer->name,
            customerPhone: $this->customer->phone,
            customerPhoneNormalized: $this->customer->phone_normalized,
            customerAddress: $this->customer->address,
            paymentStatus: PaymentStatus::Unpaid,
        );
        try {
            // A missing admin violates the revision FK after order lines/totals are replaced.
            app(UpdateOrder::class)->execute($order, $data, OrderStatus::Waiting, 1, PHP_INT_MAX, null);
            $this->fail('The revision write should have failed.');
        } catch (QueryException) {
            // Expected; the assertions below prove that the entire edit rolled back.
        }

        $order->refresh()->load('items');
        $this->assertSame(9000, (int) $order->total);
        $this->assertSame(1, $order->edit_version);
        $this->assertSame(6, (int) $order->items->sum('units_count'));
        $this->assertDatabaseCount('order_revisions', 0);
    }

    private function createOrder(OrderStatus $status): Order
    {
        $payload = $this->updatePayload();
        unset($payload['status'], $payload['expected_version']);
        $this->actingAs($this->admin)->post(route('admin.orders.store'), $payload)->assertRedirect();
        $order = Order::query()->latest('id')->firstOrFail();
        $order->update(['status' => $status]);

        return $order->refresh();
    }

    /** @return array<string, mixed> */
    private function updatePayload(?Order $order = null): array
    {
        return [
            'items' => [['product_id' => $this->product->id, 'quantity' => 6]], 'addons' => [],
            'condition' => 'raw', 'delivery_type' => 'pickup', 'delivery_period' => 'night',
            'delivery_date' => now()->toDateString(), 'delivery_time' => null, 'delivery_address' => null,
            'notes' => 'Original note', 'customer_id' => $this->customer->id, 'payment_status' => 'unpaid',
            'status' => $order === null ? 'waiting' : $order->status->value,
            'expected_version' => $order === null ? 1 : $order->edit_version,
        ];
    }
}
