<?php

namespace App\Modules\Management\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Management\Enums\ManagementOutflowType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property ManagementOutflowType $type
 * @property int $category_id
 * @property int $wallet_id
 * @property int $financial_transaction_id
 * @property string $description
 * @property string|null $counterparty
 * @property int|numeric-string $amount
 * @property CarbonImmutable $effective_date
 * @property int $created_by
 * @property Carbon|null $created_at
 */
final class ManagementOutflow extends Model
{
    protected $fillable = [
        'type', 'category_id', 'wallet_id', 'financial_transaction_id', 'description',
        'counterparty', 'amount', 'effective_date', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => ManagementOutflowType::class,
            'amount' => 'integer',
            'effective_date' => 'immutable_date',
        ];
    }

    /** @return BelongsTo<ManagementOutflowCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ManagementOutflowCategory::class, 'category_id');
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

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
