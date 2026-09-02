<?php

namespace App\Modules\Marketing\Queries;

use App\Modules\Marketing\Models\FeaturedProduct;
use App\Modules\Marketing\Support\MarketingClock;

final readonly class BuildMarketingDashboardSummary
{
    public function __construct(
        private FeaturedProductResolver $resolver,
        private MarketingClock $clock,
    ) {}

    /** @return array{state: string, product_name: ?string, starts_at: ?string, ends_at: ?string, visibility_reason: ?string, business_timezone: string} */
    public function execute(): array
    {
        $now = $this->clock->now();
        $active = $this->resolver->temporallyActive($now);

        if ($active !== null) {
            $reason = $this->resolver->visibilityReason($active);

            return $this->summary($active, $reason === null ? 'active' : 'active_hidden', $reason);
        }

        $upcoming = $this->resolver->next($now);

        if ($upcoming !== null) {
            return $this->summary($upcoming, 'upcoming', $this->resolver->visibilityReason($upcoming));
        }

        return [
            'state' => 'none',
            'product_name' => null,
            'starts_at' => null,
            'ends_at' => null,
            'visibility_reason' => null,
            'business_timezone' => $this->clock->timezone(),
        ];
    }

    /** @return array{state: string, product_name: string, starts_at: string, ends_at: ?string, visibility_reason: ?string, business_timezone: string} */
    private function summary(FeaturedProduct $featuredProduct, string $state, ?string $reason): array
    {
        return [
            'state' => $state,
            'product_name' => $featuredProduct->product->name,
            'starts_at' => $featuredProduct->starts_at->utc()->toIso8601String(),
            'ends_at' => $featuredProduct->ends_at?->utc()->toIso8601String(),
            'visibility_reason' => $reason,
            'business_timezone' => $this->clock->timezone(),
        ];
    }
}
