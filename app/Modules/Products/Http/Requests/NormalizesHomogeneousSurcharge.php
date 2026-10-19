<?php

namespace App\Modules\Products\Http\Requests;

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
            $h = $this->integer('half_dozen_price');
            $d = $this->integer('dozen_price');
            $product = $this->route('product');
            if ($product instanceof Product
                && $product->pricing_family->value === 'empanada'
                && [$h, $d] === [$product->half_dozen_price, $product->dozen_price]) {
                return;
            }
            if (2 * $h < $d) {
                $validator->errors()->add('half_dozen_price', 'La media docena no puede costar menos que la mitad de la docena.');
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
