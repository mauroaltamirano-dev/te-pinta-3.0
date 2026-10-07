<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Enums\InventorySourceType;
use App\Modules\Inventory\Enums\InventoryTransactionType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property InventoryTransactionType $type
 * @property InventorySourceType|null $source_type
 * @property string|null $source_id
 * @property string|null $description
 * @property int $created_by
 * @property int|null $reversal_of_id
 * @property CarbonImmutable $effective_date
 */
final class InventoryTransaction extends Model
{
    protected $fillable = [
        'type', 'effective_date', 'source_type', 'source_id', 'description', 'created_by', 'reversal_of_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => InventoryTransactionType::class,
            'source_type' => InventorySourceType::class,
            'effective_date' => 'immutable_date',
        ];
    }

    /** @return HasMany<StockMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /** @return HasMany<InventoryCostLayer, $this> */
    public function costLayers(): HasMany
    {
        return $this->hasMany(InventoryCostLayer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<InventoryTransaction, $this> */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    /** @return HasOne<InventoryTransaction, $this> */
    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reversal_of_id');
    }
}
