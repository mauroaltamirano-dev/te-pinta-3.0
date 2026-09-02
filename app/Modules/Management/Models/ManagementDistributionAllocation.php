<?php

namespace App\Modules\Management\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ManagementDistributionAllocation extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'management_distribution_id', 'wallet_id', 'wallet_name',
        'percentage_bps', 'amount', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['percentage_bps' => 'integer', 'amount' => 'integer', 'sort_order' => 'integer'];
    }

    /** @return BelongsTo<ManagementDistribution, $this> */
    public function distribution(): BelongsTo
    {
        return $this->belongsTo(ManagementDistribution::class, 'management_distribution_id');
    }

    /** @return BelongsTo<Wallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
