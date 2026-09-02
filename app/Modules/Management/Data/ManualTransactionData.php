<?php

namespace App\Modules\Management\Data;

use App\Modules\Management\Enums\FinancialTransactionType;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final readonly class ManualTransactionData
{
    public function __construct(
        public int $walletId,
        public FinancialTransactionType $type,
        public int $amount,
        public CarbonImmutable $effectiveDate,
        public ?string $description,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        $description = trim($request->string('description')->toString());

        return new self(
            walletId: $request->integer('wallet_id'),
            type: FinancialTransactionType::from($request->validated('type')),
            amount: $request->integer('amount'),
            effectiveDate: CarbonImmutable::parse($request->validated('effective_date'))->startOfDay(),
            description: $description === '' ? null : $description,
        );
    }
}
