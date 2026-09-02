<?php

namespace App\Modules\Instagram\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $category
 * @property string $file_path
 * @property string $mime_type
 * @property int $width
 * @property int $height
 * @property bool $is_active
 * @property int|null $schedules_count
 * @property int|null $publications_count
 */
final class InstagramStoryAsset extends Model
{
    protected $fillable = ['name', 'category', 'file_path', 'mime_type', 'width', 'height', 'is_active'];

    protected function casts(): array
    {
        return ['width' => 'integer', 'height' => 'integer', 'is_active' => 'boolean'];
    }

    /** @return HasMany<InstagramStorySchedule, $this> */
    public function schedules(): HasMany
    {
        return $this->hasMany(InstagramStorySchedule::class, 'asset_id');
    }

    /** @return HasMany<InstagramStorySchedule, $this> */
    public function activeSchedules(): HasMany
    {
        return $this->schedules()->where('is_active', true);
    }

    /** @return HasMany<InstagramStoryPublication, $this> */
    public function publications(): HasMany
    {
        return $this->hasMany(InstagramStoryPublication::class, 'asset_id');
    }
}
