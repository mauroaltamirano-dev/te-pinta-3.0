<?php

namespace App\Modules\Products\Http\Requests;

use App\Modules\Orders\Enums\OrderSettingKey;
use App\Modules\Orders\Models\OrderSetting;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Pricing\HomogeneousHalfDozenPrice;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

trait NormalizesHomogeneousSurcharge
{
    /**
     * Validate changed commercial configuration only; legacy metadata remains editable.
     *
     * @return list<\Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || $this->input('pricing_family') !== 'empanada') {
                return;
            }
            $u = $this->integer('unit_price');
            $h = $this->integer('half_dozen_price');
            $d = $this->integer('dozen_price');
            $product = $this->route('product');
            if ($product instanceof Product
                && $product->pricing_family->value === 'empanada'
                && [$u, $h, $d] === [$product->unit_price, $product->half_dozen_price, $product->dozen_price]) {
                return;
            }
            if ($d >= 12 * $u) {
                $validator->errors()->add('dozen_price', 'La docena debe costar menos que doce unidades.');
            }
            if ($h >= 6 * $u || 2 * $h < $d) {
                $validator->errors()->add('half_dozen_price', 'La media debe ahorrar frente a seis unidades y tener un recargo no negativo.');
            }
            $combined = OrderSettingKey::CombinedHalfDozenSurcharge;
            $surcharge = OrderSetting::query()->where('key', $combined->value)->value('integer_value');
            if ($surcharge === null) {
                $validator->errors()->add('dozen_price', 'Falta configurar el recargo de media docena combinada.');
            } elseif ($d + 2 * (int) $surcharge >= 12 * $u) {
                $validator->errors()->add('dozen_price', 'La docena y el recargo combinado no conservan ahorro por empanada cocinada.');
            }

        }];
    }

    private function normalizeHomogeneousSurcharge(): void
    {
        if (! $this->has('homogeneous_half_dozen_surcharge')) {
            return;
        }
        if ($this->input('pricing_family') !== 'empanada' || $this->has('half_dozen_price')) {
            throw ValidationException::withMessages(['homogeneous_half_dozen_surcharge' => 'Use un único precio de media docena o su recargo, sólo para empanadas.']);
        }
        $dozen = filter_var($this->input('dozen_price'), FILTER_VALIDATE_INT);
        if ($dozen === false) {
            return;
        }
        try {
            $half = HomogeneousHalfDozenPrice::fromSurcharge($dozen, (string) $this->input('homogeneous_half_dozen_surcharge'));
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['homogeneous_half_dozen_surcharge' => $exception->getMessage()]);
        }
        $this->merge(['half_dozen_price' => $half]);
    }
}
