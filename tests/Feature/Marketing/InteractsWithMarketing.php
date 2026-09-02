<?php

namespace Tests\Feature\Marketing;

use App\Modules\Marketing\Models\FeaturedProduct;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use Carbon\CarbonImmutable;

trait InteractsWithMarketing
{
    /** @param array<string, mixed> $overrides */
    private function category(array $overrides = []): Category
    {
        return Category::query()->create([
            'name' => 'Clásicas',
            'slug' => 'clasicas-'.uniqid(),
            'sort_order' => 1,
            'is_active' => true,
            ...$overrides,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function product(?Category $category = null, array $overrides = []): Product
    {
        return Product::query()->create([
            'category_id' => ($category ?? $this->category())->id,
            'name' => 'Salteña',
            'slug' => 'saltena-'.uniqid(),
            'description' => 'Carne cortada a cuchillo.',
            'image_path' => 'products/saltena.jpg',
            'sort_order' => 1,
            'unit_price' => 1600,
            'half_dozen_price' => 9000,
            'dozen_price' => 16000,
            'is_available' => true,
            ...$overrides,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function featured(Product $product, array $overrides = []): FeaturedProduct
    {
        return FeaturedProduct::query()->create([
            'product_id' => $product->id,
            'headline' => 'Nuestra recomendación de hoy',
            'short_copy' => 'Un clásico del norte.',
            'starts_at' => CarbonImmutable::parse('2026-08-23 14:00:00 UTC'),
            'ends_at' => CarbonImmutable::parse('2026-08-23 20:00:00 UTC'),
            'is_enabled' => true,
            ...$overrides,
        ]);
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(Product $product, array $overrides = []): array
    {
        return [
            'product_id' => $product->id,
            'headline' => 'Nuestra recomendación de hoy',
            'short_copy' => 'Un clásico del norte.',
            'starts_at' => '2026-08-23T11:00',
            'ends_at' => '2026-08-23T17:00',
            'is_enabled' => true,
            ...$overrides,
        ];
    }
}
