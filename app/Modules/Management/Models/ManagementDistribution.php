<?php

namespace App\Modules\Management\Models;

use App\Modules\Identity\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $source_wallet_id
 * @property int|null $management_order_payment_id
 * @property int $rule_version_id
 * @property int $financial_transaction_id
 * @property int|numeric-string $amount
 * @property CarbonImmutable $effective_date
 * @property string|null $description
 * @property int $created_by
 */
final class ManagementDistribution extends Model
{
    protected $fillable = [
        'source_wallet_id', 'management_order_payment_id', 'rule_version_id', 'financial_transaction_id', 'amount', 'effective_date',
        'description', 'created_by',
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

    /** @return BelongsTo<ManagementOrderPayment, $this> */
    public function managementOrderPayment(): BelongsTo
    {
        return $this->belongsTo(ManagementOrderPayment::class);
    }

    /** @return BelongsTo<DistributionRuleVersion, $this> */
    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(DistributionRuleVersion::class, 'rule_version_id');
    }

    /** @return BelongsTo<FinancialTransaction, $this> */
    public function financialTransaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class);
    }

    /** @return HasMany<ManagementDistributionAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(ManagementDistributionAllocation::class)
            ->orderBy('sort_order')->orderBy('wallet_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
