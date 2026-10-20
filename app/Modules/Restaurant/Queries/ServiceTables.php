<?php

namespace App\Modules\Restaurant\Queries;

use App\Core\Tenancy\RestaurantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

/** Read access to dining tables for table service, without exposing floor geometry. */
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
