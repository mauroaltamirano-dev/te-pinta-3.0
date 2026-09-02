<?php

namespace App\Modules\Marketing\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class PlainText implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && preg_match('/<[^>]+>/u', $value) === 1) {
            $fail('El campo :attribute debe contener texto plano, sin HTML.');
        }
    }
}
