<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\CostingPolicy;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryUnit;
use App\Modules\Inventory\Support\UnitConverter;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class ConfigureCostingPolicy
{
    public function __construct(private UnitConverter $converter) {}

    /** @param array<string, mixed> $input */
    public function execute(?int $id, array $input): CostingPolicy
    {
        $input['name'] = is_string($input['name'] ?? null) ? trim($input['name']) : null;
        $v = Validator::make($input, [
            'name' => ['required', 'string', 'max:160'], 'overhead_basis_points' => ['required', 'integer', 'between:0,10000'], 'is_active' => ['required', 'boolean'],
            'presentations' => ['present', 'array', 'max:120'], 'presentations.*' => ['array:commercial_quantity,packaging_mode,components'],
            'presentations.*.commercial_quantity' => ['required', 'integer', 'between:1,120', 'distinct'],
            'presentations.*.packaging_mode' => ['required', 'in:none,components'], 'presentations.*.components' => ['present', 'array', 'max:50'],
            'presentations.*.components.*' => ['array:inventory_item_id,quantity,unit_id'],
            'presentations.*.components.*.inventory_item_id' => ['required', 'integer'],
            'presentations.*.components.*.quantity' => ['required', 'string', 'regex:/^\d+(\.\d{1,6})?$/'],
            'presentations.*.components.*.unit_id' => ['required', 'integer'],
        ])->validate();
        try {
            return DB::transaction(function () use ($id, $v): CostingPolicy {
                $policy = $id === null ? new CostingPolicy : CostingPolicy::query()->whereKey($id)->lockForUpdate()->firstOrFail();
                if (CostingPolicy::query()->whereRaw('lower(name) = lower(?)', [$v['name']])->when($id !== null, fn ($q) => $q->where('id', '<>', $id))->exists()) {
                    throw ValidationException::withMessages(['name' => 'Ya existe un packaging y gastos con ese nombre. Usá otro nombre.']);
                }
                $all = [];
                foreach ($v['presentations'] as $presentation) {
                    foreach ($presentation['components'] as $component) {
                        $all[] = $component;
                    }
                }
                $items = InventoryItem::query()->whereKey(array_column($all, 'inventory_item_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $units = InventoryUnit::query()->whereKey(array_column($all, 'unit_id'))->orderBy('id')->get()->keyBy('id');
                $rows = [];
                foreach ($v['presentations'] as $p) {
                    if (($p['packaging_mode'] === 'none') !== ($p['components'] === [])) {
                        throw ValidationException::withMessages(['presentations' => 'Indicá que no lleva caja ni envoltorio o agregá al menos uno.']);
                    }
                    if (count(array_unique(array_column($p['components'], 'inventory_item_id'))) !== count($p['components'])) {
                        throw ValidationException::withMessages(['presentations' => 'Cada caja o envoltorio puede aparecer una sola vez por venta. Uní las cantidades en una sola línea.']);
                    }
                    $components = [];
                    foreach ($p['components'] as $c) {
                        $item = $items->get($c['inventory_item_id']);
                        $unit = $units->get($c['unit_id']);
                        if ($item === null || $unit === null || ! $item->is_active || ! $item->tracks_stock || ! in_array($item->kind, ['packaging', 'consumable'], true)) {
                            throw ValidationException::withMessages(['presentations' => 'Elegí un insumo activo de packaging o consumible que controle stock, con una medida compatible.']);
                        }
                        $components[] = $c + ['base_quantity' => $this->converter->toBase($item, $unit, $c['quantity'])];
                    }
                    $rows[] = [$p, $components];
                }
                $policy->fill(['name' => $v['name'], 'overhead_basis_points' => $v['overhead_basis_points'], 'is_active' => $v['is_active']])->save();
                $policy->presentations()->delete();
                foreach ($rows as [$p,$components]) {
                    $presentation = $policy->presentations()->create(['commercial_quantity' => $p['commercial_quantity'], 'packaging_mode' => $p['packaging_mode']]);
                    $presentation->components()->createMany($components);
                }

                return $policy->load('presentations.components.item', 'presentations.components.unit');
            });
        } catch (QueryException $e) {
            if (($e->errorInfo[0] ?? null) === '23505') {
                throw ValidationException::withMessages(['name' => 'Ya existe un packaging y gastos con ese nombre. Usá otro nombre.']);
            }
            throw $e;
        }
    }
}
