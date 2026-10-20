<?php

namespace App\Modules\Printing\Models;

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

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['payload' => 'array', 'attempts' => 'integer', 'claimed_at' => 'datetime', 'printed_at' => 'datetime'];
    }
}
