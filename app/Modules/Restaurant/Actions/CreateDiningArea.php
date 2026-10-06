<?php

namespace App\Modules\Restaurant\Actions;

use App\Modules\Restaurant\Models\DiningArea;

final class CreateDiningArea
{
    /** @param array<string, mixed> $data */
    public function execute(array $data): DiningArea
    {
        $area = DiningArea::query()->create($data);

        return $area->refresh();
    }
}
