<?php

namespace App\Modules\Customers\Models;

use App\Modules\Orders\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'phone', 'phone_normalized', 'address', 'notes'];

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
