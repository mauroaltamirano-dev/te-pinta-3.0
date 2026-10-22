<?php

namespace App\Modules\Inventory\Support;

use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryUnit;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class UnitConverter
{
    public function toBase(InventoryItem $item, InventoryUnit $inputUnit, string $quantity): string
    {
        if ($item->unit_family !== $inputUnit->family) {
            throw ValidationException::withMessages(['unit_id' => 'La unidad elegida no corresponde a la medida del insumo. Elegí una unidad de la misma medida.']);
        }

        try {
            $baseQuantity = DecimalMath::multiply($quantity, $inputUnit->factor_to_base, 6);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['quantity' => $exception->getMessage()]);
        }

        if ($baseQuantity[0] === '-' || trim(str_replace('.', '', $baseQuantity), '0') === '') {
            throw ValidationException::withMessages(['quantity' => 'La cantidad debe ser mayor que cero. Ingresá una cantidad positiva.']);
        }

        $fraction = explode('.', $baseQuantity, 2)[1] ?? '';
        if (trim(substr($fraction, $item->quantity_precision), '0') !== '') {
            throw ValidationException::withMessages(['quantity' => 'La cantidad tiene más decimales de los que admite el insumo. Redondeala o usá otra unidad.']);
        }

        if (strlen(ltrim(explode('.', $baseQuantity, 2)[0], '0')) > 14) {
            throw ValidationException::withMessages(['quantity' => 'La cantidad es demasiado grande. Revisá el número y la unidad elegida.']);
        }

        return $baseQuantity;
    }
}
