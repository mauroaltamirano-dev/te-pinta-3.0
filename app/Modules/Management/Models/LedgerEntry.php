<?php

namespace App\Modules\Management\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $financial_transaction_id
 * @property int $wallet_id
 * @property int|numeric-string $amount
 */
final class LedgerEntry extends Model
{
    protected $fillable = ['financial_transaction_id', 'wallet_id', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    /** @return BelongsTo<FinancialTransaction, $this> */
    public function financialTransaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class);
    }

    /** @return BelongsTo<Wallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
