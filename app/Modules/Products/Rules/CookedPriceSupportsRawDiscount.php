<?php

namespace App\Modules\Products\Rules;

use App\Modules\Products\Enums\PriceTier;
use App\Modules\Products\Models\RawPriceDiscount;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class CookedPriceSupportsRawDiscount implements ValidationRule
{
    public function __construct(private PriceTier $tier) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $discount = RawPriceDiscount::query()
            ->where('pricing_mode', $this->tier->value)
            ->value('amount');

        if ($discount !== null && (int) $value < (int) $discount) {
            $fail('El precio cocinado no puede ser menor que el descuento configurado para crudas.');
        }
    }
}
