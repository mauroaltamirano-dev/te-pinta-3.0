<?php

namespace App\Modules\Restaurant\Queries;

use App\Modules\Restaurant\Models\DiningArea;
use App\Modules\Restaurant\Models\DiningTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

final class FloorLayoutQuery
{
    /** @return array<int, array<string, mixed>> */
    public function areas(bool $activeOnly = false): array
    {
        // Writers lock the same area first: hold shared locks through serialization.
        return DB::transaction(fn (): array => $this->query($activeOnly)->get()->map(fn (DiningArea $area): array => $this->serialize($area))->all());
    }

    /** @return array<string, mixed> */
    public function area(DiningArea $area, bool $activeOnly = false): array
    {
        return DB::transaction(fn (): array => $this->serialize($this->query($activeOnly)->findOrFail($area->id)));
    }

    /** @return Builder<DiningArea> */
    private function query(bool $activeOnly): Builder
    {
        return DiningArea::query()->when($activeOnly, fn (Builder $query) => $query->where('is_active', true))->orderBy('id')->sharedLock()->with(['tables' => fn (Relation $query) => $query->when($activeOnly, fn (Builder $query) => $query->where('is_active', true))->orderBy('id')]);
    }

    /** @return array<string, mixed> */
    private function serialize(DiningArea $area): array
    {
        return [
            'id' => $area->id, 'name' => $area->name, 'width' => $area->width, 'height' => $area->height, 'is_active' => $area->is_active, 'layout_version' => $area->layout_version, 'layout_schema_version' => $area->layout_schema_version, 'obstacles' => $area->obstacles,
            'tables' => $area->tables->map(fn (DiningTable $table): array => [
                'id' => $table->id, 'code' => $table->code, 'name' => $table->name, 'shape' => $table->shape, 'position_x' => $table->position_x, 'position_y' => $table->position_y, 'width' => $table->width, 'height' => $table->height, 'rotation' => $table->rotation, 'capacity' => $table->capacity, 'is_active' => $table->is_active,
            ])->all(),
        ];
    }
}
