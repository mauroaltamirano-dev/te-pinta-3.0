<?php

namespace App\Modules\Management\Models;

use App\Modules\Identity\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $financial_transaction_id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 * @property string $checksum_sha256
 * @property int $uploaded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property int|null $deleted_by
 * @property string|null $deletion_reason
 */
final class ManagementAttachment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'financial_transaction_id', 'disk', 'path', 'original_name', 'mime_type',
        'size', 'checksum_sha256', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return ['size' => 'integer', 'deleted_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<FinancialTransaction, $this> */
    public function financialTransaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @return BelongsTo<User, $this> */
    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
