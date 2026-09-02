<?php

namespace App\Modules\Management\Data;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final readonly class ReversalData
{
    public function __construct(public CarbonImmutable $effectiveDate, public ?string $description) {}

    public static function fromRequest(FormRequest $request): self
    {
        $description = trim($request->string('description')->toString());

        return new self(
            CarbonImmutable::parse($request->validated('effective_date'))->startOfDay(),
            $description === '' ? null : $description,
        );
    }
}
