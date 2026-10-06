<?php

namespace Tests\Feature\Restaurant;

use App\Modules\Restaurant\Models\DiningArea;
use App\Modules\Restaurant\Queries\FloorLayoutQuery;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class FloorDatabaseTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function row(int $areaId, array $changes = []): array
    {
        return array_replace(['dining_area_id' => $areaId, 'code' => '1', 'shape' => 'rectangle', 'position_x' => '0.2', 'position_y' => '0.2', 'width' => '0.1', 'height' => '0.16', 'rotation' => 0, 'is_active' => true], $changes);
    }

    #[DataProvider('invalidRows')]
    public function test_postgresql_rejects_invalid_table_configuration(array $changes): void
    {
        $area = DiningArea::query()->create(['name' => 'Test']);
        $this->expectException(QueryException::class);
        DB::transaction(function () use ($area, $changes): void {
            DB::table('dining_tables')->insert($this->row($area->id, $changes));
            DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        });
    }

    public static function invalidRows(): array
    {
        return ['shape' => [['shape' => 'polygon']], 'x negative' => [['position_x' => '-0.1']], 'y negative' => [['position_y' => '-0.1']], 'x above' => [['position_x' => '1.1']], 'width zero' => [['width' => '0']], 'height zero' => [['height' => '0']], 'width negative' => [['width' => '-0.1']], 'x sum' => [['position_x' => '0.99']], 'y sum' => [['position_y' => '0.99']], 'rotation range' => [['rotation' => 360]], 'rotation step' => [['rotation' => 10]], 'rotated footprint' => [['position_x' => '0', 'rotation' => 45]], 'capacity' => [['capacity' => -1]], 'empty code' => [['code' => '']], 'non canonical code' => [['code' => 'vip']], 'foreign key' => [['dining_area_id' => 999999]], 'ellipse' => [['shape' => 'circle', 'height' => '0.1']]];
    }

    #[DataProvider('invalidAreas')]
    public function test_postgresql_rejects_invalid_area_and_obstacle_schema(array $changes): void
    {
        $this->expectException(QueryException::class);
        DB::transaction(fn () => DB::table('dining_areas')->insert(array_replace(['name' => 'Test'], $changes)));
    }

    public static function invalidAreas(): array
    {
        return ['name' => [['name' => ' ']], 'width' => [['width' => 0]], 'height' => [['height' => 0]], 'version' => [['layout_version' => -1]], 'schema version' => [['layout_schema_version' => 2]], 'object' => [['obstacles' => '{}']], 'unknown type' => [['obstacles' => '[{"type":"svg","x":"0.2","y":"0.2","width":"0.1","height":"0.1"}]']], 'missing field' => [['obstacles' => '[{"type":"circle"}]']], 'overflow' => [['obstacles' => '[{"type":"rectangle","x":"0.99","y":"0.2","width":"0.1","height":"0.1"}]']], 'unknown key' => [['obstacles' => '[{"type":"rectangle","x":"0.2","y":"0.2","width":"0.1","height":"0.1","orders":[]}]']], 'wrong type' => [['obstacles' => '[{"type":"rectangle","x":0.2,"y":"0.2","width":"0.1","height":"0.1"}]']]];
    }

    public function test_postgresql_unique_table_code_is_deferred_and_rejects_duplicates(): void
    {
        $area = DiningArea::query()->create(['name' => 'Test']);
        $this->expectException(QueryException::class);
        DB::transaction(function () use ($area): void {
            DB::table('dining_tables')->insert([$this->row($area->id), $this->row($area->id)]);
            DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        });
    }

    public function test_area_dimension_change_cannot_leave_rotated_tables_outside(): void
    {
        $area = DiningArea::query()->create(['name' => 'Test']);
        DB::table('dining_tables')->insert($this->row($area->id, ['position_x' => '0.8', 'width' => '0.15', 'height' => '0.4', 'rotation' => 90]));
        $this->expectException(QueryException::class);
        DB::transaction(function () use ($area): void {
            DB::table('dining_areas')->where('id', $area->id)->update(['width' => 100]);
            DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        });
    }

    public function test_active_read_query_excludes_inactive_configuration_without_deleting_it(): void
    {
        $active = DiningArea::query()->create(['name' => 'Active']);
        $inactive = DiningArea::query()->create(['name' => 'Inactive', 'is_active' => false]);
        DB::table('dining_tables')->insert([$this->row($active->id), $this->row($active->id, ['code' => '2', 'is_active' => false]), $this->row($inactive->id)]);
        $query = new FloorLayoutQuery;
        $this->assertCount(1, $query->areas(true));
        $this->assertCount(1, $query->areas(true)[0]['tables']);
        $this->assertCount(2, $query->areas());
        $this->assertDatabaseCount('dining_tables', 3);
    }
}
