<?php

namespace App\Modules\Products\Data;

use Illuminate\Foundation\Http\FormRequest;

final readonly class ProductData
{
    /** @param array<int, int> $tagIds */
    public function __construct(
        public string $name,
        public ?string $description,
        public int $categoryId,
        public array $tagIds,
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

        return new self(
            name: $validated->string('name')->toString(),
            description: is_string($description) && $description !== '' ? $description : null,
            categoryId: $validated->integer('category_id'),
            tagIds: $validated->collect('tags')->map(fn (mixed $id): int => (int) $id)->all(),
            unitPrice: $validated->integer('unit_price'),
            halfDozenPrice: $validated->integer('half_dozen_price'),
            dozenPrice: $validated->integer('dozen_price'),
            isAvailable: $validated->boolean('is_available'),
            sortOrder: $validated->integer('sort_order'),
            removeImage: $validated->boolean('remove_image'),
        );
    }
}
