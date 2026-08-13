<?php

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Enums\DeliveryType;
use App\Modules\Orders\Enums\OrderCondition;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Orders\Models\OrderItemComposition;
use App\Modules\Products\Models\Product;
use Illuminate\Support\Facades\Storage;

final class BuildOrderDashboardData
{
    /**
     * @return array{
     *     mostOrderedProducts: array<int, array{product_id: int, name: string, units_count: int, image_url: string|null}>,
     *     deliveryTypeDistribution: array<int, array{type: string, orders_count: int}>,
     *     conditionDistribution: array<int, array{condition: string, orders_count: int}>
     * }
     */
    public function execute(): array
    {
        /** @var array<int, array{name: string, units_count: int}> $products */
        $products = [];

        $directItems = OrderItem::query()
            ->selectRaw('order_items.product_id, order_items.product_name, SUM(order_items.units_count) AS units_count')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNull('orders.deleted_at')
            ->whereNotNull('order_items.product_id')
            ->groupBy('order_items.product_id', 'order_items.product_name')
            ->get();

        foreach ($directItems as $item) {
            $productId = (int) $item->product_id;
            $products[$productId] ??= ['name' => (string) $item->product_name, 'units_count' => 0];
            $products[$productId]['units_count'] += (int) $item->units_count;
        }

        $combinedItems = OrderItemComposition::query()
            ->selectRaw('order_item_compositions.product_id, order_item_compositions.product_name, SUM(order_item_compositions.quantity) AS quantity')
            ->join('order_items', 'order_items.id', '=', 'order_item_compositions.order_item_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNull('orders.deleted_at')
            ->groupBy('order_item_compositions.product_id', 'order_item_compositions.product_name')
            ->get();

        foreach ($combinedItems as $item) {
            $productId = (int) $item->product_id;
            $products[$productId] ??= ['name' => (string) $item->product_name, 'units_count' => 0];
            $products[$productId]['units_count'] += (int) $item->quantity;
        }

        $catalogProducts = Product::withTrashed()
            ->whereKey(array_keys($products))
            ->get(['id', 'name', 'image_path'])
            ->keyBy('id');

        $mostOrderedProducts = collect($products)
            ->map(function (array $aggregate, int $productId) use ($catalogProducts): array {
                $product = $catalogProducts->get($productId);

                return [
                    'product_id' => $productId,
                    'name' => $product->name,
                    'units_count' => $aggregate['units_count'],
                    'image_url' => $product?->image_path
                        ? Storage::disk('public')->url($product->image_path)
                        : null,
                ];
            })
            ->sortBy([
                ['units_count', 'desc'],
                ['name', 'asc'],
            ])
            ->take(3)
            ->values()
            ->all();

        $deliveryCounts = Order::query()
            ->selectRaw('delivery_type, COUNT(*) AS orders_count')
            ->groupBy('delivery_type')
            ->pluck('orders_count', 'delivery_type');

        $conditionCounts = Order::query()
            ->selectRaw('condition, COUNT(*) AS orders_count')
            ->groupBy('condition')
            ->pluck('orders_count', 'condition');

        return [
            'mostOrderedProducts' => $mostOrderedProducts,
            'deliveryTypeDistribution' => array_map(
                fn (DeliveryType $type): array => [
                    'type' => $type->value,
                    'orders_count' => (int) ($deliveryCounts[$type->value] ?? 0),
                ],
                DeliveryType::cases(),
            ),
            'conditionDistribution' => array_map(
                fn (OrderCondition $condition): array => [
                    'condition' => $condition->value,
                    'orders_count' => (int) ($conditionCounts[$condition->value] ?? 0),
                ],
                OrderCondition::cases(),
            ),
        ];
    }
}
