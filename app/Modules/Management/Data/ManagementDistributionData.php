<?php

namespace App\Modules\Management\Data;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final readonly class ManagementDistributionData
{
    public function __construct(
        public int $sourceWalletId,
        public int $ruleVersionId,
        public int $amount,
        public CarbonImmutable $effectiveDate,
        public ?string $description,
        public ?int $managementOrderPaymentId = null,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        $description = trim($request->string('description')->toString());

        return new self(
            $request->integer('source_wallet_id'),
            $request->integer('rule_version_id'),
            $request->integer('amount'),
            CarbonImmutable::parse($request->validated('effective_date'))->startOfDay(),
            $description === '' ? null : $description,
            null,
        );
    }
}
