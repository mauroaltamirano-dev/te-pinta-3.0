<?php

namespace App\Modules\Settings\Actions;

use App\Modules\Orders\Models\PublicOrderingHour;
use Illuminate\Support\Facades\DB;

final class UpdatePublicOrderingHours
{
    /** @param  list<array{weekday: int, opens_at: ?string, closes_at: ?string}>  $hours */
    public function execute(array $hours): void
    {
        DB::transaction(function () use ($hours): void {
            foreach ($hours as $hour) {
                PublicOrderingHour::query()->updateOrCreate(
                    ['weekday' => (int) $hour['weekday']],
                    ['opens_at' => $hour['opens_at'], 'closes_at' => $hour['closes_at']],
                );
            }
        });
    }
}
