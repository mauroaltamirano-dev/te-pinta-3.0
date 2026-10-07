<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $inventory_item_id
 * @property string $name
 * @property numeric-string $content_quantity
 * @property int $content_unit_id
 * @property bool $is_active
 */
final class PurchasePresentation extends Model
{
    protected $table = 'inventory_purchase_presentations';

    protected $fillable = ['inventory_item_id', 'name', 'content_quantity', 'content_unit_id', 'is_active'];

    protected function casts(): array
    {
        return ['content_quantity' => 'decimal:6', 'is_active' => 'boolean'];
    }

    /** @return BelongsTo<InventoryItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    /** @return BelongsTo<InventoryUnit, $this> */
    public function contentUnit(): BelongsTo
    {
        return $this->belongsTo(InventoryUnit::class, 'content_unit_id');
    }

    /** @return BelongsTo<InventoryUnit, $this> */
    public function unit(): BelongsTo
    {
        return $this->contentUnit();
    }
}
