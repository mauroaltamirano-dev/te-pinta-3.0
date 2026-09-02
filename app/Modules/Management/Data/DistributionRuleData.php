<?php

namespace App\Modules\Management\Data;

use Illuminate\Foundation\Http\FormRequest;

final readonly class DistributionRuleData
{
    /** @param list<DistributionAllocationData> $allocations */
    public function __construct(public string $name, public array $allocations) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(trim($request->string('name')->toString()), self::allocationsFromRequest($request));
    }

    /** @return list<DistributionAllocationData> */
    public static function allocationsFromRequest(FormRequest $request): array
    {
        $result = [];
        foreach ($request->safe()->array('allocations') as $allocation) {
            if (! is_array($allocation)) {
                continue;
            }
            $result[] = new DistributionAllocationData(
                (int) $allocation['wallet_id'],
                (int) $allocation['percentage_bps'],
                (int) $allocation['sort_order'],
            );
        }

        return $result;
    }
}
