<?php

namespace App\Modules\Management\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class DistributionRuleVersion extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'management_distribution_rule_versions';

    protected $fillable = ['distribution_rule_id', 'version_number', 'created_by'];

    /** @return BelongsTo<DistributionRule, $this> */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(DistributionRule::class, 'distribution_rule_id');
    }

    /** @return HasMany<DistributionRuleAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(DistributionRuleAllocation::class, 'rule_version_id')
            ->orderBy('sort_order')->orderBy('wallet_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
