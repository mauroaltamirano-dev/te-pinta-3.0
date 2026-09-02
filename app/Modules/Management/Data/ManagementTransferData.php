<?php

namespace App\Modules\Management\Data;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final readonly class ManagementTransferData
{
    public function __construct(
        public int $sourceWalletId,
        public int $destinationWalletId,
        public int $amount,
        public CarbonImmutable $effectiveDate,
        public ?string $description,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        $description = trim($request->string('description')->toString());

        return new self(
            sourceWalletId: $request->integer('source_wallet_id'),
            destinationWalletId: $request->integer('destination_wallet_id'),
            amount: $request->integer('amount'),
            effectiveDate: CarbonImmutable::parse($request->validated('effective_date'))->startOfDay(),
            description: $description === '' ? null : $description,
        );
    }
}
