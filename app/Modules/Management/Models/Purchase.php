<?php

namespace App\Modules\Management\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\InventoryTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property CarbonImmutable $effective_date
 * @property Wallet $wallet
 */
final class Purchase extends Model
{
    protected $fillable = ['idempotency_key', 'request_fingerprint', 'wallet_id', 'supplier_name', 'description',
        'effective_date', 'subtotal', 'shipping_amount', 'total', 'financial_transaction_id',
        'inventory_transaction_id', 'created_by'];

    protected function casts(): array
    {
        return ['effective_date' => 'immutable_date', 'subtotal' => 'integer', 'shipping_amount' => 'integer', 'total' => 'integer'];
    }

    /** @return HasMany<PurchaseLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseLine::class);
    }

    /** @return BelongsTo<Wallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /** @return BelongsTo<FinancialTransaction, $this> */
    public function financialTransaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class);
    }

    /** @return BelongsTo<InventoryTransaction, $this> */
    public function inventoryTransaction(): BelongsTo
    {
        return $this->belongsTo(InventoryTransaction::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasOne<PurchaseReversal, $this> */
    public function reversal(): HasOne
    {
        return $this->hasOne(PurchaseReversal::class);
    }
}
