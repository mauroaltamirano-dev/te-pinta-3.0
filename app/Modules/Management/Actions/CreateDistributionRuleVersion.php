<?php

namespace App\Modules\Management\Actions;

use App\Modules\Management\Data\DistributionAllocationData;
use App\Modules\Management\Models\DistributionRule;
use App\Modules\Management\Models\DistributionRuleVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class CreateDistributionRuleVersion
{
    /** @param list<DistributionAllocationData> $allocations */
    public function execute(DistributionRule $rule, array $allocations, int $adminId): DistributionRuleVersion
    {
        return DB::transaction(function () use ($rule, $allocations, $adminId): DistributionRuleVersion {
            $lockedRule = DistributionRule::query()->lockForUpdate()->findOrFail($rule->id);
            $nextVersion = ((int) $lockedRule->versions()->max('version_number')) + 1;
            $version = $lockedRule->versions()->create([
                'version_number' => $nextVersion,
                'created_by' => $adminId,
            ]);
            $version->allocations()->createMany(collect($allocations)
                ->sortBy(fn (DistributionAllocationData $allocation): string => sprintf('%010d-%020d', $allocation->sortOrder, $allocation->walletId))
                ->values()
                ->map(fn (DistributionAllocationData $allocation): array => [
                    'wallet_id' => $allocation->walletId,
                    'percentage_bps' => $allocation->percentageBps,
                    'sort_order' => $allocation->sortOrder,
                ])->all());

            Log::info('[Management] distribution.rule_version_created', [
                'module' => 'Management',
                'distribution_rule_id' => $lockedRule->id,
                'rule_version_id' => $version->id,
                'version_number' => $nextVersion,
                'admin_id' => $adminId,
            ]);

            return $version->load('allocations.wallet', 'rule');
        });
    }
}
