<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Current estimate configuration; never a historical inventory or financial fact.
 * @property int $id
 * @property string $name
 * @property int $overhead_basis_points
 * @property bool $is_active
 */
final class CostingPolicy extends Model
{
    protected $table = 'inventory_costing_policies';

    protected $fillable = ['name', 'overhead_basis_points', 'is_active'];

    protected function casts(): array
    {
        return ['overhead_basis_points' => 'integer', 'is_active' => 'boolean'];
    }

    /** @return HasMany<CostingPolicyPresentation, $this> */
    public function presentations(): HasMany
    {
        return $this->hasMany(CostingPolicyPresentation::class, 'costing_policy_id')->orderBy('commercial_quantity');
    }
}
