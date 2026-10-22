<?php

namespace Tests\Feature\Inventory;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Actions\RecordInventoryTransaction;
use App\Modules\Inventory\Data\InventoryMovementData;
use App\Modules\Inventory\Data\InventoryTransactionData;
use App\Modules\Inventory\Enums\InventorySourceType;
use App\Modules\Inventory\Enums\InventoryTransactionType;
use App\Modules\Inventory\Models\InventoryCategory;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryUnit;
use App\Modules\Inventory\Models\PurchasePresentation;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class InventoryAdminHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_inventory_admin_routes_require_an_administrator(): void
    {
        $category = $this->category();
        $item = $this->item($category);
        $presentation = $this->presentation($item);

        $this->get(route('admin.inventory.index'))->assertRedirect(route('login'));

        $user = User::factory()->create();
        $this->actingAs($user)->get(route('admin.inventory.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.inventory.categories.store'), ['name' => 'Carnes'])->assertForbidden();
        $this->actingAs($user)->patch(route('admin.inventory.items.update', $item), $this->itemPayload($category))->assertForbidden();
        $this->actingAs($user)->patch(route('admin.inventory.presentations.update', $presentation), $this->presentationPayload($item))->assertForbidden();
    }

    public function test_administrator_can_create_and_update_categories(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)
            ->post(route('admin.inventory.categories.store'), ['name' => ' Verduras '])
            ->assertRedirect(route('admin.inventory.index'));

        $category = InventoryCategory::query()->sole();
        $this->assertSame('Verduras', $category->name);
        $this->assertTrue($category->is_active);

        $this->actingAs($admin)
            ->patch(route('admin.inventory.categories.update', $category), [
                'name' => 'Vegetales',
                'is_active' => false,
            ])
            ->assertRedirect(route('admin.inventory.index'));

        $this->assertDatabaseHas('inventory_categories', [
            'id' => $category->id,
            'name' => 'Vegetales',
            'is_active' => false,
        ]);
    }

    public function test_case_insensitive_catalog_duplicates_return_validation_errors(): void
    {
        $admin = User::factory()->administrator()->create();
        $category = $this->category(['name' => 'Carnes']);
        $item = $this->item($category, ['name' => 'Huevo']);
        $this->presentation($item, ['name' => 'Maple']);

        $this->actingAs($admin)
            ->from(route('admin.inventory.index'))
            ->post(route('admin.inventory.categories.store'), ['name' => 'carnes'])
            ->assertRedirect(route('admin.inventory.index'))
            ->assertSessionHasErrors('name');

        $this->actingAs($admin)
            ->from(route('admin.inventory.index'))
            ->post(route('admin.inventory.items.store'), $this->itemPayload($category, ['name' => 'hUEVO']))
            ->assertSessionHasErrors('name');

        $this->actingAs($admin)
            ->from(route('admin.inventory.index'))
            ->post(route('admin.inventory.presentations.store'), $this->presentationPayload($item, ['name' => 'MAPLE']))
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('inventory_categories', 1);
        $this->assertDatabaseCount('inventory_items', 1);
        $this->assertDatabaseCount('inventory_purchase_presentations', 1);
    }

    public function test_administrator_can_create_and_update_items_and_presentations(): void
    {
        $admin = User::factory()->administrator()->create();
        $category = $this->category();
        $grams = $this->unit('g');
        $kilograms = $this->unit('kg');

        $this->actingAs($admin)
            ->post(route('admin.inventory.items.store'), $this->itemPayload($category, [
                'name' => 'Comino',
                'base_unit_id' => $grams->id,
                'default_input_unit_id' => $kilograms->id,
            ]))
            ->assertRedirect(route('admin.inventory.index'));

        $item = InventoryItem::query()->sole();
        $this->assertSame('weight', $item->unit_family->value);
        $this->assertSame($kilograms->id, $item->default_input_unit_id);

        $this->actingAs($admin)
            ->post(route('admin.inventory.presentations.store'), $this->presentationPayload($item, [
                'name' => 'Bolsa',
                'content_quantity' => '1.000000',
                'content_unit_id' => $kilograms->id,
            ]))
            ->assertRedirect(route('admin.inventory.index'));

        $presentation = PurchasePresentation::query()->sole();
        $this->assertSame('1.000000', $presentation->content_quantity);

        $this->actingAs($admin)
            ->patch(route('admin.inventory.items.update', $item), $this->itemPayload($category, [
                'name' => 'Comino molido',
                'base_unit_id' => $grams->id,
                'default_input_unit_id' => $grams->id,
                'quantity_precision' => 3,
            ]))
            ->assertRedirect(route('admin.inventory.index'));
        $this->actingAs($admin)
            ->patch(route('admin.inventory.presentations.update', $presentation), $this->presentationPayload($item, [
                'name' => 'Bolsa grande',
                'content_quantity' => '1000.000000',
                'content_unit_id' => $grams->id,
                'is_active' => false,
            ]))
            ->assertRedirect(route('admin.inventory.index'));

        $this->assertSame('Comino molido', $item->fresh()?->name);
        $this->assertDatabaseHas('inventory_purchase_presentations', [
            'id' => $presentation->id,
            'name' => 'Bolsa grande',
            'is_active' => false,
        ]);
    }

    public function test_item_and_presentation_references_and_units_are_validated_on_the_server(): void
    {
        $admin = User::factory()->administrator()->create();
        $inactiveCategory = $this->category(['is_active' => false]);
        $grams = $this->unit('g');
        $milliliters = $this->unit('ml');

        $this->actingAs($admin)
            ->post(route('admin.inventory.items.store'), $this->itemPayload($inactiveCategory))
            ->assertSessionHasErrors('category_id');
        $this->actingAs($admin)
            ->post(route('admin.inventory.items.store'), $this->itemPayload($inactiveCategory, [
                'category_id' => $this->category(['name' => 'Activa'])->id,
                'base_unit_id' => $milliliters->id,
                'default_input_unit_id' => $grams->id,
                'quantity_precision' => 7,
            ]))
            ->assertSessionHasErrors(['base_unit_id', 'quantity_precision']);

        $countCategory = $this->category(['name' => 'Huevos']);
        $egg = $this->item($countCategory, [
            'name' => 'Huevo',
            'unit_family' => 'count',
            'base_unit_id' => $this->unit('unit')->id,
            'default_input_unit_id' => $this->unit('unit')->id,
            'quantity_precision' => 0,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.inventory.presentations.store'), $this->presentationPayload($egg, [
                'content_quantity' => '1.500000',
                'content_unit_id' => $this->unit('unit')->id,
            ]))
            ->assertSessionHasErrors('content_quantity');
        $this->actingAs($admin)
            ->post(route('admin.inventory.presentations.store'), $this->presentationPayload($egg, [
                'content_unit_id' => $grams->id,
            ]))
            ->assertSessionHasErrors('content_unit_id');

        $this->assertDatabaseCount('inventory_purchase_presentations', 0);
    }

    public function test_admin_can_set_count_reference_mass_without_changing_stock_unit(): void
    {
        $admin = User::factory()->administrator()->create();
        $category = $this->category(['name' => 'Huevos']);
        $unit = $this->unit('unit');

        $payload = $this->itemPayload($category, [
            'name' => 'Huevo',
            'unit_family' => 'count',
            'base_unit_id' => $unit->id,
            'default_input_unit_id' => $unit->id,
            'quantity_precision' => 0,
            'reference_mass_grams_per_unit' => '60',
        ]);
        $this->actingAs($admin)->post(route('admin.inventory.items.store'), $payload)->assertRedirect();
        $item = InventoryItem::query()->sole();
        $this->assertSame('60.000000', $item->reference_mass_grams_per_unit);
        $this->assertSame($unit->id, $item->base_unit_id);
        $this->actingAs($admin)->get(route('admin.inventory.index'))->assertInertia(fn (Assert $page) => $page->where('items.0.reference_mass_grams_per_unit', '60.000000'));

        $this->actingAs($admin)->patch(route('admin.inventory.items.update', $item), [
            ...$payload,
            'unit_family' => 'weight',
            'base_unit_id' => $this->unit('g')->id,
            'default_input_unit_id' => $this->unit('g')->id,
        ])->assertSessionHasErrors('reference_mass_grams_per_unit');
    }

    public function test_index_exposes_catalog_derived_stock_and_paginated_movement_history(): void
    {
        $admin = User::factory()->administrator()->create();
        $category = $this->category();
        $item = $this->item($category, ['name' => 'Roast beef']);
        $presentation = $this->presentation($item, ['name' => 'Caja']);

        app(RecordInventoryTransaction::class)->execute(new InventoryTransactionData(
            type: InventoryTransactionType::Adjustment,
            effectiveDate: CarbonImmutable::parse('2026-09-01'),
            movements: [new InventoryMovementData($item->id, '4000.000000')],
            sourceType: InventorySourceType::Adjustment,
            sourceId: 'inventory-http-test-1',
            description: 'Initial count',
        ), $admin->id);

        $this->actingAs($admin)
            ->get(route('admin.inventory.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/inventory/index')
                ->where('categories.0.name', 'Carnes')
                ->where('units.0.family', 'count')
                ->where('items.0.name', 'Roast beef')
                ->where('items.0.stock_quantity', '4000.000000')
                ->where('items.0.presentations.0.id', $presentation->id)
                ->where('transactions.data.0.type', 'adjustment')
                ->where('transactions.data.0.effective_date', '2026-09-01')
                ->where('transactions.data.0.source_type', 'adjustment')
                ->where('transactions.data.0.movements.0.inventory_item.name', 'Roast beef')
                ->where('transactions.data.0.movements.0.quantity', '4000.000000')
                ->where('transactions.current_page', 1)
                ->where('transactions.total', 1));
    }

    public function test_index_filters_items_by_category_classification_and_name_on_the_server(): void
    {
        $admin = User::factory()->administrator()->create();
        $meats = $this->category();
        $fillings = $this->category(['name' => 'Rellenos']);
        $this->item($meats, ['name' => 'Roast beef', 'kind' => 'raw_material']);
        $this->item($meats, ['name' => 'Grasa', 'kind' => 'raw_material']);
        $this->item($fillings, ['name' => 'Relleno Salteñas', 'kind' => 'semi_finished']);
        $this->item($fillings, ['name' => 'Sal', 'kind' => null]);
        $names = fn (array $query): array => collect($this->actingAs($admin)
            ->get(route('admin.inventory.index', $query))
            ->assertOk()
            ->viewData('page')['props']['items'])->pluck('name')->all();

        $this->assertSame(['Grasa', 'Relleno Salteñas', 'Roast beef', 'Sal'], $names([]));
        $this->assertSame(['Grasa', 'Roast beef'], $names(['category' => $meats->id]));
        $this->assertSame(['Relleno Salteñas'], $names(['kind' => 'semi_finished']));
        $this->assertSame(['Relleno Salteñas', 'Sal'], $names(['search' => 'SAL']));
        $this->assertSame(['Relleno Salteñas'], $names(['search' => ' saltenas ']));
        $this->assertSame([], $names(['category' => $meats->id, 'kind' => 'semi_finished']));
        $this->assertSame(['Grasa'], $names(['category' => $meats->id, 'search' => 'gra', 'kind' => null]));

        $this->actingAs($admin)
            ->get(route('admin.inventory.index', ['category' => $fillings->id, 'kind' => 'raw_material', 'search' => 'ro']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.category', $fillings->id)
                ->where('filters.kind', 'raw_material')
                ->where('filters.search', 'ro')
                ->where('filters.transaction_id', null)
                ->where('items', [])
                ->where('valuation.zero', 4));
    }

    public function test_index_ignores_invalid_item_filters_instead_of_redirecting(): void
    {
        $admin = User::factory()->administrator()->create();
        $category = $this->category();
        $this->item($category, ['name' => 'Roast beef', 'kind' => 'raw_material']);

        $this->actingAs($admin)
            ->get(route('admin.inventory.index', ['category' => 999999, 'kind' => 'bogus', 'search' => ['x']]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.category', null)
                ->where('filters.kind', null)
                ->where('filters.search', '')
                ->where('items.0.name', 'Roast beef'));
    }

    public function test_item_and_category_saves_return_to_the_filtered_list(): void
    {
        $admin = User::factory()->administrator()->create();
        $category = $this->category();
        $grams = $this->unit('g');
        $item = $this->item($category, ['name' => 'Roast beef', 'kind' => 'raw_material']);
        $filtered = route('admin.inventory.index', ['category' => $category->id, 'kind' => 'raw_material', 'search' => 'roast']);

        $this->actingAs($admin)->from($filtered)
            ->patch(route('admin.inventory.items.update', $item), $this->itemPayload($category, [
                'name' => 'Roast beef editado',
                'base_unit_id' => $grams->id,
                'default_input_unit_id' => $grams->id,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect($filtered);
        $this->assertSame('Roast beef editado', $item->fresh()->name);

        $this->actingAs($admin)->from($filtered)
            ->post(route('admin.inventory.items.store'), $this->itemPayload($category, [
                'name' => 'Grasa',
                'base_unit_id' => $grams->id,
                'default_input_unit_id' => $grams->id,
            ]))
            ->assertRedirect($filtered);

        $this->actingAs($admin)->from($filtered)
            ->patch(route('admin.inventory.categories.update', $category), ['name' => 'Carnes rojas', 'is_active' => true])
            ->assertRedirect($filtered);

        $this->actingAs($admin)->from($filtered)
            ->post(route('admin.inventory.categories.store'), ['name' => 'Verduras'])
            ->assertRedirect($filtered);
    }

    /** @param array<string, mixed> $overrides */
    private function category(array $overrides = []): InventoryCategory
    {
        return InventoryCategory::query()->create([
            'name' => 'Carnes',
            'is_active' => true,
            ...$overrides,
        ]);
    }

    private function unit(string $code): InventoryUnit
    {
        return InventoryUnit::query()->where('code', $code)->sole();
    }

    /** @param array<string, mixed> $overrides */
    private function item(InventoryCategory $category, array $overrides = []): InventoryItem
    {
        $baseUnit = $this->unit('g');

        return InventoryItem::query()->create([
            'inventory_category_id' => $category->id,
            'name' => 'Muzzarella',
            'tracks_stock' => true,
            'unit_family' => 'weight',
            'base_unit_id' => $baseUnit->id,
            'default_input_unit_id' => $baseUnit->id,
            'quantity_precision' => 3,
            'is_active' => true,
            ...$overrides,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function presentation(InventoryItem $item, array $overrides = []): PurchasePresentation
    {
        return PurchasePresentation::query()->create([
            'inventory_item_id' => $item->id,
            'name' => 'Horma',
            'content_quantity' => '4000.000000',
            'content_unit_id' => $item->base_unit_id,
            'is_active' => true,
            ...$overrides,
        ]);
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function itemPayload(InventoryCategory $category, array $overrides = []): array
    {
        $grams = $this->unit('g');

        return [
            'category_id' => $category->id,
            'name' => 'Muzzarella',
            'tracks_stock' => true,
            'unit_family' => 'weight',
            'base_unit_id' => $grams->id,
            'default_input_unit_id' => $grams->id,
            'quantity_precision' => 3,
            'is_active' => true,
            'kind' => 'raw_material',
            'recipe_input_enabled' => true,
            'production_output_enabled' => false,
            'order_consumption_enabled' => false,
            ...$overrides,
        ];
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function presentationPayload(InventoryItem $item, array $overrides = []): array
    {
        return [
            'inventory_item_id' => $item->id,
            'name' => 'Horma',
            'content_quantity' => '4000.000000',
            'content_unit_id' => $item->base_unit_id,
            'is_active' => true,
            ...$overrides,
        ];
    }
}
