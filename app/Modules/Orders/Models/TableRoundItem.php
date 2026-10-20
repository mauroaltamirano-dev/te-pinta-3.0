<?php

namespace App\Modules\Orders\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $table_round_id
 * @property int|null $product_id
 * @property int|null $order_addon_id
 * @property string $name
 * @property int $quantity
 */
final class TableRoundItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['product_id', 'order_addon_id', 'name', 'quantity'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }
}
