<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Inventory\Enums\UnitFamily;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $symbol
 * @property UnitFamily $family
 * @property numeric-string $factor_to_base
 * @property bool $is_base
 * @property bool $is_system
 */
final class InventoryUnit extends Model
{
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'family' => UnitFamily::class,
            'factor_to_base' => 'decimal:9',
            'is_base' => 'boolean',
            'is_system' => 'boolean',
        ];
    }
}
