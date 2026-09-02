<?php

namespace App\Modules\Instagram\Models;

use Illuminate\Database\Eloquent\Model;

/** @property bool $enabled */
final class InstagramAutomationSetting extends Model
{
    protected $fillable = ['id', 'enabled'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    public static function current(): self
    {
        return self::query()->firstOrCreate(['id' => 1], ['enabled' => false]);
    }
}
