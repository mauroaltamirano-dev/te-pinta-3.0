<?php

namespace App\Modules\Marketing\Support;

use App\Modules\Marketing\Data\FeaturedProductData;
use App\Modules\Marketing\Models\FeaturedProduct;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

final class FeaturedProductOverlap
{
    private const CONSTRAINT = 'marketing_featured_products_enabled_window_exclusion';

    public function ensureAvailable(FeaturedProductData $data, ?int $exceptId = null): void
    {
        if (! $data->isEnabled) {
            return;
        }

        $query = FeaturedProduct::query()
            ->where('is_enabled', true)
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->where(fn ($query) => $query
                ->whereNull('ends_at')
                ->orWhere('ends_at', '>', $data->startsAt));

        if ($data->endsAt !== null) {
            $query->where('starts_at', '<', $data->endsAt);
        }

        if ($query->lockForUpdate()->first(['id']) !== null) {
            $this->throwValidation();
        }
    }

    public function mapConstraintViolation(QueryException $exception): never
    {
        if ($exception->getCode() === '23P01'
            && str_contains((string) ($exception->errorInfo[2] ?? $exception->getMessage()), self::CONSTRAINT)) {
            $this->throwValidation();
        }

        throw $exception;
    }

    private function throwValidation(): never
    {
        throw ValidationException::withMessages([
            'starts_at' => 'Ya existe una recomendación programada para parte de este período.',
        ]);
    }
}
