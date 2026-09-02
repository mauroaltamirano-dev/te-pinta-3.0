<?php

namespace App\Modules\Instagram\Models;

use App\Modules\Instagram\Enums\InstagramStoryPublicationStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $schedule_id
 * @property int $asset_id
 * @property InstagramStoryPublicationStatus $status
 * @property int $attempts
 * @property int $queue_order
 * @property CarbonImmutable $scheduled_for
 * @property CarbonImmutable|null $processing_started_at
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $failed_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $archived_at
 * @property string|null $error_message
 * @property string|null $failure_code
 * @property bool $retryable
 * @property bool $requires_manual_review
 * @property string|null $meta_container_id
 * @property string|null $meta_media_id
 * @property-read InstagramStoryAsset $asset
 * @property-read InstagramStorySchedule|null $schedule
 */
final class InstagramStoryPublication extends Model
{
    protected $fillable = ['schedule_id', 'asset_id', 'scheduled_for', 'queue_order', 'status', 'attempts', 'processing_started_at', 'published_at', 'failed_at', 'cancelled_at', 'archived_at', 'error_message', 'failure_code', 'retryable', 'requires_manual_review', 'meta_container_id', 'meta_media_id'];

    protected function casts(): array
    {
        return [
            'status' => InstagramStoryPublicationStatus::class,
            'attempts' => 'integer',
            'queue_order' => 'integer',
            'retryable' => 'boolean',
            'requires_manual_review' => 'boolean',
            'scheduled_for' => 'immutable_datetime',
            'processing_started_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<InstagramStoryAsset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(InstagramStoryAsset::class, 'asset_id');
    }

    /** @return BelongsTo<InstagramStorySchedule, $this> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(InstagramStorySchedule::class, 'schedule_id');
    }
}
