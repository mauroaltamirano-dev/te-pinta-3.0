<?php

namespace App\Modules\Management\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property int $sort_order
 * @property bool $is_order_payment_default
 * @property CarbonImmutable|null $archived_at
 * @property int|numeric-string|null $balance
 * @property Carbon|null $created_at
 */
final class Wallet extends Model
{
    protected $fillable = ['name', 'sort_order', 'is_order_payment_default', 'archived_at'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_order_payment_default' => 'boolean', 'archived_at' => 'immutable_datetime'];
    }

    /** @return HasMany<LedgerEntry, $this> */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    /** @return HasMany<ManagementOutflow, $this> */
    public function managementOutflows(): HasMany
    {
        return $this->hasMany(ManagementOutflow::class);
    }

    /** @return HasMany<ManagementTransfer, $this> */
    public function outgoingManagementTransfers(): HasMany
    {
        return $this->hasMany(ManagementTransfer::class, 'source_wallet_id');
    }

    /** @return HasMany<ManagementTransfer, $this> */
    public function incomingManagementTransfers(): HasMany
    {
        return $this->hasMany(ManagementTransfer::class, 'destination_wallet_id');
    }

    /** @return HasMany<ManagementDistribution, $this> */
    public function sourceManagementDistributions(): HasMany
    {
        return $this->hasMany(ManagementDistribution::class, 'source_wallet_id');
    }

    /** @return HasMany<ManagementOrderPayment, $this> */
    public function managementOrderPayments(): HasMany
    {
        return $this->hasMany(ManagementOrderPayment::class, 'destination_wallet_id');
    }
}
