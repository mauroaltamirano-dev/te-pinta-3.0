<?php

namespace App\Modules\Management\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class DistributionRule extends Model
{
    protected $table = 'management_distribution_rules';

    protected $fillable = ['name', 'active', 'created_by'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    /** @return HasMany<DistributionRuleVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(DistributionRuleVersion::class, 'distribution_rule_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
