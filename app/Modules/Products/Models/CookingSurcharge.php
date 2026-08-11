<?php

namespace App\Modules\Products\Models;

use App\Modules\Products\Enums\PriceTier;
use Illuminate\Database\Eloquent\Model;

final class CookingSurcharge extends Model
{
    protected $fillable = ['pricing_mode', 'amount'];

    protected function casts(): array
    {
        return [
            'pricing_mode' => PriceTier::class,
            'amount' => 'integer',
        ];
    }
}
