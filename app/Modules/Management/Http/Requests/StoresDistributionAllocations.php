<?php

namespace App\Modules\Management\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait StoresDistributionAllocations
{
    /** @return array<string, array<int, mixed>> */
    private function allocationRules(): array
    {
        return [
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.wallet_id' => ['required', 'integer', 'distinct', Rule::exists('wallets', 'id')->whereNull('archived_at')],
            'allocations.*.percentage_bps' => ['required', 'integer', 'min:1', 'max:10000'],
            'allocations.*.sort_order' => ['required', 'integer', 'min:0', 'max:4294967295'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allocations = $this->input('allocations');
            if (! is_array($allocations)) {
                return;
            }
            $sum = collect($allocations)->sum(fn (mixed $allocation): int => is_array($allocation) && is_numeric($allocation['percentage_bps'] ?? null)
                ? (int) $allocation['percentage_bps'] : 0);
            if ($sum !== 10000) {
                $validator->errors()->add('allocations', 'Los porcentajes deben sumar exactamente 100%.');
            }
        });
    }
}
