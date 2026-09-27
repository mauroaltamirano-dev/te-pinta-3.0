<?php

namespace Tests\Feature\Products;

use App\Modules\Identity\Models\User;
use App\Modules\Products\Enums\PriceTier;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\RawPriceDiscount;
use App\Modules\Products\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_entry_redirects_administrators_to_product_management(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertRedirect(route('admin.products.index'));
    }

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

    public function test_product_slug_is_stable_on_rename_and_explicitly_editable(): void
    {
        $admin = User::factory()->administrator()->create();
        $category = $this->createCategory();

        $this->actingAs($admin)->post(route('admin.products.store'), $this->validPayload($category, [
            'name' => 'Salteña',
        ]))->assertRedirect(route('admin.products.index'));

        $product = Product::query()->sole();
        $this->assertSame('saltena', $product->slug);

        $this->actingAs($admin)->put(route('admin.products.update', $product), $this->validPayload($category, [
            'name' => 'Salteña especial',
        ]))->assertRedirect(route('admin.products.index'));
        $this->assertSame('saltena', $product->fresh()->slug);

        $this->actingAs($admin)->put(route('admin.products.update', $product), $this->validPayload($category, [
            'name' => 'Salteña especial',
            'slug' => 'saltena-especial',
        ]))->assertRedirect(route('admin.products.index'));
        $this->assertSame('saltena-especial', $product->fresh()->slug);
    }

    public function test_product_slug_rejects_invalid_or_used_values_including_soft_deleted_products(): void
    {
        $admin = User::factory()->administrator()->create();
        $category = $this->createCategory();
        $existing = $this->createProduct($category);
        $existing->delete();

        foreach (['criolla-salada', 'Not Valid'] as $slug) {
            $this->actingAs($admin)->post(route('admin.products.store'), $this->validPayload($category, [
                'slug' => $slug,
            ]))->assertSessionHasErrors('slug');
        }

        $this->assertSame(1, Product::query()->withTrashed()->count());
    }

    public function test_administrator_can_manage_tag_vocabulary_and_existing_product_association(): void
    {
        $admin = User::factory()->administrator()->create();
        $category = $this->createCategory();
        $product = $this->createProduct($category);

        $this->actingAs($admin)->post(route('admin.product-tags.store'), [
            'name' => 'Más elegida',
            'slug' => '',
        ])->assertRedirect(route('admin.products.index'));

        $tag = Tag::query()->sole();
        $this->assertSame('mas-elegida', $tag->slug);

        $this->actingAs($admin)->put(route('admin.product-tags.update', $tag), [
            'name' => 'Favorita',
            'slug' => 'favorita',
        ])->assertRedirect(route('admin.products.index'));

        $this->actingAs($admin)->put(route('admin.products.update', $product), $this->validPayload($category, [
            'tags' => [$tag->id],
        ]))->assertRedirect(route('admin.products.index'));

        $this->assertSame('favorita', $product->fresh()->tags->sole()->slug);
        $this->actingAs($admin)->delete(route('admin.product-tags.destroy', $tag))
            ->assertSessionHasErrors('tag');
        $this->assertDatabaseHas('tags', ['id' => $tag->id]);

        $product->tags()->detach($tag);
        $this->actingAs($admin)->delete(route('admin.product-tags.destroy', $tag))
            ->assertRedirect(route('admin.products.index'));
        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
    }

    public function test_non_administrators_cannot_manage_tags(): void
    {
        $tag = Tag::query()->create(['name' => 'Clásica', 'slug' => 'clasica']);
        $payload = ['name' => 'Nueva', 'slug' => 'nueva'];

        $this->post(route('admin.product-tags.store'), $payload)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->post(route('admin.product-tags.store'), $payload)->assertForbidden();
        $this->put(route('admin.product-tags.update', $tag), $payload)->assertForbidden();
        $this->delete(route('admin.product-tags.destroy', $tag))->assertForbidden();
    }

    public function test_tag_slug_conflicts_are_reported_on_create_and_update(): void
    {
        $admin = User::factory()->administrator()->create();
        $existing = Tag::query()->create(['name' => 'Picante', 'slug' => 'picante']);
        $another = Tag::query()->create(['name' => 'Suave', 'slug' => 'suave']);

        $this->actingAs($admin)->post(route('admin.product-tags.store'), [
            'name' => 'Picante duplicada',
            'slug' => $existing->slug,
        ])->assertSessionHasErrors('slug');

        $this->actingAs($admin)->put(route('admin.product-tags.update', $another), [
            'name' => 'Suave',
            'slug' => $existing->slug,
        ])->assertSessionHasErrors('slug');

        $this->assertSame('suave', $another->fresh()->slug);
        $this->assertSame(2, Tag::query()->count());
    }

    public function test_database_tag_slug_conflict_after_validation_is_translated_to_a_field_error(): void
    {
        $admin = User::factory()->administrator()->create();
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION test_tag_slug_conflict() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'duplicate key value violates unique constraint "tags_slug_unique"' USING ERRCODE = '23505';
            END;
            $$;
            CREATE TRIGGER test_tag_slug_conflict BEFORE INSERT OR UPDATE ON tags
            FOR EACH ROW EXECUTE FUNCTION test_tag_slug_conflict();
            SQL);

        try {
            DB::statement('SAVEPOINT tag_slug_conflict');
            $response = $this->actingAs($admin)->post(route('admin.product-tags.store'), [
                'name' => 'Nueva',
                'slug' => '',
            ]);
            DB::statement('ROLLBACK TO SAVEPOINT tag_slug_conflict');
            $response->assertSessionHasErrors('slug');
        } finally {
            DB::unprepared('DROP TRIGGER test_tag_slug_conflict ON tags; DROP FUNCTION test_tag_slug_conflict();');
        }
    }

    public function test_public_editorial_metadata_is_saved_and_can_be_cleared(): void
    {
        $admin = User::factory()->administrator()->create();
        $category = $this->createCategory();

        $this->actingAs($admin)->post(route('admin.products.store'), $this->validPayload($category, [
            'description' => 'Short menu copy',
            'long_description' => 'A longer story for the product page.',
            'public_ingredients' => 'Tomato, basil, mozzarella.',
        ]))->assertRedirect(route('admin.products.index'));

        $product = Product::query()->sole();
        $this->assertSame('Short menu copy', $product->description);
        $this->assertSame('A longer story for the product page.', $product->long_description);
        $this->assertSame('Tomato, basil, mozzarella.', $product->public_ingredients);

        $this->actingAs($admin)->get(route('admin.products.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/products/index')
                ->where('products.0.long_description', 'A longer story for the product page.')
                ->where('products.0.public_ingredients', 'Tomato, basil, mozzarella.'));

        $this->actingAs($admin)->put(route('admin.products.update', $product), $this->validPayload($category, [
            'long_description' => '',
            'public_ingredients' => '',
        ]))->assertRedirect(route('admin.products.index'));

        $this->assertNull($product->fresh()->long_description);
        $this->assertNull($product->fresh()->public_ingredients);
    }

    public function test_public_editorial_metadata_length_is_validated_on_the_server(): void
    {
        $admin = User::factory()->administrator()->create();
        $category = $this->createCategory();

        $this->actingAs($admin)->post(route('admin.products.store'), $this->validPayload($category, [
            'long_description' => str_repeat('a', 5001),
            'public_ingredients' => str_repeat('b', 2001),
        ]))->assertSessionHasErrors(['long_description', 'public_ingredients']);

        $this->assertDatabaseCount('products', 0);
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

    public function test_cooked_product_prices_cannot_be_lower_than_the_raw_discounts(): void
    {
        $admin = User::factory()->administrator()->create();
        $category = $this->createCategory();
        RawPriceDiscount::query()->create([
            'pricing_mode' => PriceTier::Unit,
            'amount' => 200,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.products.store'), $this->validPayload($category, ['unit_price' => 199]))
            ->assertSessionHasErrors('unit_price');

        $this->assertDatabaseCount('products', 0);
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
