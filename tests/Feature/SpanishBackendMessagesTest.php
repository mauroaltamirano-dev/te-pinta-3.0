<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\InventoryCategory;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryUnit;
use App\Modules\Inventory\Models\PurchasePresentation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

/** User-visible backend errors are Spanish and say what to do. */
final class SpanishBackendMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_framework_rules_and_field_names_render_in_spanish(): void
    {
        $validator = Validator::make(
            ['quantity' => '1.1234567', 'output_inventory_item_id' => null],
            ['quantity' => ['decimal:0,6'], 'output_inventory_item_id' => ['required']],
        );

        $this->assertSame('El campo cantidad debe tener 0-6 decimales.', $validator->errors()->first('quantity'));
        $this->assertSame('El campo insumo producido es obligatorio.', $validator->errors()->first('output_inventory_item_id'));
    }

    public function test_duplicate_inventory_category_explains_how_to_fix_it(): void
    {
        $admin = User::factory()->administrator()->create();
        InventoryCategory::query()->create(['name' => 'Carnes', 'is_active' => true]);

        $this->actingAs($admin)
            ->from(route('admin.inventory.index'))
            ->post(route('admin.inventory.categories.store'), ['name' => 'carnes'])
            ->assertSessionHasErrors(['name' => 'Ya existe una categoría de inventario con ese nombre. Usá otro nombre.']);
    }

    public function test_duplicate_purchase_presentation_explains_how_to_fix_it(): void
    {
        $admin = User::factory()->administrator()->create();
        $item = $this->item();
        PurchasePresentation::query()->create(['inventory_item_id' => $item->id, 'name' => 'Horma', 'content_quantity' => '4000.000000', 'content_unit_id' => $item->base_unit_id, 'is_active' => true]);

        $this->actingAs($admin)
            ->from(route('admin.inventory.index'))
            ->post(route('admin.inventory.presentations.store'), ['inventory_item_id' => $item->id, 'name' => 'HORMA', 'content_quantity' => '4000', 'content_unit_id' => $item->base_unit_id, 'is_active' => true])
            ->assertSessionHasErrors(['name' => 'Este insumo ya tiene una presentación con ese nombre. Usá otro nombre.']);
    }

    public function test_presentation_unit_of_another_measure_explains_how_to_fix_it(): void
    {
        $admin = User::factory()->administrator()->create();
        $item = $this->item();

        $this->actingAs($admin)
            ->from(route('admin.inventory.index'))
            ->post(route('admin.inventory.presentations.store'), ['inventory_item_id' => $item->id, 'name' => 'Botella', 'content_quantity' => '1', 'content_unit_id' => $this->unit('ml')->id, 'is_active' => true])
            ->assertSessionHasErrors(['content_unit_id' => 'La unidad del contenido no corresponde a la medida del insumo. Elegí una unidad de la misma medida.']);
    }

    public function test_adjustment_errors_explain_how_to_fix_them(): void
    {
        $admin = User::factory()->administrator()->create();
        $item = $this->item();
        $values = ['inventory_item_id' => $item->id, 'unit_id' => $this->unit('g')->id, 'type' => 'waste', 'quantity' => '1.0001', 'effective_date' => '2026-10-11', 'idempotency_key' => (string) Str::uuid()];

        $this->actingAs($admin)
            ->postJson(route('admin.inventory.adjustments.preview'), $values)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity' => 'La cantidad tiene más decimales de los que admite el insumo. Redondeala o usá otra unidad.']);

        $this->postJson(route('admin.inventory.adjustments.preview'), ['unit_id' => $this->unit('ml')->id, 'quantity' => '1'] + $values)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['unit_id' => 'Elegí una unidad compatible con la medida del insumo.']);

        $this->postJson(route('admin.inventory.adjustments.preview'), ['quantity' => '1', 'explicit_total_cost' => 100] + $values)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['explicit_total_cost' => 'El costo total solo se carga cuando el ajuste suma stock. Dejalo vacío para este ajuste.']);
    }

    private function unit(string $code): InventoryUnit
    {
        return InventoryUnit::query()->where('code', $code)->sole();
    }

    private function item(): InventoryItem
    {
        $grams = $this->unit('g');
        $category = InventoryCategory::query()->create(['name' => 'Lácteos', 'is_active' => true]);

        return InventoryItem::query()->create(['inventory_category_id' => $category->id, 'name' => 'Muzzarella', 'tracks_stock' => true, 'unit_family' => 'weight', 'base_unit_id' => $grams->id, 'default_input_unit_id' => $grams->id, 'quantity_precision' => 3, 'is_active' => true, 'kind' => 'raw_material']);
    }
}
