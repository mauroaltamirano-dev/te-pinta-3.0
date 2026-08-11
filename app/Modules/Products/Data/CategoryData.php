<?php

namespace App\Modules\Products\Data;

use Illuminate\Foundation\Http\FormRequest;

final readonly class CategoryData
{
    public function __construct(
        public string $name,
        public int $sortOrder,
        public bool $isActive,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        $validated = $request->safe();

        return new self(
            name: $validated->string('name')->toString(),
            sortOrder: $validated->integer('sort_order'),
            isActive: $validated->boolean('is_active'),
        );
    }
}
