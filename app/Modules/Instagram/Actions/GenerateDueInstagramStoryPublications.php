<?php

namespace App\Modules\Instagram\Actions;

use App\Modules\Instagram\Enums\InstagramStoryPublicationStatus;
use App\Modules\Instagram\Models\InstagramAutomationSetting;
use App\Modules\Instagram\Models\InstagramStoryPublication;
use App\Modules\Instagram\Models\InstagramStorySchedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;

final class GenerateDueInstagramStoryPublications
{
    public function execute(?CarbonImmutable $now = null): int
    {
        if (! config('instagram.automation_enabled') || ! InstagramAutomationSetting::current()->enabled) {
            return 0;
        }

        $timezone = (string) config('instagram.timezone');
        $localNow = ($now ?? CarbonImmutable::now())->setTimezone($timezone)->startOfMinute();
        $created = 0;

        InstagramStorySchedule::query()
            ->where('is_active', true)
            ->whereNotNull('asset_id')
            ->whereHas('asset', fn ($query) => $query->where('is_active', true))
            ->orderBy('time')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->each(function (InstagramStorySchedule $schedule) use ($localNow, &$created): void {
                $days = array_map('intval', $schedule->days_of_week);
                if (! in_array($localNow->dayOfWeekIso, $days, true)) {
                    return;
                }

                $scheduledLocal = CarbonImmutable::parse($localNow->toDateString().' '.$schedule->time, $localNow->timezone)->startOfMinute();
                if (! $scheduledLocal->equalTo($localNow)) {
                    return;
                }

                try {
                    $publication = InstagramStoryPublication::query()->firstOrCreate(
                        ['schedule_id' => $schedule->id, 'scheduled_for' => $scheduledLocal->utc()],
                        ['asset_id' => $schedule->asset_id, 'queue_order' => $schedule->sort_order, 'status' => InstagramStoryPublicationStatus::Pending],
                    );
                    $created += $publication->wasRecentlyCreated ? 1 : 0;
                } catch (QueryException $exception) {
                    if (! str_contains((string) $exception->getCode(), '23505')) {
                        throw $exception;
                    }
                }
            });

        return $created;
    }
}
