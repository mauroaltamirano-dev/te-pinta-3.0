<?php

namespace App\Modules\Management\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $order_id
 * @property int $destination_wallet_id
 * @property int $financial_transaction_id
 * @property int|numeric-string $amount
 * @property CarbonImmutable $effective_date
 * @property int $created_by
 */
final class ManagementOrderPayment extends Model
{
    protected $fillable = [
        'order_id', 'destination_wallet_id', 'financial_transaction_id', 'amount', 'effective_date', 'created_by',
    ];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'effective_date' => 'immutable_date'];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withTrashed();
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

    /** @return HasOne<ManagementDistribution, $this> */
    public function distribution(): HasOne
    {
        return $this->hasOne(ManagementDistribution::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
