<?php

namespace Tests\Support;

use App\Modules\Orders\Models\PublicOrderingHour;

trait OpensPublicOrdering
{
    /** Equal times mean all day, so public checkout tests do not depend on the clock. */
    private function openPublicOrderingAllDay(): void
    {
        PublicOrderingHour::query()->update(['opens_at' => '00:00', 'closes_at' => '00:00']);
    }
}
