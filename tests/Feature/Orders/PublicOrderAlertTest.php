<?php

namespace Tests\Feature\Orders;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\OrderSettingKey;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderSetting;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\OpensPublicOrdering;
use Tests\TestCase;

final class PublicOrderAlertTest extends TestCase
{
    use OpensPublicOrdering;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $category = Category::query()->create(['name' => 'Empanadas', 'slug' => 'empanadas', 'sort_order' => 1, 'is_active' => true]);
        Product::query()->create([
            'category_id' => $category->id, 'name' => 'Salteña', 'slug' => 'saltena',
            'unit_price' => 1800, 'half_dozen_price' => 10000, 'dozen_price' => 18000,
            'is_available' => true, 'sort_order' => 1,
        ]);
        OrderSetting::query()->where('key', OrderSettingKey::WhatsappOrderPhone->value)->update(['string_value' => '5491112345678']);
        $this->openPublicOrderingAllDay();
    }

    public function test_staff_see_the_latest_web_order_waiting_for_confirmation(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin)->getJson(route('admin.orders.web-pending'))->assertOk()
            ->assertExactJson(['data' => ['pending_count' => 0, 'latest' => null]]);

        $first = $this->placeWebOrder('Primera visita', 'alert-key-000000001');
        $second = $this->placeWebOrder('Segunda visita', 'alert-key-000000002');

        $this->actingAs($admin)->getJson(route('admin.orders.web-pending'))->assertOk()
            ->assertExactJson(['data' => [
                'pending_count' => 2,
                'latest' => [
                    'id' => $second->id,
                    'public_id' => $second->public_id,
                    'customer_name' => 'Segunda visita',
                    'url' => route('admin.orders.show', $second),
                ],
            ]]);

        $second->delete();
        $first->update(['status' => 'waiting']);
        $this->actingAs($admin)->getJson(route('admin.orders.web-pending'))
            ->assertExactJson(['data' => ['pending_count' => 0, 'latest' => null]]);
    }

    public function test_only_staff_who_can_view_orders_are_alerted(): void
    {
        $this->getJson(route('admin.orders.web-pending'))->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson(route('admin.orders.web-pending'))->assertForbidden();
    }

    private function placeWebOrder(string $name, string $key): Order
    {
        $this->postJson(route('public.orders.store'), [
            'items' => [['product_slug' => 'saltena', 'quantity' => 6]], 'addons' => [], 'condition' => 'raw',
            'delivery_type' => 'pickup', 'delivery_address' => null, 'notes' => null,
            'customer' => ['name' => $name, 'phone' => '+54 9 11 1234-'.substr($key, -4), 'address' => null],
            'idempotency_key' => $key,
        ])->assertCreated();

        return Order::query()->latest('id')->firstOrFail();
    }
}
