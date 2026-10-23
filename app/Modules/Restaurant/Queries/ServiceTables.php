<?php

namespace App\Modules\Restaurant\Queries;

use App\Core\Tenancy\RestaurantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

/** Read access to dining tables for table service; floor geometry is exposed read-only, for drawing the board. */
final readonly class ServiceTables
{
    public function __construct(private RestaurantContext $restaurant) {}

    /**
     * Tables a waiter can pick, in natural code order: active tables of active areas, plus any table
     * kept by an open session after it was deactivated.
     *
     * @param  list<int>  $keepIds
     * @return list<array{id: int, code: string, area: string, available: bool}>
     */
    public function listed(array $keepIds): array
    {
        $tables = $this->select()
            ->where(fn ($query) => $query->where(fn ($active) => $active->where('t.is_active', true)->where('a.is_active', true))->orWhereIn('t.id', $keepIds))
            ->get()->map(fn (stdClass $row): array => $this->row($row))->all();
        usort($tables, fn (array $a, array $b): int => strnatcasecmp($a['code'], $b['code']) ?: $a['id'] <=> $b['id']);

        return $tables;
    }

    /**
     * The floor of the given tables, to draw them as the editor placed them: their areas (by id, with
     * obstacles) and only those tables' geometry. Coordinates are fractions of the area, as stored.
     *
     * @param  list<int>  $tableIds
     * @return list<array{id: int, name: string, width: int, height: int, obstacles: list<array<string, mixed>>, tables: list<array{id: int, shape: string, x: string, y: string, width: string, height: string, rotation: int}>}>
     */
    public function floor(array $tableIds): array
    {
        $tables = DB::table('dining_tables')->where('restaurant_id', $this->restaurant->id())->whereIn('id', $tableIds)->orderBy('id')
            ->get(['id', 'dining_area_id', 'shape', 'position_x', 'position_y', 'width', 'height', 'rotation'])->groupBy('dining_area_id');

        return array_values(DB::table('dining_areas')->where('restaurant_id', $this->restaurant->id())->whereIn('id', $tables->keys()->all())->orderBy('id')
            ->get(['id', 'name', 'width', 'height', 'obstacles'])
            ->map(fn (stdClass $area): array => [
                'id' => (int) $area->id, 'name' => (string) $area->name, 'width' => (int) $area->width, 'height' => (int) $area->height,
                'obstacles' => array_values(array_map(
                    fn (array $obstacle): array => array_intersect_key($obstacle, array_flip(['type', 'x', 'y', 'width', 'height', 'label'])),
                    json_decode((string) $area->obstacles, true, flags: JSON_THROW_ON_ERROR),
                )),
                'tables' => array_values($tables->get($area->id)->map(fn (stdClass $table): array => [
                    'id' => (int) $table->id, 'shape' => (string) $table->shape, 'x' => (string) $table->position_x, 'y' => (string) $table->position_y,
                    'width' => (string) $table->width, 'height' => (string) $table->height, 'rotation' => (int) $table->rotation,
                ])->all()),
            ])->all());
    }

    /** @return array{id: int, code: string, area: string, available: bool}|null */
    public function find(int $tableId): ?array
    {
        $row = $this->select()->where('t.id', $tableId)->first();

        return $row === null ? null : $this->row($row);
    }

    /**
     * Lock one table for a table-service decision.
     *
     * @return array{id: int, code: string, available: bool}|null available = table and area are active
     */
    public function lock(int $tableId): ?array
    {
        $row = DB::table('dining_tables as t')
            ->join('dining_areas as a', 'a.id', '=', 't.dining_area_id')
            ->where('t.id', $tableId)
            ->lockForUpdate()
            ->first(['t.id', 't.code', 't.is_active', 'a.is_active as area_active']);

        return $row === null ? null : ['id' => (int) $row->id, 'code' => (string) $row->code, 'available' => (bool) $row->is_active && (bool) $row->area_active];
    }

    private function select(): Builder
    {
        return DB::table('dining_tables as t')
            ->join('dining_areas as a', 'a.id', '=', 't.dining_area_id')
            ->where('t.restaurant_id', $this->restaurant->id())
            ->select(['t.id', 't.code', 't.is_active', 'a.name as area', 'a.is_active as area_active']);
    }

    /** @return array{id: int, code: string, area: string, available: bool} */
    private function row(stdClass $row): array
    {
        return ['id' => (int) $row->id, 'code' => (string) $row->code, 'area' => (string) $row->area, 'available' => (bool) $row->is_active && (bool) $row->area_active];
    }

    public function code(int $tableId): string
    {
        return (string) DB::table('dining_tables')->where('id', $tableId)->value('code');
    }
}
