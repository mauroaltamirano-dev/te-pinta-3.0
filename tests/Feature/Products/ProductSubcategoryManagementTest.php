<?php

namespace Tests\Feature\Products;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductSubcategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ProductSubcategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_catalog_manager_creates_subcategories_with_the_same_name_under_different_categories(): void
    {
        $admin = User::factory()->administrator()->create();
        $empanadas = $this->category('Empanadas', 1);
        $pizzas = $this->category('Pizzas', 2);

        $this->actingAs($admin)
            ->post(route('admin.product-subcategories.store', $empanadas), ['name' => 'Especiales', 'sort_order' => 2, 'is_active' => true])
            ->assertRedirect(route('admin.products.index'))
            ->assertSessionHas('success', 'Subcategoría creada.');
        $this->actingAs($admin)
            ->post(route('admin.product-subcategories.store', $pizzas), ['name' => 'Especiales', 'sort_order' => 1, 'is_active' => true])
            ->assertRedirect(route('admin.products.index'));

        $this->assertDatabaseHas('product_subcategories', ['category_id' => $empanadas->id, 'name' => 'Especiales', 'slug' => 'especiales', 'sort_order' => 2, 'is_active' => true]);
        $this->assertDatabaseHas('product_subcategories', ['category_id' => $pizzas->id, 'name' => 'Especiales', 'slug' => 'especiales', 'sort_order' => 1, 'is_active' => true]);
    }

    public function test_a_subcategory_name_is_unique_within_its_category(): void
    {
        $admin = User::factory()->administrator()->create();
        $empanadas = $this->category('Empanadas', 1);
        $this->subcategory($empanadas, 'Clásicas');

        $this->actingAs($admin)
            ->from(route('admin.products.index'))
            ->post(route('admin.product-subcategories.store', $empanadas), ['name' => 'clásicas', 'sort_order' => 1, 'is_active' => true])
            ->assertSessionHasErrors(['name' => 'Ya existe una subcategoría con ese nombre en esta categoría.']);

        $this->assertSame(1, ProductSubcategory::query()->count());
    }

    public function test_subcategory_input_is_validated(): void
    {
        $admin = User::factory()->administrator()->create();
        $empanadas = $this->category('Empanadas', 1);

        $this->actingAs($admin)
            ->from(route('admin.products.index'))
            ->post(route('admin.product-subcategories.store', $empanadas), ['name' => '', 'sort_order' => -1, 'is_active' => 'maybe'])
            ->assertSessionHasErrors(['name', 'sort_order', 'is_active']);

        $this->assertSame(0, ProductSubcategory::query()->count());
    }

    public function test_a_catalog_manager_renames_reorders_and_deactivates_a_subcategory_without_touching_its_products(): void
    {
        $admin = User::factory()->administrator()->create();
        $empanadas = $this->category('Empanadas', 1);
        $classics = $this->subcategory($empanadas, 'Clasicas');
        $product = $this->product($empanadas, 'Carne', $classics);

        $this->actingAs($admin)
            ->put(route('admin.product-subcategories.update', $classics), ['name' => 'Clásicas', 'sort_order' => 5, 'is_active' => false])
            ->assertRedirect(route('admin.products.index'))
            ->assertSessionHas('success', 'Subcategoría actualizada.');

        $classics->refresh();
        $this->assertSame('Clásicas', $classics->name);
        $this->assertSame('clasicas', $classics->slug);
        $this->assertSame(5, $classics->sort_order);
        $this->assertFalse($classics->is_active);
        $this->assertSame($classics->id, $product->refresh()->subcategory_id);
        $this->assertTrue($product->is_available);
    }

    public function test_a_subcategory_never_moves_to_another_category(): void
    {
        $admin = User::factory()->administrator()->create();
        $empanadas = $this->category('Empanadas', 1);
        $pizzas = $this->category('Pizzas', 2);
        $classics = $this->subcategory($empanadas, 'Clásicas');

        $this->actingAs($admin)
            ->put(route('admin.product-subcategories.update', $classics), ['name' => 'Clásicas', 'sort_order' => 1, 'is_active' => true, 'category_id' => $pizzas->id])
            ->assertRedirect(route('admin.products.index'));

        $this->assertSame($empanadas->id, $classics->refresh()->category_id);
    }

    public function test_only_catalog_managers_manage_subcategories(): void
    {
        $empanadas = $this->category('Empanadas', 1);
        $classics = $this->subcategory($empanadas, 'Clásicas');
        $payload = ['name' => 'Especiales', 'sort_order' => 1, 'is_active' => true];

        $this->post(route('admin.product-subcategories.store', $empanadas), $payload)->assertRedirect(route('login'));

        $cashier = User::factory()->withRole(Role::Cashier)->create();
        $this->actingAs($cashier)->post(route('admin.product-subcategories.store', $empanadas), $payload)->assertForbidden();
        $this->actingAs($cashier)->put(route('admin.product-subcategories.update', $classics), $payload)->assertForbidden();

        $this->assertSame(1, ProductSubcategory::query()->count());
        $this->assertSame('Clásicas', $classics->refresh()->name);
    }

    public function test_the_products_screen_lists_each_category_with_its_ordered_subcategories(): void
    {
        $admin = User::factory()->administrator()->create();
        $empanadas = $this->category('Empanadas', 1);
        $this->category('Postres', 2);
        $this->subcategory($empanadas, 'Especiales', 2);
        $this->subcategory($empanadas, 'Clásicas', 1, false);
        $product = $this->product($empanadas, 'Carne');

        $this->actingAs($admin)
            ->get(route('admin.products.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/products/index')
                ->where('categories.0.name', 'Empanadas')
                ->where('categories.0.subcategories.0.name', 'Clásicas')
                ->where('categories.0.subcategories.0.is_active', false)
                ->where('categories.0.subcategories.1.name', 'Especiales')
                ->where('categories.1.subcategories', [])
                ->where('products.0.id', $product->id)
                ->where('products.0.subcategory_id', null));
    }

    private function category(string $name, int $order): Category
    {
        return Category::query()->create(['name' => $name, 'slug' => str($name)->slug()->toString(), 'sort_order' => $order, 'is_active' => true]);
    }

    private function subcategory(Category $category, string $name, int $order = 1, bool $active = true): ProductSubcategory
    {
        return ProductSubcategory::query()->create(['category_id' => $category->id, 'name' => $name, 'slug' => str($name)->slug()->toString(), 'sort_order' => $order, 'is_active' => $active]);
    }

    private function product(Category $category, string $name, ?ProductSubcategory $subcategory = null): Product
    {
        return Product::query()->create([
            'category_id' => $category->id,
            'subcategory_id' => $subcategory?->id,
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'unit_price' => 2_000,
            'half_dozen_price' => 12_000,
            'dozen_price' => 20_000,
            'is_available' => true,
            'sort_order' => 1,
        ]);
    }
}
