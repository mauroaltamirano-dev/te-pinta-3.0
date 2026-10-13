<?php

namespace Tests\Support;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Actions\ConfigureProductInventoryConsumption;
use App\Modules\Inventory\Actions\RecordInventoryTransaction;
use App\Modules\Inventory\Data\InventoryMovementData;
use App\Modules\Inventory\Data\InventoryTransactionData;
use App\Modules\Inventory\Enums\InventorySourceType;
use App\Modules\Inventory\Enums\InventoryTransactionType;
use App\Modules\Inventory\Models\InventoryCategory;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryUnit;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\RawPriceDiscount;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

trait CostingFixtures
{
    private User $admin;

    private Product $product;

    private InventoryItem $filling;

    private InventoryItem $box;

    private InventoryUnit $gram;

    private InventoryUnit $unit;

    private function costingFixture(string $family = 'empanada'): void
    {
        $this->admin = User::factory()->administrator()->create();
        foreach (['unit' => 100, 'half_dozen' => 1000, 'dozen' => 2000] as $mode => $amount) {
            RawPriceDiscount::query()->updateOrCreate(['pricing_mode' => $mode], ['amount' => $amount]);
        }
        $category = Category::query()->create(['name' => 'Arbitrary category', 'slug' => Str::uuid()]);
        $this->product = Product::query()->create(['category_id' => $category->id, 'name' => 'Current sale', 'slug' => Str::uuid(), 'pricing_family' => $family, 'unit_price' => 12000, 'half_dozen_price' => $family === 'unit' ? 12000 : 65000, 'dozen_price' => $family === 'unit' ? 12000 : 120000, 'is_available' => true]);
        $this->gram = InventoryUnit::query()->where('code', 'g')->sole();
        $this->unit = InventoryUnit::query()->where('code', 'unit')->sole();
        $c = InventoryCategory::query()->create(['name' => 'Physical costing']);
        $this->filling = InventoryItem::query()->create(['inventory_category_id' => $c->id, 'name' => 'Finished filling', 'kind' => 'semi_finished', 'tracks_stock' => true, 'unit_family' => 'weight', 'base_unit_id' => $this->gram->id, 'default_input_unit_id' => $this->gram->id, 'quantity_precision' => 6, 'order_consumption_enabled' => true, 'is_active' => true]);
        $this->box = InventoryItem::query()->create(['inventory_category_id' => $c->id, 'name' => 'Cardboard box', 'kind' => 'packaging', 'tracks_stock' => true, 'unit_family' => 'count', 'base_unit_id' => $this->unit->id, 'default_input_unit_id' => $this->unit->id, 'quantity_precision' => 6, 'is_active' => true]);
    }

    private function receive(InventoryItem $item, string $quantity, ?int $cost): void
    {
        app(RecordInventoryTransaction::class)->execute(new InventoryTransactionData(InventoryTransactionType::Adjustment, CarbonImmutable::parse('2026-10-13'), [new InventoryMovementData($item->id, $quantity, $cost)], InventorySourceType::Adjustment, (string) Str::uuid()), $this->admin->id);
    }

    private function consumption(string $quantity = '55', ?InventoryUnit $unit = null): void
    {
        app(ConfigureProductInventoryConsumption::class)->execute($this->product->id, 'consume', [['inventory_item_id' => $this->filling->id, 'quantity' => $quantity, 'unit_id' => ($unit ?? $this->gram)->id]], $this->admin->id);
    }

    /** @return array<string, mixed> */
    private function policyPayload(int $bp = 1000): array
    {
        return ['name' => 'Current estimate', 'overhead_basis_points' => $bp, 'is_active' => true, 'presentations' => [
            ['commercial_quantity' => 1, 'packaging_mode' => 'none', 'components' => []],
            ['commercial_quantity' => 6, 'packaging_mode' => 'components', 'components' => [['inventory_item_id' => $this->box->id, 'quantity' => '1', 'unit_id' => $this->unit->id]]],
            ['commercial_quantity' => 12, 'packaging_mode' => 'components', 'components' => [['inventory_item_id' => $this->box->id, 'quantity' => '2.000001', 'unit_id' => $this->unit->id]]],
        ]];
    }
}
