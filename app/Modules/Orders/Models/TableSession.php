<?php

namespace App\Modules\Orders\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\TableSessionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $dining_table_id
 * @property int $order_id
 * @property int $waiter_id
 * @property TableSessionStatus $status
 * @property Carbon $opened_at
 * @property Carbon|null $bill_requested_at
 * @property int|null $bill_requested_by
 * @property Carbon|null $closed_at
 * @property int|null $closed_by
 */
final class TableSession extends Model
{
    protected $attributes = ['status' => 'open'];

    protected $fillable = ['dining_table_id', 'order_id', 'waiter_id', 'status', 'opened_at', 'bill_requested_at', 'bill_requested_by', 'closed_at', 'closed_by'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => TableSessionStatus::class, 'opened_at' => 'datetime', 'bill_requested_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function waiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waiter_id');
    }

    /** @return HasMany<TableRound, $this> */
    public function rounds(): HasMany
    {
        return $this->hasMany(TableRound::class)->orderBy('sequence');
    }
}
