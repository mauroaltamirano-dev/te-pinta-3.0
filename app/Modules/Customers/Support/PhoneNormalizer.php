<?php

namespace App\Modules\Customers\Support;

final class PhoneNormalizer
{
    public function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $normalized = preg_replace('/\D+/', '', trim($phone));

        return $normalized === '' ? null : $normalized;
    }
}
