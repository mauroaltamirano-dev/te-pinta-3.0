<?php

namespace Tests\Feature\Products;

use App\Modules\Identity\Models\User;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_create_edit_or_delete_products(): void
    {
        $category = $this->createCategory();
        $product = $this->createProduct($category);

        $this->post(route('admin.products.store'), $this->validPayload($category))
            ->assertRedirect(route('login'));
        $this->put(route('admin.products.update', $product), $this->validPayload($category))
            ->assertRedirect(route('login'));
        $this->delete(route('admin.products.destroy', $product))
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('products', 1);
        $this->assertNull($product->fresh()?->deleted_at);
    }

    public function test_authenticated_non_administrator_cannot_manage_products(): void
    {
        $user = User::factory()->create();
        $category = $this->createCategory();
        $product = $this->createProduct($category);

        $this->actingAs($user)->get(route('admin.products.index'))->assertForbidden();
        $this->actingAs($user)
            ->post(route('admin.products.store'), $this->validPayload($category))
            ->assertForbidden();
        $this->actingAs($user)
            ->put(route('admin.products.update', $product), $this->validPayload($category))
            ->assertForbidden();
        $this->actingAs($user)->delete(route('admin.products.destroy', $product))->assertForbidden();

        $this->assertNull($product->fresh()?->deleted_at);
    }

    public function test_administrator_can_create_update_and_soft_delete_a_product(): void
    {
        $admin = User::factory()->administrator()->create();
        $classic = $this->createCategory();
        $special = $this->createCategory([
            'name' => 'Especiales',
            'slug' => 'especiales',
            'sort_order' => 2,
        ]);
        $vegetarian = Tag::query()->create(['name' => 'Vegetariana', 'slug' => 'vegetariana']);

        $this->actingAs($admin)->post(route('admin.products.store'), $this->validPayload($classic, [
            'name' => 'Caprese',
            'tags' => [$vegetarian->id],
        ]))->assertRedirect(route('admin.products.index'));

        $product = Product::query()->sole();
        $this->assertSame('caprese', $product->slug);
        $this->assertSame([1600, 9000, 16000], [
            $product->unit_price,
            $product->half_dozen_price,
            $product->dozen_price,
        ]);
        $this->assertTrue($product->tags()->whereKey($vegetarian->id)->exists());

        $this->actingAs($admin)->put(route('admin.products.update', $product), $this->validPayload($special, [
            'name' => 'Caprese especial',
            'tags' => [],
            'is_available' => false,
            'sort_order' => 8,
        ]))->assertRedirect(route('admin.products.index'));

        $product->refresh();
        $this->assertSame($special->id, $product->category_id);
        $this->assertSame('Caprese especial', $product->name);
        $this->assertFalse($product->is_available);
        $this->assertSame(8, $product->sort_order);
        $this->assertCount(0, $product->tags);

        $this->actingAs($admin)
            ->delete(route('admin.products.destroy', $product))
            ->assertRedirect(route('admin.products.index'));

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_product_input_is_validated_on_the_server(): void
    {
        $admin = User::factory()->administrator()->create();
        $category = $this->createCategory();

        $this->actingAs($admin)->post(route('admin.products.store'), [
            'name' => '',
            'description' => null,
            'category_id' => 999999,
            'tags' => [999999],
            'unit_price' => -1,
            'half_dozen_price' => 12.5,
            'dozen_price' => 'not-a-price',
            'is_available' => 'sometimes',
            'sort_order' => -1,
        ])->assertSessionHasErrors([
            'name',
            'category_id',
            'tags.0',
            'unit_price',
            'half_dozen_price',
            'dozen_price',
            'is_available',
            'sort_order',
        ]);

        $this->actingAs($admin)->post(route('admin.products.store'), $this->validPayload($category, [
            'image' => UploadedFile::fake()->create('unsafe.svg', 20, 'image/svg+xml'),
        ]))->assertSessionHasErrors('image');

        $this->actingAs($admin)->post(route('admin.products.store'), $this->validPayload($category, [
            'image' => UploadedFile::fake()->create('misleading.txt', 20, 'image/jpeg'),
        ]))->assertSessionHasErrors('image');

        $this->actingAs($admin)->post(route('admin.products.store'), $this->validPayload($category, [
            'image' => UploadedFile::fake()->image('too-large.jpg')->size(4097),
        ]))->assertSessionHasErrors('image');

        $this->assertDatabaseCount('products', 0);
    }

    public function test_product_image_is_stored_replaced_and_removed_through_laravel_storage(): void
    {
        Storage::fake('public');
        $admin = User::factory()->administrator()->create();
        $category = $this->createCategory();

        $this->actingAs($admin)->post(route('admin.products.store'), $this->validPayload($category, [
            'image' => UploadedFile::fake()->image('first.jpg', 400, 400),
        ]));

        $product = Product::query()->sole();
        $firstPath = $product->image_path;
        $this->assertNotNull($firstPath);
        Storage::disk('public')->assertExists($firstPath);

        $this->actingAs($admin)->post(route('admin.products.update', $product), $this->validPayload($category, [
            '_method' => 'put',
            'image' => UploadedFile::fake()->image('replacement.png', 400, 400),
        ]));

        $product->refresh();
        $this->assertNotSame($firstPath, $product->image_path);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($product->image_path);

        $replacementPath = $product->image_path;
        $this->actingAs($admin)->put(route('admin.products.update', $product), $this->validPayload($category, [
            'remove_image' => true,
        ]));

        $this->assertNull($product->refresh()->image_path);
        Storage::disk('public')->assertMissing($replacementPath);
    }

    public function test_unavailable_product_remains_visible_in_administration(): void
    {
        $admin = User::factory()->administrator()->create();
        $category = $this->createCategory();
        $this->createProduct($category, ['name' => 'Paused product', 'is_available' => false]);

        $this->actingAs($admin)->get(route('admin.products.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/products/index')
                ->has('products', 1)
                ->where('products.0.name', 'Paused product')
                ->where('products.0.is_available', false));
    }

    public function test_product_changes_are_logged_as_products_module_actions(): void
    {
        Log::spy();
        $admin = User::factory()->administrator()->create();
        $category = $this->createCategory();

        $this->actingAs($admin)
            ->post(route('admin.products.store'), $this->validPayload($category))
            ->assertRedirect(route('admin.products.index'));

        Log::shouldHaveReceived('info')
            ->withArgs(fn (string $message, array $context): bool => $message === 'admin.request.completed'
                && $context['module'] === 'Products'
                && $context['action'] === 'admin.products.store')
            ->once();
    }

    public function test_administrator_can_create_edit_deactivate_and_order_categories_but_not_delete_them(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->post(route('admin.product-categories.store'), [
            'name' => 'Seasonal',
            'sort_order' => 12,
            'is_active' => true,
        ])->assertRedirect(route('admin.products.index'));

        $category = Category::query()->where('slug', 'seasonal')->sole();
        $this->assertSame(12, $category->sort_order);

        $this->actingAs($admin)->put(route('admin.product-categories.update', $category), [
            'name' => 'Limited editions',
            'sort_order' => 3,
            'is_active' => false,
        ])->assertRedirect(route('admin.products.index'));

        $category->refresh();
        $this->assertSame('Limited editions', $category->name);
        $this->assertSame('limited-editions', $category->slug);
        $this->assertSame(3, $category->sort_order);
        $this->assertFalse($category->is_active);

        $this->createProduct($category);

        $this->actingAs($admin)
            ->delete("/admin/settings/categories/{$category->id}")
            ->assertMethodNotAllowed();
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_guests_cannot_manage_categories(): void
    {
        $category = $this->createCategory();

        $this->post(route('admin.product-categories.store'), [
            'name' => 'Guest category',
            'sort_order' => 2,
            'is_active' => true,
        ])->assertRedirect(route('login'));

        $this->put(route('admin.product-categories.update', $category), [
            'name' => 'Changed by guest',
            'sort_order' => 2,
            'is_active' => false,
        ])->assertRedirect(route('login'));

        $this->assertSame('Clásicas', $category->fresh()?->name);
    }

    /** @param array<string, mixed> $overrides */
    private function createCategory(array $overrides = []): Category
    {
        return Category::query()->create(array_merge([
            'name' => 'Clásicas',
            'slug' => 'clasicas',
            'sort_order' => 1,
            'is_active' => true,
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function createProduct(Category $category, array $overrides = []): Product
    {
        return Product::query()->create(array_merge([
            'category_id' => $category->id,
            'name' => 'Criolla Salada',
            'slug' => 'criolla-salada',
            'description' => null,
            'image_path' => null,
            'unit_price' => 1600,
            'half_dozen_price' => 9000,
            'dozen_price' => 16000,
            'is_available' => true,
            'sort_order' => 1,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(Category $category, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Criolla Salada',
            'description' => null,
            'category_id' => $category->id,
            'tags' => [],
            'unit_price' => 1600,
            'half_dozen_price' => 9000,
            'dozen_price' => 16000,
            'is_available' => true,
            'sort_order' => 1,
        ], $overrides);
    }
}
