<?php

namespace App\Modules\Management\Models;

use App\Modules\Management\Enums\ManagementOutflowType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property ManagementOutflowType $type
 * @property string $code
 * @property string $name
 * @property bool $is_active
 * @property int $sort_order
 */
final class ManagementOutflowCategory extends Model
{
    protected $fillable = ['type', 'code', 'name', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['type' => ManagementOutflowType::class, 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    /** @return HasMany<ManagementOutflow, $this> */
    public function outflows(): HasMany
    {
        return $this->hasMany(ManagementOutflow::class, 'category_id');
    }
}
