<?php

namespace App\Modules\Orders\Actions;

use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderAddon;
use App\Modules\Orders\Support\OrderBusinessDate;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;

final class BuildAdminOrderFormOptions
{
    public function __construct(private readonly OrderBusinessDate $businessDate) {}

    /** @return array{products: array<int, array<string, mixed>>, customers: mixed, addons: mixed, today: string} */
    public function execute(?Order $historicalOrder = null): array
    {
        return [
            ...$this->catalog($historicalOrder),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name', 'phone', 'address']),
            'today' => $this->businessDate->todayString(),
        ];
    }

    /**
     * Sellable products and add-ons, plus the retired ones a historical order still holds.
     *
     * @return array{products: array<int, array<string, mixed>>, addons: array<int, array<string, mixed>>}
     */
    public function catalog(?Order $historicalOrder = null): array
    {
        $quantities = $historicalOrder === null ? [] : $this->itemQuantities($historicalOrder);
        $addonQuantities = $historicalOrder === null ? [] : $this->addonQuantities($historicalOrder);
        $historicalProductIds = array_keys($quantities);
        $historicalAddonIds = array_keys($addonQuantities);

        $products = Product::query()->withTrashed()
            ->where(function ($query) use ($historicalProductIds): void {
                $query->whereIn('id', $historicalProductIds)
                    ->orWhere(function ($query): void {
                        $query->where('is_available', true)->whereNull('deleted_at');
                    });
            })
            ->with('subcategory')
            ->orderBy('sort_order')->orderBy('id')->get();
        $categories = Category::query()->withTrashed()->whereIn('id', $products->pluck('category_id')->unique())->get()->keyBy('id');

        $products = $products
            ->map(function (Product $product) use ($quantities, $categories): array {
                $category = $categories->get($product->category_id) ?? throw new ModelNotFoundException;
                $sellable = $product->deleted_at === null && $product->is_available && $category->deleted_at === null && $category->is_active;

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'pricing_family' => $product->pricing_family->value,
                    'image_url' => $product->image_path ? Storage::disk('public')->url($product->image_path) : null,
                    'category' => ['id' => $category->id, 'name' => $category->name],
                    // A filter only, like on the public menu: an inactive subcategory is not offered.
                    'subcategory' => $product->subcategory?->is_active ? [
                        'id' => $product->subcategory->id,
                        'name' => $product->subcategory->name,
                        'sort_order' => $product->subcategory->sort_order,
                    ] : null,
                    'unit_price' => $product->unit_price,
                    'half_dozen_price' => $product->half_dozen_price,
                    'dozen_price' => $product->dozen_price,
                    'is_sellable' => $sellable,
                    'historical_quantity' => $quantities[$product->id] ?? null,
                ];
            })->filter(fn (array $product): bool => $product['is_sellable'] || $product['historical_quantity'] !== null)->values()->all();

        $addons = OrderAddon::query()->withTrashed()
            ->where(fn ($query) => $query->where('is_available', true)->whereNull('deleted_at')->orWhereIn('id', $historicalAddonIds))
            ->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (OrderAddon $addon): array => [
                'id' => $addon->id,
                'name' => $addon->name,
                'price' => $addon->price,
                'is_sellable' => $addon->deleted_at === null && $addon->is_available,
                'historical_quantity' => $addonQuantities[$addon->id] ?? null,
            ])->all();

        return ['products' => $products, 'addons' => $addons];
    }

    /** @return array<int, int> */
    public function itemQuantities(Order $order): array
    {
        $order->loadMissing('items.compositions');
        $quantities = [];
        foreach ($order->items as $item) {
            if ($item->product_id !== null) {
                $quantities[$item->product_id] = ($quantities[$item->product_id] ?? 0) + $item->units_count;
            }
            foreach ($item->compositions as $part) {
                $quantities[$part->product_id] = ($quantities[$part->product_id] ?? 0) + $part->quantity;
            }
        }
        ksort($quantities);

        return $quantities;
    }

    /** @return array<int, int> */
    public function addonQuantities(Order $order): array
    {
        $order->loadMissing('toppings');
        $quantities = [];
        foreach ($order->toppings as $topping) {
            if ($topping->order_addon_id !== null) {
                $quantities[$topping->order_addon_id] = ($quantities[$topping->order_addon_id] ?? 0) + $topping->quantity;
            }
        }
        ksort($quantities);

        return $quantities;
    }
}
