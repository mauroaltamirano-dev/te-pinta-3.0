<?php

namespace App\Modules\Orders\Models;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\TableRoundKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Immutable record of what one confirmation sent to the kitchen (PostgreSQL rejects updates and deletes).
 *
 * @property int $id
 * @property int $table_session_id
 * @property int $sequence
 * @property TableRoundKind $kind
 * @property string|null $note
 * @property int $confirmed_by
 * @property Role $confirmed_by_role
 * @property Carbon $confirmed_at
 * @property string $idempotency_hash
 * @property string $fingerprint
 */
final class TableRound extends Model
{
    public $timestamps = false;

    protected $fillable = ['table_session_id', 'sequence', 'kind', 'note', 'confirmed_by', 'confirmed_by_role', 'confirmed_at', 'idempotency_hash', 'fingerprint'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['sequence' => 'integer', 'kind' => TableRoundKind::class, 'confirmed_by_role' => Role::class, 'confirmed_at' => 'datetime'];
    }

    /** @return BelongsTo<TableSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(TableSession::class, 'table_session_id');
    }

    /** @return BelongsTo<User, $this> */
    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /** @return HasMany<TableRoundItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(TableRoundItem::class)->orderBy('id');
    }
}
