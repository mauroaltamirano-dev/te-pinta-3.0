<?php

namespace Database\Seeders;

use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\RawPriceDiscount;
use App\Modules\Products\Models\Tag;
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
                ['category' => 'especiales', 'name' => 'Salteña', 'unit' => 1800, 'half' => 10000, 'dozen' => 18000],
                ['category' => 'especiales', 'name' => 'Humita Tucumana', 'unit' => 1700, 'half' => 9500, 'dozen' => 17000, 'vegetarian' => true],
                ['category' => 'especiales', 'name' => 'Hongos al puerro', 'unit' => 1800, 'half' => 10000, 'dozen' => 18000, 'vegetarian' => true],
                ['category' => 'especiales', 'name' => 'Pollo y portobellos', 'unit' => 1800, 'half' => 10000, 'dozen' => 18000],
                ['category' => 'especiales', 'name' => 'Matambre y verduras asadas', 'unit' => 1900, 'half' => 10500, 'dozen' => 19000],
                ['category' => 'clasicas', 'name' => 'Criolla Salada', 'unit' => 1800, 'half' => 10000, 'dozen' => 18000],
                ['category' => 'clasicas', 'name' => 'Criolla Dulce', 'unit' => 1800, 'half' => 10000, 'dozen' => 18000],
                ['category' => 'clasicas', 'name' => 'Jamón y Queso', 'unit' => 1600, 'half' => 9000, 'dozen' => 16000],
                ['category' => 'clasicas', 'name' => 'Caprese', 'unit' => 1600, 'half' => 9000, 'dozen' => 16000, 'vegetarian' => true],
            ];

            $sortOrders = [];

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
                    'name' => $attributes['name'],
                    'slug' => $slug,
                    'description' => null,
                    'image_path' => null,
                    'sort_order' => $sortOrders[$categorySlug],
                    'unit_price' => $attributes['unit'],
                    'half_dozen_price' => $attributes['half'],
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
