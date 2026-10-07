<?php

namespace App\Modules\Management\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\InventoryTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PurchaseReversal extends Model
{
    protected $fillable = ['purchase_id', 'financial_transaction_id', 'inventory_transaction_id', 'description', 'effective_date', 'created_by'];

    protected function casts(): array
    {
        return ['effective_date' => 'immutable_date'];
    }

    /** @return BelongsTo<Purchase, $this> */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
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
}
