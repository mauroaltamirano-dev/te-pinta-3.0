<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryUnit;
use App\Modules\Inventory\Models\ProductInventoryConsumptionDefinition;
use App\Modules\Inventory\Models\ProductInventoryConsumptionVersion;
use App\Modules\Inventory\Support\DecimalMath;
use App\Modules\Inventory\Support\UnitConverter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final readonly class ConfigureProductInventoryConsumption
{
    public function __construct(private UnitConverter $converter) {}

    /** @param list<array{inventory_item_id:int,quantity:string,unit_id:int}> $components */
    public function execute(int $productId, string $mode, array $components, int $adminId): ProductInventoryConsumptionDefinition
    {
        return DB::transaction(function () use ($productId, $mode, $components, $adminId): ProductInventoryConsumptionDefinition {
            $product = DB::table('products')->where('id', $productId)->whereNull('deleted_at')->lockForUpdate()->first();
            if ($product === null || ! in_array($mode, ['consume', 'no_inventory'], true)) {
                throw ValidationException::withMessages(['mode' => 'Elegí si el producto consume insumos o no usa inventario.']);
            }
            $d = ProductInventoryConsumptionDefinition::query()->where('product_id', $productId)->lockForUpdate()->first();
            if ($d !== null && $d->mode !== $mode) {
                throw ValidationException::withMessages(['mode' => 'El modo de consumo de este producto ya está definido y no se puede cambiar. Si necesitás cambiarlo, consultá al responsable del sistema.']);
            }
            if (($mode === 'no_inventory' && $components !== []) || ($mode === 'consume' && $components === [])) {
                throw ValidationException::withMessages(['components' => 'Si el producto consume insumos, agregá al menos un componente; si no usa inventario, quitá los componentes.']);
            }
            $d ??= ProductInventoryConsumptionDefinition::query()->create(['product_id' => $productId, 'mode' => $mode, 'created_by' => $adminId]);
            if ($mode === 'no_inventory') {
                return $d->load('currentVersion.components');
            }
            $versions = $d->versions()->orderBy('id')->lockForUpdate()->get();
            $ids = array_column($components, 'inventory_item_id');
            if (count(array_unique($ids)) !== count($ids)) {
                throw ValidationException::withMessages(['components' => 'Cada insumo puede aparecer una sola vez. Uní las cantidades en una sola línea.']);
            }
            $items = InventoryItem::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $units = InventoryUnit::query()->whereKey(array_column($components, 'unit_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $v = ProductInventoryConsumptionVersion::query()->create(['definition_id' => $d->id, 'version' => ($versions->max('version') ?? 0) + 1, 'product_name_snapshot' => $product->name, 'is_active' => false, 'is_sealed' => false, 'created_by' => $adminId]);
            foreach ($components as $c) {
                $i = $items->get($c['inventory_item_id']);
                $u = $units->get($c['unit_id']);
                if ($i === null || $u === null || ! $i->is_active || ! $i->tracks_stock || ! $i->order_consumption_enabled) {
                    throw ValidationException::withMessages(['components' => 'Elegí insumos activos, que controlen stock y tengan habilitado "Consumo al vender".']);
                }
                try {
                    $q = DecimalMath::normalize($c['quantity'], 6);
                } catch (InvalidArgumentException $e) {
                    throw ValidationException::withMessages(['components' => $e->getMessage()]);
                }
                $v->components()->create(['inventory_item_id' => $i->id, 'quantity' => $q, 'unit_id' => $u->id, 'base_quantity' => $this->converter->toBase($i, $u, $q)]);
            }
            foreach ($versions as $old) {
                if ($old->is_active) {
                    $old->update(['is_active' => false]);
                }
            }
            $v->update(['is_active' => true, 'is_sealed' => true]);
            $d->update(['current_version_id' => $v->id]);

            return $d->fresh()->load('currentVersion.components.item.baseUnit', 'versions.components.unit');
        });
    }
}
