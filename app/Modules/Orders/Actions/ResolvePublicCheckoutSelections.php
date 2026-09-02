<?php

namespace App\Modules\Orders\Actions;

use App\Core\Contracts\ProductPriceProvider;
use App\Modules\Orders\Enums\OrderCondition;
use App\Modules\Orders\Models\OrderAddon;
use Illuminate\Validation\ValidationException;

final readonly class ResolvePublicCheckoutSelections
{
    public function __construct(private ProductPriceProvider $products) {}

    /**
     * @param  array<int, array{product_slug: string, quantity: int}>  $items
     * @param  array<int, array{addon_slug: string, quantity: int}>  $addons
     * @return array{items: array<int, array{product_id: int, quantity: int}>, addons: array<int, array{addon_id: int, quantity: int}>}
     */
    public function execute(array $items, array $addons, OrderCondition $condition): array
    {
        $productSlugs = array_column($items, 'product_slug');
        $products = $this->products->productsForSaleBySlugs($productSlugs, $condition === OrderCondition::Cooked);
        if (count($products) !== count($productSlugs)) {
            throw ValidationException::withMessages(['items' => 'Todos los productos deben estar disponibles.']);
        }

        $addonSlugs = array_column($addons, 'addon_slug');
        $availableAddons = OrderAddon::query()->whereIn('slug', $addonSlugs)->where('is_available', true)->get()->keyBy('slug');
        if ($availableAddons->count() !== count($addonSlugs)) {
            throw ValidationException::withMessages(['addons' => 'Todos los adicionales deben estar disponibles.']);
        }

        return [
            'items' => array_map(fn (array $item): array => [
                'product_id' => $products[$item['product_slug']]->id,
                'quantity' => $item['quantity'],
            ], $items),
            'addons' => array_map(fn (array $addon): array => [
                'addon_id' => $availableAddons[$addon['addon_slug']]->id,
                'quantity' => $addon['quantity'],
            ], $addons),
        ];
    }
}
