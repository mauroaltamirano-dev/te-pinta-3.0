<?php

namespace App\Modules\Marketing\Http\Resources;

use App\Modules\Marketing\Enums\FeaturedProductStatus;
use App\Modules\Marketing\Models\FeaturedProduct;
use App\Modules\Marketing\Queries\BuildFeaturedProductPublicView;
use App\Modules\Marketing\Queries\FeaturedProductResolver;
use App\Modules\Marketing\Support\MarketingClock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FeaturedProduct */
final class AdminFeaturedProductResource extends JsonResource
{
    public function __construct(
        FeaturedProduct $resource,
        private readonly MarketingClock $clock,
        private readonly FeaturedProductResolver $resolver,
        private readonly BuildFeaturedProductPublicView $publicView,
    ) {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $now = $this->clock->now();
        $status = $this->statusAt($now);
        $visibilityReason = $this->resolver->visibilityReason($this->resource);

        return [
            'id' => $this->id,
            'headline' => $this->headline,
            'short_copy' => $this->short_copy,
            'starts_at' => $this->starts_at->utc()->toIso8601String(),
            'ends_at' => $this->ends_at?->utc()->toIso8601String(),
            'starts_at_local' => $this->clock->formatLocal($this->starts_at),
            'ends_at_local' => $this->ends_at === null ? null : $this->clock->formatLocal($this->ends_at),
            'is_enabled' => $this->is_enabled,
            'status' => $status->value,
            'is_publicly_visible' => $status === FeaturedProductStatus::Active && $visibilityReason === null,
            'visibility_reason' => $visibilityReason,
            'product' => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'is_deleted' => $this->product->trashed(),
            ],
            'public_view' => $this->publicView->execute($this->resource),
        ];
    }
}
