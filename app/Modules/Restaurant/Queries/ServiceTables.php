<?php

namespace App\Modules\Restaurant\Queries;

use Illuminate\Support\Facades\DB;

/** Read access to dining tables for table service, without exposing floor geometry. */
final class ServiceTables
{
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

    public function code(int $tableId): string
    {
        return (string) DB::table('dining_tables')->where('id', $tableId)->value('code');
    }
}
