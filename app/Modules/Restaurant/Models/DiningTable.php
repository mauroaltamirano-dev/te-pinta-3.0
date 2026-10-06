<?php

namespace App\Modules\Restaurant\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $dining_area_id
 * @property string $code
 * @property string|null $name
 * @property string $shape
 * @property string $position_x
 * @property string $position_y
 * @property string $width
 * @property string $height
 * @property int $rotation
 * @property int|null $capacity
 * @property bool $is_active
 */
final class DiningTable extends Model
{
    protected $fillable = ['code', 'name', 'shape', 'position_x', 'position_y', 'width', 'height', 'rotation', 'capacity', 'is_active'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['position_x' => 'decimal:4', 'position_y' => 'decimal:4', 'width' => 'decimal:4', 'height' => 'decimal:4', 'rotation' => 'integer', 'capacity' => 'integer', 'is_active' => 'boolean'];
    }

    /** @return BelongsTo<DiningArea, $this> */
    public function area(): BelongsTo
    {
        return $this->belongsTo(DiningArea::class, 'dining_area_id');
    }
}
