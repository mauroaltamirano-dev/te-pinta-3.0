<?php

namespace App\Modules\Management\Http\Resources;

use App\Modules\Management\Models\DistributionRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DistributionRule */
final class AdminDistributionRuleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'active' => $this->active,
            'created_at' => $this->created_at?->toIso8601String(),
            'versions' => $this->versions->sortByDesc('version_number')->values()->map(fn ($version): array => [
                'id' => $version->id,
                'version_number' => $version->version_number,
                'created_at' => $version->created_at?->toIso8601String(),
                'allocations' => $version->allocations->map(fn ($allocation): array => [
                    'id' => $allocation->id,
                    'wallet' => [
                        'id' => $allocation->wallet->id,
                        'name' => $allocation->wallet->name,
                        'archived_at' => $allocation->wallet->archived_at?->toIso8601String(),
                    ],
                    'percentage_bps' => $allocation->percentage_bps,
                    'sort_order' => $allocation->sort_order,
                ])->all(),
            ])->all(),
        ];
    }
}
