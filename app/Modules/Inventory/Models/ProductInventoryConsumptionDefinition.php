<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property int $id
 * @property string $mode
 * @property int $product_id
 * @property int|null $current_version_id
 * @property ProductInventoryConsumptionVersion|null $currentVersion */
final class ProductInventoryConsumptionDefinition extends Model
{
    protected $table = 'inventory_product_consumption_definitions';

    protected $fillable = ['product_id', 'mode', 'current_version_id', 'created_by'];

    protected function casts(): array
    {
        return ['product_id' => 'integer'];
    }

    /** @return BelongsTo<ProductInventoryConsumptionVersion, $this> */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(ProductInventoryConsumptionVersion::class, 'current_version_id');
    }

    /** @return HasMany<ProductInventoryConsumptionVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(ProductInventoryConsumptionVersion::class, 'definition_id')->orderByDesc('version');
    }
}
