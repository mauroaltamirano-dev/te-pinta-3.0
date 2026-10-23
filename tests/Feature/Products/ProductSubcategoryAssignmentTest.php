<?php

namespace Tests\Feature\Products;

use App\Modules\Identity\Models\User;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductSubcategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProductSubcategoryAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private Category $empanadas;

    private Category $pizzas;

    private ProductSubcategory $classics;

    private ProductSubcategory $pizzaSpecials;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empanadas = Category::query()->create(['name' => 'Empanadas', 'slug' => 'empanadas', 'sort_order' => 1, 'is_active' => true]);
        $this->pizzas = Category::query()->create(['name' => 'Pizzas', 'slug' => 'pizzas', 'sort_order' => 2, 'is_active' => true]);
        $this->classics = ProductSubcategory::query()->create(['category_id' => $this->empanadas->id, 'name' => 'Clásicas', 'slug' => 'clasicas', 'sort_order' => 1, 'is_active' => true]);
        $this->pizzaSpecials = ProductSubcategory::query()->create(['category_id' => $this->pizzas->id, 'name' => 'Especiales', 'slug' => 'especiales', 'sort_order' => 1, 'is_active' => true]);
    }

    public function test_a_product_is_created_with_an_optional_subcategory_of_its_category(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)
            ->post(route('admin.products.store'), $this->payload(['name' => 'Carne', 'subcategory_id' => $this->classics->id]))
            ->assertRedirect(route('admin.products.index'));
        $this->actingAs($admin)
            ->post(route('admin.products.store'), $this->payload(['name' => 'Humita']))
            ->assertRedirect(route('admin.products.index'));

        $this->assertSame($this->classics->id, Product::query()->where('name', 'Carne')->value('subcategory_id'));
        $this->assertNull(Product::query()->where('name', 'Humita')->value('subcategory_id'));
    }

    public function test_a_subcategory_of_another_category_is_rejected_with_a_clear_message(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)
            ->from(route('admin.products.index'))
            ->post(route('admin.products.store'), $this->payload(['name' => 'Carne', 'subcategory_id' => $this->pizzaSpecials->id]))
            ->assertSessionHasErrors(['subcategory_id' => 'La subcategoría elegida no pertenece a la categoría del producto.']);

        $this->assertSame(0, Product::query()->count());
    }

    public function test_an_inactive_subcategory_cannot_be_newly_assigned_but_a_product_keeps_the_one_it_has(): void
    {
        $admin = User::factory()->administrator()->create();
        $product = $this->product($this->classics);
        $retired = ProductSubcategory::query()->create(['category_id' => $this->empanadas->id, 'name' => 'Antiguas', 'slug' => 'antiguas', 'sort_order' => 2, 'is_active' => false]);
        $this->classics->update(['is_active' => false]);

        $this->actingAs($admin)
            ->put(route('admin.products.update', $product), $this->payload(['name' => 'Carne picante', 'subcategory_id' => $this->classics->id]))
            ->assertRedirect(route('admin.products.index'));
        $this->assertSame('Carne picante', $product->refresh()->name);
        $this->assertSame($this->classics->id, $product->subcategory_id);

        $this->actingAs($admin)
            ->from(route('admin.products.index'))
            ->put(route('admin.products.update', $product), $this->payload(['subcategory_id' => $retired->id]))
            ->assertSessionHasErrors(['subcategory_id' => 'La subcategoría elegida está inactiva.']);
        $this->assertSame($this->classics->id, $product->refresh()->subcategory_id);
    }

    public function test_moving_a_product_to_another_category_requires_a_subcategory_of_that_category_or_none(): void
    {
        $admin = User::factory()->administrator()->create();
        $product = $this->product($this->classics);

        $this->actingAs($admin)
            ->from(route('admin.products.index'))
            ->put(route('admin.products.update', $product), $this->payload(['category_id' => $this->pizzas->id, 'subcategory_id' => $this->classics->id]))
            ->assertSessionHasErrors('subcategory_id');
        $this->assertSame($this->empanadas->id, $product->refresh()->category_id);

        $this->actingAs($admin)
            ->put(route('admin.products.update', $product), $this->payload(['category_id' => $this->pizzas->id, 'subcategory_id' => null]))
            ->assertRedirect(route('admin.products.index'));
        $product->refresh();
        $this->assertSame($this->pizzas->id, $product->category_id);
        $this->assertNull($product->subcategory_id);
    }

    public function test_updating_a_product_without_sending_a_subcategory_clears_it(): void
    {
        $admin = User::factory()->administrator()->create();
        $product = $this->product($this->classics);

        $this->actingAs($admin)
            ->put(route('admin.products.update', $product), $this->payload())
            ->assertRedirect(route('admin.products.index'));

        $this->assertNull($product->refresh()->subcategory_id);
    }

    public function test_the_subcategory_does_not_change_the_stored_prices(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->post(route('admin.products.store'), $this->payload(['name' => 'Con subcategoría', 'subcategory_id' => $this->classics->id]));
        $this->actingAs($admin)->post(route('admin.products.store'), $this->payload(['name' => 'Sin subcategoría']));

        $with = Product::query()->where('name', 'Con subcategoría')->firstOrFail();
        $without = Product::query()->where('name', 'Sin subcategoría')->firstOrFail();
        $this->assertSame(
            [$without->unit_price, $without->half_dozen_price, $without->dozen_price],
            [$with->unit_price, $with->half_dozen_price, $with->dozen_price],
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Carne',
            'description' => null,
            'category_id' => $this->empanadas->id,
            'tags' => [],
            'pricing_family' => 'empanada',
            'dozen_price' => 18000,
            'is_available' => true,
            'sort_order' => 1,
        ], $overrides);
    }

    private function product(ProductSubcategory $subcategory): Product
    {
        return Product::query()->create([
            'category_id' => $subcategory->category_id,
            'subcategory_id' => $subcategory->id,
            'name' => 'Carne',
            'slug' => 'carne',
            'unit_price' => 1_700,
            'half_dozen_price' => 10_200,
            'dozen_price' => 18_000,
            'is_available' => true,
            'sort_order' => 1,
        ]);
    }
}
