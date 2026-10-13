<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property int $id
 * @property int $version
 * @property bool $is_active
 * @property bool $is_sealed */
final class ProductInventoryConsumptionVersion extends Model
{
    protected $table = 'inventory_product_consumption_versions';

    protected $fillable = ['definition_id', 'version', 'product_name_snapshot', 'is_active', 'is_sealed', 'created_by'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'is_active' => 'boolean', 'is_sealed' => 'boolean'];
    }

    /** @return HasMany<ProductInventoryConsumptionComponent, $this> */
    public function components(): HasMany
    {
        return $this->hasMany(ProductInventoryConsumptionComponent::class, 'version_id')->orderBy('id');
    }
}
