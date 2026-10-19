<?php

namespace Tests\Feature\Products;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\OrderSettingKey;
use App\Modules\Orders\Models\OrderSetting;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\RawPriceDiscount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class FixedDozenUnitPriceTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_10_19_000000_activate_fixed_dozen_pricing.php';

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['unit' => 200, 'half_dozen' => 1000, 'dozen' => 2000] as $mode => $amount) {
            RawPriceDiscount::query()->create(['pricing_mode' => $mode, 'amount' => $amount]);
        }
        $this->category = Category::query()->create(['name' => 'Empanadas', 'slug' => 'empanadas', 'is_active' => true]);
        $this->actingAs(User::factory()->administrator()->create());
    }

    public function test_the_surcharge_percentage_is_seeded_at_ten(): void
    {
        self::assertSame(10, OrderSetting::integer(OrderSettingKey::EmpanadaUnitSurchargePercent));
    }

    public function test_creating_an_empanada_stores_the_unit_price_derived_from_its_dozen(): void
    {
        $this->post(route('admin.products.store'), $this->payload(['dozen_price' => 20000]))->assertRedirect();

        $product = Product::query()->sole();
        self::assertSame([1800, 20000], [$product->unit_price, $product->dozen_price]);
    }

    public function test_the_empanada_unit_price_cannot_be_sent(): void
    {
        $this->post(route('admin.products.store'), $this->payload(['unit_price' => 1500]))
            ->assertSessionHasErrors('unit_price');

        $this->assertDatabaseCount('products', 0);
    }

    public function test_changing_the_dozen_recomputes_the_stored_unit_price(): void
    {
        $product = $this->empanada(20000);

        $this->put(route('admin.products.update', $product), $this->payload(['dozen_price' => 22000]))->assertRedirect();

        self::assertSame([2000, 22000], [$product->fresh()->unit_price, $product->fresh()->dozen_price]);
    }

    public function test_an_edit_that_keeps_the_dozen_keeps_the_stored_unit_price(): void
    {
        $product = $this->empanada(20000);
        // Percentage changes go through Settings, which recomputes every empanada; a product edit never does.
        OrderSetting::query()->where('key', OrderSettingKey::EmpanadaUnitSurchargePercent->value)->update(['integer_value' => 50]);

        $this->put(route('admin.products.update', $product), $this->payload(['dozen_price' => 20000, 'name' => 'Dulce renombrada']))->assertRedirect();

        self::assertSame(1800, $product->fresh()->unit_price);
        self::assertSame('Dulce renombrada', $product->fresh()->name);
    }

    public function test_a_unit_product_keeps_its_editable_unit_price(): void
    {
        $this->post(route('admin.products.store'), [
            ...$this->payload(), 'name' => 'Pizza', 'pricing_family' => 'unit', 'unit_price' => 7000,
            'dozen_price' => null, 'half_dozen_price' => null,
        ])->assertRedirect();

        self::assertSame(7000, Product::query()->sole()->unit_price);
    }

    public function test_switching_a_unit_product_to_empanada_derives_its_unit_price(): void
    {
        $product = Product::query()->create([
            'category_id' => $this->category->id, 'name' => 'Ex pizza', 'slug' => 'ex-pizza', 'pricing_family' => 'unit',
            'unit_price' => 7000, 'half_dozen_price' => 7000, 'dozen_price' => 7000, 'is_available' => true,
        ]);

        $this->put(route('admin.products.update', $product), $this->payload(['dozen_price' => 18000]))->assertRedirect();

        self::assertSame(1700, $product->fresh()->unit_price);
    }

    public function test_the_product_editor_receives_the_surcharge_percentage(): void
    {
        $this->get(route('admin.products.index'))->assertInertia(fn (Assert $page) => $page
            ->where('empanadaUnitSurchargePercent', 10));
    }

    public function test_activation_recomputes_existing_empanadas_once_and_leaves_unit_products(): void
    {
        $dulce = $this->empanada(20000);
        $pizza = Product::query()->create([
            'category_id' => $this->category->id, 'name' => 'Pizza', 'slug' => 'pizza', 'pricing_family' => 'unit',
            'unit_price' => 7000, 'half_dozen_price' => 7000, 'dozen_price' => 7000, 'is_available' => true,
        ]);
        DB::table('products')->where('id', $dulce->id)->update(['unit_price' => 2100]);
        $dulce->delete();

        $migration = require database_path(self::MIGRATION);
        $migration->down();
        $migration->up();

        self::assertSame(1800, (int) DB::table('products')->where('id', $dulce->id)->value('unit_price'));
        self::assertSame(7000, (int) DB::table('products')->where('id', $pizza->id)->value('unit_price'));
        self::assertSame(10, OrderSetting::integer(OrderSettingKey::EmpanadaUnitSurchargePercent));
    }

    private function empanada(int $dozen): Product
    {
        $this->post(route('admin.products.store'), $this->payload(['dozen_price' => $dozen]))->assertRedirect();

        return Product::query()->latest('id')->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Dulce', 'category_id' => $this->category->id, 'tags' => [], 'pricing_family' => 'empanada',
            'half_dozen_price' => 11000, 'dozen_price' => 20000, 'is_available' => true, 'sort_order' => 1,
            ...$overrides,
        ];
    }
}
