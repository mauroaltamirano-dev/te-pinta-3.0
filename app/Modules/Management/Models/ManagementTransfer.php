<?php

namespace App\Modules\Management\Models;

use App\Modules\Identity\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $source_wallet_id
 * @property int $destination_wallet_id
 * @property int $financial_transaction_id
 * @property int|numeric-string $amount
 * @property CarbonImmutable $effective_date
 * @property string|null $description
 * @property int $created_by
 * @property Carbon|null $created_at
 */
final class ManagementTransfer extends Model
{
    protected $fillable = [
        'source_wallet_id', 'destination_wallet_id', 'financial_transaction_id',
        'amount', 'effective_date', 'description', 'created_by',
    ];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'effective_date' => 'immutable_date'];
    }

    /** @return BelongsTo<Wallet, $this> */
    public function sourceWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'source_wallet_id');
    }

    /** @return BelongsTo<Wallet, $this> */
    public function destinationWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'destination_wallet_id');
    }

    /** @return BelongsTo<FinancialTransaction, $this> */
    public function financialTransaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
