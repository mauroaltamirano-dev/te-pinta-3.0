<?php

namespace App\Modules\Management\Data;

use App\Modules\Management\Enums\ManagementOutflowType;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final readonly class ManagementOutflowData
{
    public function __construct(
        public ManagementOutflowType $type,
        public int $categoryId,
        public int $walletId,
        public string $description,
        public ?string $counterparty,
        public int $amount,
        public CarbonImmutable $effectiveDate,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        $counterparty = trim($request->string('counterparty')->toString());

        return new self(
            type: ManagementOutflowType::from($request->validated('type')),
            categoryId: $request->integer('category_id'),
            walletId: $request->integer('wallet_id'),
            description: trim($request->string('description')->toString()),
            counterparty: $counterparty === '' ? null : $counterparty,
            amount: $request->integer('amount'),
            effectiveDate: CarbonImmutable::parse($request->validated('effective_date'))->startOfDay(),
        );
    }
}
