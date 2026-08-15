<?php

namespace Tests\Feature\Orders;

use App\Modules\Identity\Models\User;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class AdminQuickOrderHomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_home_requires_an_administrator(): void
    {
        $this->get(route('admin.home'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())
            ->get(route('admin.home'))
            ->assertForbidden();
    }

    public function test_admin_home_renders_quick_order_with_only_sellable_products_from_active_categories(): void
    {
        $activeCategory = $this->category('Activas', 'activas', true, 1);
        $inactiveCategory = $this->category('Inactivas', 'inactivas', false, 2);
        $deletedCategory = $this->category('Eliminadas', 'eliminadas', true, 3);
        $deletedCategory->delete();

        $sellable = $this->product($activeCategory, 'Vendible', 'vendible', true, 1);
        $this->product($activeCategory, 'No disponible', 'no-disponible', false, 2);
        $deletedProduct = $this->product($activeCategory, 'Eliminado', 'eliminado', true, 3);
        $deletedProduct->delete();
        $this->product($inactiveCategory, 'Categoría inactiva', 'categoria-inactiva', true, 4);
        $this->product($deletedCategory, 'Categoría eliminada', 'categoria-eliminada', true, 5);

        $response = $this->actingAs(User::factory()->administrator()->create())
            ->get(route('admin.home'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/home')
                ->has('products', 1)
                ->where('products.0.id', $sellable->id)
                ->where('products.0.name', 'Vendible')
                ->where('products.0.category.name', 'Activas')
                ->has('customers')
                ->has('addons')
                ->has('today'));
    }

    public function test_admin_home_uses_the_orders_business_date_instead_of_utc(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-14 00:30:00 UTC'));

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('admin.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('today', '2026-08-13'));
    }

    private function category(string $name, string $slug, bool $active, int $sortOrder): Category
    {
        return Category::query()->create([
            'name' => $name,
            'slug' => $slug,
            'sort_order' => $sortOrder,
            'is_active' => $active,
        ]);
    }

    private function product(
        Category $category,
        string $name,
        string $slug,
        bool $available,
        int $sortOrder,
    ): Product {
        return Product::query()->create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => $slug,
            'unit_price' => 1600,
            'half_dozen_price' => 9000,
            'dozen_price' => 16000,
            'is_available' => $available,
            'sort_order' => $sortOrder,
        ]);
    }
}
