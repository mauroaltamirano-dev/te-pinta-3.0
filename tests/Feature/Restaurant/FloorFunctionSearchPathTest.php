<?php

namespace Tests\Feature\Restaurant;

use App\Modules\Restaurant\Models\DiningArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class FloorFunctionSearchPathTest extends TestCase
{
    use RefreshDatabase;

    public function test_floor_functions_pin_their_search_path(): void
    {
        $config = DB::table('pg_proc')
            ->whereIn('proname', ['restaurant_geometry_valid', 'restaurant_obstacles_valid', 'restaurant_validate_final_table_bounds'])
            ->orderBy('proname')
            ->pluck('proconfig', 'proname')
            ->all();

        $this->assertSame([
            'restaurant_geometry_valid' => '{search_path=public}',
            'restaurant_obstacles_valid' => '{search_path=public}',
            'restaurant_validate_final_table_bounds' => '{search_path=public}',
        ], $config);
    }

    public function test_the_obstacles_check_passes_with_the_empty_search_path_pg_restore_uses(): void
    {
        $area = DiningArea::query()->create(['name' => 'Vereda', 'width' => 1600, 'height' => 1000, 'is_active' => true]);

        $searchPath = DB::selectOne('SHOW search_path')->search_path;
        // pg_restore sets an empty search_path before COPY; the check must still resolve its helpers.
        DB::statement("SELECT set_config('search_path', '', false)");
        try {
            // A savepoint keeps the real error visible: a failure here must not abort the test transaction.
            DB::transaction(fn () => DB::statement(<<<'SQL'
                UPDATE public.dining_areas
                SET obstacles = '[{"type":"rectangle","x":"0.1","y":"0.1","width":"0.2","height":"0.2","label":"Barra"}]'::jsonb
                WHERE id = ?
                SQL, [$area->id]));
        } finally {
            DB::statement('SELECT set_config(?, ?, false)', ['search_path', $searchPath]);
        }

        $this->assertSame('Barra', $area->refresh()->obstacles[0]['label']);
    }
}
