<?php

namespace App\Modules\Management\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DistributionRuleAllocation extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'management_distribution_rule_allocations';

    protected $fillable = ['rule_version_id', 'wallet_id', 'percentage_bps', 'sort_order'];

    protected function casts(): array
    {
        return ['percentage_bps' => 'integer', 'sort_order' => 'integer'];
    }

    /** @return BelongsTo<DistributionRuleVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(DistributionRuleVersion::class, 'rule_version_id');
    }

    /** @return BelongsTo<Wallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
