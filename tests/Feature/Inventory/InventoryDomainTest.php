<?php

namespace Tests\Feature\Inventory;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Actions\CreateInventoryCategory;
use App\Modules\Inventory\Actions\CreateInventoryItem;
use App\Modules\Inventory\Actions\CreatePurchasePresentation;
use App\Modules\Inventory\Actions\RecordInventoryTransaction;
use App\Modules\Inventory\Actions\UpdateInventoryItem;
use App\Modules\Inventory\Data\InventoryItemData;
use App\Modules\Inventory\Data\InventoryMovementData;
use App\Modules\Inventory\Data\InventoryTransactionData;
use App\Modules\Inventory\Data\PurchasePresentationData;
use App\Modules\Inventory\Enums\InventorySourceType;
use App\Modules\Inventory\Enums\InventoryTransactionType;
use App\Modules\Inventory\Enums\UnitFamily;
use App\Modules\Inventory\Models\InventoryCostLayer;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryUnit;
use App\Modules\Inventory\Queries\InventoryMovementHistoryQuery;
use App\Modules\Inventory\Queries\InventoryStockBalanceQuery;
use App\Modules\Inventory\Support\UnitConverter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class InventoryDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_units_are_seeded_and_exactly_convert_to_base_units(): void
    {
        $units = InventoryUnit::query()->orderBy('id')->get();
        $this->assertSame(['g', 'kg', 'ml', 'L', 'unit', 'half_dozen', 'dozen'], $units->pluck('code')->all());
        $this->assertTrue($units->every(fn (InventoryUnit $unit): bool => $unit->is_system));

        $mozzarella = $this->createItem('Mozzarella', UnitFamily::Weight, 'g', 'kg', 3);
        $eggs = $this->createItem('Egg', UnitFamily::Count, 'unit', 'dozen', 0);
        $converter = app(UnitConverter::class);

        $this->assertSame('4000.000000', $converter->toBase($mozzarella, $this->unit('kg'), '4'));
        $this->assertSame('12.000000', $converter->toBase($eggs, $this->unit('dozen'), '1'));
    }

    public function test_item_creation_validates_category_unit_family_and_precision(): void
    {
        $category = app(CreateInventoryCategory::class)->execute('Spices');

        try {
            app(CreateInventoryItem::class)->execute(new InventoryItemData(
                $category->id, 'Cumin', true, UnitFamily::Weight,
                $this->unit('ml')->id, $this->unit('kg')->id, 7,
            ));
            $this->fail('Expected invalid item units and precision to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('base_unit_id', $exception->errors());
            $this->assertArrayHasKey('quantity_precision', $exception->errors());
        }
    }

    public function test_presentations_are_item_specific_compatible_and_precision_safe(): void
    {
        $egg = $this->createItem('Egg', UnitFamily::Count, 'unit', 'unit', 0);
        $presentation = app(CreatePurchasePresentation::class)->execute(new PurchasePresentationData(
            $egg->id, 'Maple', '30', $this->unit('unit')->id,
        ));

        $this->assertSame('30.000000', $presentation->content_quantity);
        $this->assertSame('unit', $presentation->contentUnit->code);

        foreach ([
            new PurchasePresentationData($egg->id, 'Liquid', '1', $this->unit('L')->id),
            new PurchasePresentationData($egg->id, 'Partial box', '1.5', $this->unit('unit')->id),
        ] as $invalidPresentation) {
            try {
                app(CreatePurchasePresentation::class)->execute($invalidPresentation);
                $this->fail('Expected incompatible or fractional count presentation to fail.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_transaction_records_signed_base_movements_cost_layers_and_derived_stock(): void
    {
        $admin = User::factory()->administrator()->create();
        $mozzarella = $this->createItem('Mozzarella', UnitFamily::Weight, 'g', 'kg', 3);

        $receipt = app(RecordInventoryTransaction::class)->execute(new InventoryTransactionData(
            InventoryTransactionType::PurchaseReceipt,
            CarbonImmutable::parse('2026-09-01'),
            [new InventoryMovementData($mozzarella->id, '4000', 40000)],
            InventorySourceType::Purchase,
            'purchase-001',
            'Initial receipt',
        ), $admin->id);
        app(RecordInventoryTransaction::class)->execute(new InventoryTransactionData(
            InventoryTransactionType::Adjustment,
            CarbonImmutable::parse('2026-09-02'),
            [new InventoryMovementData($mozzarella->id, '-500')],
            InventorySourceType::Adjustment,
            'adjustment-001',
        ), $admin->id);

        $this->assertSame('4000.000000', $receipt->movements->first()->quantity);
        $layer = InventoryCostLayer::query()->sole();
        $this->assertSame(40000, $layer->total_cost);
        $this->assertSame('4000.000000', $layer->quantity);
        $this->assertSame('10.000000000', $layer->unitCost());

        $balance = app(InventoryStockBalanceQuery::class)->forItems([$mozzarella->id])->sole();
        $this->assertSame('3500.000000', $balance->stock_quantity);
        $history = app(InventoryMovementHistoryQuery::class)->paginate();
        $this->assertSame(2, $history->total());
        $this->assertSame('adjustment-001', $history->items()[0]->source_id);
        $this->assertTrue($history->items()[0]->relationLoaded('movements'));
    }

    public function test_cost_unit_rate_is_derived_without_rounding_the_authoritative_values(): void
    {
        $admin = User::factory()->administrator()->create();
        $egg = $this->createItem('Egg', UnitFamily::Count, 'unit', 'unit', 0);
        app(RecordInventoryTransaction::class)->execute(new InventoryTransactionData(
            InventoryTransactionType::PurchaseReceipt,
            CarbonImmutable::parse('2026-09-02'),
            [new InventoryMovementData($egg->id, '30', 6500)],
            InventorySourceType::Purchase,
            'purchase-eggs',
        ), $admin->id);

        $layer = InventoryCostLayer::query()->sole();
        $this->assertSame(6500, $layer->total_cost);
        $this->assertSame('30.000000', $layer->quantity);
        $this->assertSame('216.666666667', $layer->unitCost());
    }

    public function test_fractional_movement_is_rejected_for_an_indivisible_item(): void
    {
        $admin = User::factory()->administrator()->create();
        $egg = $this->createItem('Egg', UnitFamily::Count, 'unit', 'unit', 0);

        $this->expectException(ValidationException::class);
        app(RecordInventoryTransaction::class)->execute(new InventoryTransactionData(
            InventoryTransactionType::Adjustment,
            CarbonImmutable::parse('2026-09-02'),
            [new InventoryMovementData($egg->id, '-1.5')],
            InventorySourceType::Adjustment,
            'fractional-eggs',
        ), $admin->id);
    }

    public function test_multi_movement_failure_rolls_back_the_complete_inventory_transaction(): void
    {
        $admin = User::factory()->administrator()->create();
        $stockable = $this->createItem('Flour', UnitFamily::Weight, 'g', 'kg', 3);
        $nonStockable = $this->createItem('Occasional tray', UnitFamily::Count, 'unit', 'unit', 0, false);

        try {
            app(RecordInventoryTransaction::class)->execute(new InventoryTransactionData(
                InventoryTransactionType::Adjustment,
                CarbonImmutable::parse('2026-09-02'),
                [
                    new InventoryMovementData($stockable->id, '1000'),
                    new InventoryMovementData($nonStockable->id, '1'),
                ],
                InventorySourceType::Adjustment,
                'atomic-failure',
            ), $admin->id);
            $this->fail('Expected the non-stockable movement to reject the transaction.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('inventory_transactions', 0);
            $this->assertDatabaseCount('inventory_stock_movements', 0);
        }
    }

    public function test_negative_stock_is_represented_by_the_signed_movement_sum(): void
    {
        $admin = User::factory()->administrator()->create();
        $item = $this->createItem('Beef', UnitFamily::Weight, 'g', 'kg', 3);

        app(RecordInventoryTransaction::class)->execute(new InventoryTransactionData(
            InventoryTransactionType::Adjustment,
            CarbonImmutable::parse('2026-09-02'),
            [new InventoryMovementData($item->id, '-4000')],
            InventorySourceType::Adjustment,
            'negative-stock',
        ), $admin->id);

        $this->assertSame('-4000.000000', app(InventoryStockBalanceQuery::class)->forItems([$item->id])->sole()->stock_quantity);
    }

    public function test_transaction_type_requires_its_matching_provenance_type(): void
    {
        $admin = User::factory()->administrator()->create();
        $item = $this->createItem('Oil', UnitFamily::Volume, 'ml', 'L', 3);

        $this->expectException(ValidationException::class);
        app(RecordInventoryTransaction::class)->execute(new InventoryTransactionData(
            InventoryTransactionType::PurchaseReceipt,
            CarbonImmutable::parse('2026-09-02'),
            [new InventoryMovementData($item->id, '1000')],
            InventorySourceType::Order,
            'wrong-provenance',
        ), $admin->id);
    }

    public function test_application_rejects_structural_item_changes_after_dependents_exist(): void
    {
        $admin = User::factory()->administrator()->create();
        $egg = $this->createItem('Egg', UnitFamily::Count, 'unit', 'unit', 0);
        app(CreatePurchasePresentation::class)->execute(new PurchasePresentationData(
            $egg->id, 'Maple', '30', $this->unit('unit')->id,
        ));

        try {
            app(UpdateInventoryItem::class)->execute($egg, new InventoryItemData(
                $egg->inventory_category_id, $egg->name, true, UnitFamily::Weight,
                $this->unit('g')->id, $this->unit('g')->id, 3,
            ));
            $this->fail('Expected presentation-dependent measurement identity to be protected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('measurement', $exception->errors());
        }

        app(RecordInventoryTransaction::class)->execute(new InventoryTransactionData(
            InventoryTransactionType::Adjustment, CarbonImmutable::parse('2026-09-02'),
            [new InventoryMovementData($egg->id, '1')], InventorySourceType::Adjustment, 'egg-stock',
        ), $admin->id);
        try {
            app(UpdateInventoryItem::class)->execute($egg, new InventoryItemData(
                $egg->inventory_category_id, $egg->name, false, UnitFamily::Count,
                $egg->base_unit_id, $egg->default_input_unit_id, 0,
            ));
            $this->fail('Expected stock tracking identity to be protected after a movement.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('measurement', $exception->errors());
        }
    }

    private function createItem(
        string $name,
        UnitFamily $family,
        string $baseCode,
        string $inputCode,
        int $precision,
        bool $tracksStock = true,
    ): InventoryItem {
        $category = app(CreateInventoryCategory::class)->execute($name.' category');

        return app(CreateInventoryItem::class)->execute(new InventoryItemData(
            $category->id, $name, $tracksStock, $family,
            $this->unit($baseCode)->id, $this->unit($inputCode)->id, $precision,
        ));
    }

    private function unit(string $code): InventoryUnit
    {
        return InventoryUnit::query()->where('code', $code)->firstOrFail();
    }
}
