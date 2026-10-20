<?php

namespace App\Modules\Printing\Models;

use App\Core\Tenancy\RestaurantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A frozen document waiting for, or already sent to, the shop print station.
 * PostgreSQL rejects changes to its content and any delete.
 *
 * @property int $id
 * @property string $kind kitchen_ticket|bill
 * @property int $order_id
 * @property string $dedupe_key
 * @property array<string, mixed> $payload
 * @property string $status pending|claimed|printed
 * @property int $attempts
 * @property string|null $claim_token
 * @property Carbon|null $claimed_at
 * @property int|null $claimed_by
 * @property Carbon|null $printed_at
 * @property int|null $printed_by
 * @property int|null $reprint_of_id
 * @property int $requested_by
 * @property Carbon $created_at
 */
final class PrintJob extends Model
{
    protected $attributes = ['status' => 'pending', 'attempts' => 0];

    protected $fillable = ['kind', 'order_id', 'dedupe_key', 'payload', 'reprint_of_id', 'requested_by'];

    /**
     * The current restaurant's jobs. Every station read and write starts here: reads are not
     * isolated by the database yet (docs/DATABASE.md, tenancy).
     *
     * @return Builder<self>
     */
    public static function queue(): Builder
    {
        return self::query()->where('restaurant_id', app(RestaurantContext::class)->id());
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['payload' => 'array', 'attempts' => 'integer', 'claimed_at' => 'datetime', 'printed_at' => 'datetime'];
    }
}
