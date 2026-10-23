<?php

namespace App\Modules\Products\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class UniqueSlug
{
    /** @param array<string, int> $scope Columns the slug is unique within (for example a subcategory's category). */
    public function for(string $table, string $value, ?int $exceptId = null, array $scope = []): string
    {
        $base = Str::slug($value) ?: 'item';
        $slug = $base;
        $suffix = 2;

        while (DB::table($table)
            ->when($exceptId !== null, fn ($query) => $query->where('id', '!=', $exceptId))
            ->where($scope)
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
