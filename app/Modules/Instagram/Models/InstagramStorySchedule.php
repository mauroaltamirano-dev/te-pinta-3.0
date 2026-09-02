<?php

namespace App\Modules\Instagram\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $asset_id
 * @property string $name
 * @property string $time
 * @property array<int, int> $days_of_week
 * @property int $sort_order
 * @property bool $is_active
 * @property-read InstagramStoryAsset|null $asset
 */
final class InstagramStorySchedule extends Model
{
    protected $fillable = ['name', 'time', 'days_of_week', 'sort_order', 'asset_id', 'is_active'];

    protected function casts(): array
    {
        return ['days_of_week' => 'array', 'sort_order' => 'integer', 'is_active' => 'boolean'];
    }

    /** @return BelongsTo<InstagramStoryAsset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(InstagramStoryAsset::class, 'asset_id');
    }

    /** @return HasMany<InstagramStoryPublication, $this> */
    public function publications(): HasMany
    {
        return $this->hasMany(InstagramStoryPublication::class, 'schedule_id');
    }
}
