<?php

namespace App\Modules\Restaurant\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read Collection<int, DiningTable> $tables
 * @property int $id
 * @property string $name
 * @property int $width
 * @property int $height
 * @property bool $is_active
 * @property int $layout_version
 * @property int $layout_schema_version
 * @property array<int, array<string, mixed>> $obstacles
 */
final class DiningArea extends Model
{
    protected $attributes = ['width' => 1600, 'height' => 1000, 'is_active' => true, 'layout_version' => 0, 'layout_schema_version' => 1, 'obstacles' => '[]'];

    protected $fillable = ['name', 'width', 'height', 'is_active', 'obstacles', 'layout_version'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['width' => 'integer', 'height' => 'integer', 'is_active' => 'boolean', 'obstacles' => 'array', 'layout_version' => 'integer', 'layout_schema_version' => 'integer'];
    }

    /** @return HasMany<DiningTable, $this> */
    public function tables(): HasMany
    {
        return $this->hasMany(DiningTable::class);
    }
}
