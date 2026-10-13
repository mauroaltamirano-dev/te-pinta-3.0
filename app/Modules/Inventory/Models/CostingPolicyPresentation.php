<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property int $id
 * @property int $commercial_quantity
 * @property string $packaging_mode
 */
final class CostingPolicyPresentation extends Model
{
    protected $table = 'inventory_costing_policy_presentations';

    protected $fillable = ['commercial_quantity', 'packaging_mode'];

    protected function casts(): array
    {
        return ['commercial_quantity' => 'integer'];
    }

    /** @return HasMany<CostingPolicyPackagingComponent, $this> */
    public function components(): HasMany
    {
        return $this->hasMany(CostingPolicyPackagingComponent::class, 'presentation_id')->orderBy('id');
    }
}
