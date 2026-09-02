<?php

namespace App\Modules\Management\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Management\Enums\FinancialTransactionType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property FinancialTransactionType $type
 * @property string|null $description
 * @property CarbonImmutable $effective_date
 * @property int $created_by
 * @property int|null $reversal_of_id
 * @property Carbon|null $created_at
 */
final class FinancialTransaction extends Model
{
    protected $fillable = ['type', 'description', 'effective_date', 'created_by', 'reversal_of_id'];

    protected function casts(): array
    {
        return ['type' => FinancialTransactionType::class, 'effective_date' => 'immutable_date'];
    }

    /** @return HasMany<LedgerEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    /** @return HasMany<ManagementAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(ManagementAttachment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<FinancialTransaction, $this> */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    /** @return HasOne<FinancialTransaction, $this> */
    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reversal_of_id');
    }

    /** @return HasOne<ManagementOutflow, $this> */
    public function managementOutflow(): HasOne
    {
        return $this->hasOne(ManagementOutflow::class);
    }

    /** @return HasOne<ManagementTransfer, $this> */
    public function managementTransfer(): HasOne
    {
        return $this->hasOne(ManagementTransfer::class);
    }

    /** @return HasOne<ManagementDistribution, $this> */
    public function managementDistribution(): HasOne
    {
        return $this->hasOne(ManagementDistribution::class);
    }

    /** @return HasOne<ManagementOrderPayment, $this> */
    public function managementOrderPayment(): HasOne
    {
        return $this->hasOne(ManagementOrderPayment::class);
    }
}
