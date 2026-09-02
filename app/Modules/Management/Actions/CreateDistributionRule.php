<?php

namespace App\Modules\Management\Actions;

use App\Modules\Management\Data\DistributionRuleData;
use App\Modules\Management\Models\DistributionRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class CreateDistributionRule
{
    public function execute(DistributionRuleData $data, int $adminId): DistributionRule
    {
        return DB::transaction(function () use ($data, $adminId): DistributionRule {
            DB::statement("SELECT pg_advisory_xact_lock(hashtext('management_distribution_active_rule'))");
            DistributionRule::query()->where('active', true)->lockForUpdate()->get();
            DistributionRule::query()->where('active', true)->update(['active' => false]);

            $rule = DistributionRule::query()->create([
                'name' => $data->name,
                'active' => true,
                'created_by' => $adminId,
            ]);
            $version = $rule->versions()->create(['version_number' => 1, 'created_by' => $adminId]);
            $version->allocations()->createMany(collect($data->allocations)
                ->sortBy(fn ($allocation): string => sprintf('%010d-%020d', $allocation->sortOrder, $allocation->walletId))
                ->values()
                ->map(fn ($allocation): array => [
                    'wallet_id' => $allocation->walletId,
                    'percentage_bps' => $allocation->percentageBps,
                    'sort_order' => $allocation->sortOrder,
                ])->all());

            Log::info('[Management] distribution.rule_created', [
                'module' => 'Management',
                'distribution_rule_id' => $rule->id,
                'rule_version_id' => $version->id,
                'admin_id' => $adminId,
            ]);

            return $rule->load('versions.allocations.wallet');
        });
    }
}
