<?php

namespace App\Modules\Management\Actions;

use App\Modules\Management\Models\DistributionRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class SetDistributionRuleActive
{
    public function execute(DistributionRule $rule, bool $active, int $adminId): DistributionRule
    {
        return DB::transaction(function () use ($rule, $active, $adminId): DistributionRule {
            DB::statement("SELECT pg_advisory_xact_lock(hashtext('management_distribution_active_rule'))");
            $locked = DistributionRule::query()->lockForUpdate()->findOrFail($rule->id);
            if ($active) {
                DistributionRule::query()
                    ->where('id', '<>', $locked->id)
                    ->where('active', true)
                    ->lockForUpdate()
                    ->get();
                DistributionRule::query()
                    ->where('id', '<>', $locked->id)
                    ->where('active', true)
                    ->update(['active' => false]);
            }
            $locked->update(['active' => $active]);
            Log::info('[Management] distribution.rule_status_changed', [
                'module' => 'Management',
                'distribution_rule_id' => $locked->id,
                'active' => $active,
                'admin_id' => $adminId,
            ]);

            return $locked;
        });
    }
}
