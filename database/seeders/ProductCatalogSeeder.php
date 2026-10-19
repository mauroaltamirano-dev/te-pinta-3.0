<?php

namespace Database\Seeders;

use App\Modules\Products\Actions\RecalculateEmpanadaUnitPrices;
use App\Modules\Products\Enums\ProductPricingFamily;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\RawPriceDiscount;
use App\Modules\Products\Models\Tag;
use App\Modules\Products\Pricing\EmpanadaUnitPrice;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ProductCatalogSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            // Bootstrap only: an administered or soft-deleted catalog must never
            // receive starter varieties again after a rename or slug edit.
            $hasCatalog = Product::query()->withTrashed()->exists();

            if ($hasCatalog) {
                return;
            }

            $categories = collect([
                ['name' => 'Clásicas', 'slug' => 'clasicas', 'sort_order' => 10],
                ['name' => 'Especiales', 'slug' => 'especiales', 'sort_order' => 20],
            ])->mapWithKeys(function (array $attributes): array {
                $category = Category::query()->withTrashed()->where('slug', $attributes['slug'])->first();

                if ($category?->trashed()) {
                    return [];
                }

                $category ??= Category::query()->create([...$attributes, 'is_active' => true]);

                return [$attributes['slug'] => $category];
            });

            $vegetarian = Tag::query()->firstOrCreate(
                ['slug' => 'vegetariana'],
                ['name' => 'Vegetariana'],
            );

            $products = [
                ['category' => 'especiales', 'name' => 'Salteña', 'dozen' => 18000],
                ['category' => 'especiales', 'name' => 'Humita Tucumana', 'dozen' => 17000, 'vegetarian' => true],
                ['category' => 'especiales', 'name' => 'Hongos al puerro', 'dozen' => 18000, 'vegetarian' => true],
                ['category' => 'especiales', 'name' => 'Pollo y portobellos', 'dozen' => 18000],
                ['category' => 'especiales', 'name' => 'Matambre y verduras asadas', 'dozen' => 19000],
                ['category' => 'clasicas', 'name' => 'Criolla Salada', 'dozen' => 18000],
                ['category' => 'clasicas', 'name' => 'Criolla Dulce', 'dozen' => 18000],
                ['category' => 'clasicas', 'name' => 'Jamón y Queso', 'dozen' => 16000],
                ['category' => 'clasicas', 'name' => 'Caprese', 'dozen' => 16000, 'vegetarian' => true],
            ];

            $sortOrders = [];
            $unitPrices = app(RecalculateEmpanadaUnitPrices::class);

            foreach ($products as $attributes) {
                $categorySlug = $attributes['category'];
                $sortOrders[$categorySlug] = ($sortOrders[$categorySlug] ?? 0) + 10;

                $category = $categories->get($categorySlug);

                if (! $category instanceof Category) {
                    continue;
                }

                $slug = Str::slug($attributes['name']);

                if (Product::query()->withTrashed()->where('slug', $slug)->exists()) {
                    continue;
                }

                $product = Product::query()->create([
                    'category_id' => $category->id,
                    'pricing_family' => ProductPricingFamily::Empanada,
                    'name' => $attributes['name'],
                    'slug' => $slug,
                    'description' => null,
                    'image_path' => null,
                    'sort_order' => $sortOrders[$categorySlug],
                    'unit_price' => $unitPrice = $unitPrices->forDozen($attributes['dozen']),
                    'half_dozen_price' => EmpanadaUnitPrice::halfDozen($unitPrice),
                    'dozen_price' => $attributes['dozen'],
                    'is_available' => true,
                ]);

                if ($attributes['vegetarian'] ?? false) {
                    $product->tags()->attach($vegetarian);
                }
            }

            foreach (['unit' => 200, 'half_dozen' => 1000, 'dozen' => 2000] as $mode => $amount) {
                RawPriceDiscount::query()->firstOrCreate(
                    ['pricing_mode' => $mode],
                    ['amount' => $amount],
                );
            }
        });
    }
}
