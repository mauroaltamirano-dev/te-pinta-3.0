<?php

namespace App\Modules\Products\Data;

use App\Modules\Products\Enums\ProductPricingFamily;
use Illuminate\Foundation\Http\FormRequest;

final readonly class ProductData
{
    /** @param array<int, int> $tagIds */
    public function __construct(
        public string $name,
        public ?string $slug,
        public ?string $description,
        public ?string $longDescription,
        public ?string $publicIngredients,
        public int $categoryId,
        public array $tagIds,
        public ProductPricingFamily $pricingFamily,
        public int $unitPrice,
        public int $halfDozenPrice,
        public int $dozenPrice,
        public bool $isAvailable,
        public int $sortOrder,
        public bool $removeImage,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        $validated = $request->safe();
        $description = $validated['description'] ?? null;
        $longDescription = $validated['long_description'] ?? null;
        $publicIngredients = $validated['public_ingredients'] ?? null;
        $pricingFamily = ProductPricingFamily::from($validated->string('pricing_family')->toString());
        $unitPrice = $validated->integer('unit_price');

        return new self(
            name: $validated->string('name')->toString(),
            slug: $validated['slug'] ?? null,
            description: is_string($description) && $description !== '' ? $description : null,
            longDescription: is_string($longDescription) && $longDescription !== '' ? $longDescription : null,
            publicIngredients: is_string($publicIngredients) && $publicIngredients !== '' ? $publicIngredients : null,
            categoryId: $validated->integer('category_id'),
            tagIds: $validated->collect('tags')->map(fn (mixed $id): int => (int) $id)->all(),
            pricingFamily: $pricingFamily,
            unitPrice: $unitPrice,
            halfDozenPrice: $pricingFamily === ProductPricingFamily::Unit ? $unitPrice : $validated->integer('half_dozen_price'),
            dozenPrice: $pricingFamily === ProductPricingFamily::Unit ? $unitPrice : $validated->integer('dozen_price'),
            isAvailable: $validated->boolean('is_available'),
            sortOrder: $validated->integer('sort_order'),
            removeImage: $validated->boolean('remove_image'),
        );
    }
}
