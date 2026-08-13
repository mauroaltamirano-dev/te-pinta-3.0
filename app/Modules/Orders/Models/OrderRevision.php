<?php

namespace App\Modules\Orders\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OrderRevision extends Model
{
    public $timestamps = false;

    protected $fillable = ['order_id', 'admin_id', 'order_status', 'total_before', 'total_after', 'changed_fields', 'reason', 'created_at'];

    protected function casts(): array
    {
        return ['changed_fields' => 'array', 'total_before' => 'integer', 'total_after' => 'integer', 'created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
