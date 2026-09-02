<?php

namespace App\Modules\Marketing\Data;

use App\Modules\Marketing\Support\MarketingClock;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final readonly class FeaturedProductData
{
    public function __construct(
        public int $productId,
        public ?string $headline,
        public ?string $shortCopy,
        public CarbonImmutable $startsAt,
        public ?CarbonImmutable $endsAt,
        public bool $isEnabled,
    ) {}

    public static function fromRequest(FormRequest $request, MarketingClock $clock): self
    {
        $headline = $request->validated('headline');
        $shortCopy = $request->validated('short_copy');
        $endsAt = $request->validated('ends_at');

        return new self(
            productId: (int) $request->validated('product_id'),
            headline: is_string($headline) && $headline !== '' ? $headline : null,
            shortCopy: is_string($shortCopy) && $shortCopy !== '' ? $shortCopy : null,
            startsAt: $clock->parseLocal($request->validated('starts_at')),
            endsAt: is_string($endsAt) && $endsAt !== '' ? $clock->parseLocal($endsAt) : null,
            isEnabled: $request->boolean('is_enabled'),
        );
    }

    /** @return array{product_id: int, headline: ?string, short_copy: ?string, starts_at: CarbonImmutable, ends_at: ?CarbonImmutable, is_enabled: bool} */
    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'headline' => $this->headline,
            'short_copy' => $this->shortCopy,
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt,
            'is_enabled' => $this->isEnabled,
        ];
    }
}
