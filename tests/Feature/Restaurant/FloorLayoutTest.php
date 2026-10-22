<?php

namespace Tests\Feature\Restaurant;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Restaurant\Models\DiningArea;
use App\Modules\Restaurant\Models\DiningTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class FloorLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function admin(): User
    {
        return User::factory()->administrator()->create();
    }

    private function area(): DiningArea
    {
        return DiningArea::query()->create(['name' => 'Test dining room', 'width' => 1600, 'height' => 1000]);
    }

    /** @return array<string, mixed> */
    private function table(array $changes = []): array
    {
        return array_replace(['code' => '1', 'name' => null, 'shape' => 'rectangle', 'position_x' => '0.2000', 'position_y' => '0.2000', 'width' => '0.1000', 'height' => '0.1600', 'rotation' => 0, 'capacity' => null, 'is_active' => true], $changes);
    }

    /** @return array<string, mixed> */
    private function layout(DiningArea $area, array $tables = [], array $changes = []): array
    {
        return array_replace(['name' => $area->name, 'width' => $area->width, 'height' => $area->height, 'is_active' => true, 'expected_version' => $area->layout_version, 'obstacles' => [], 'tables' => $tables], $changes);
    }

    public function test_create_area_and_read_empty_floor(): void
    {
        $this->actingAs($this->admin())->postJson('/admin/restaurant/areas', ['name' => 'Patio', 'width' => 1600, 'height' => 1000, 'is_active' => true])->assertCreated()->assertJsonPath('area.name', 'Patio')->assertJsonPath('area.layout_version', 0)->assertJsonPath('area.tables', []);
        $this->get('/admin/restaurant/floor')->assertOk();
        $this->assertDatabaseCount('dining_areas', 1);
        $this->assertDatabaseCount('dining_tables', 0);
    }

    public function test_area_name_is_required(): void
    {
        $this->actingAs($this->admin())->postJson('/admin/restaurant/areas', ['name' => '', 'width' => 1600, 'height' => 1000, 'is_active' => true])->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_area_can_be_deactivated_and_reactivated(): void
    {
        $area = $this->area();
        $this->actingAs($this->admin())->putJson("/admin/restaurant/areas/{$area->id}/layout", $this->layout($area, changes: ['is_active' => false]))->assertOk()->assertJsonPath('area.is_active', false)->assertJsonPath('area.layout_version', 1);
        $this->putJson("/admin/restaurant/areas/{$area->id}/layout", $this->layout($area->fresh()))->assertOk()->assertJsonPath('area.is_active', true)->assertJsonPath('area.layout_version', 2);
    }

    public function test_tables_have_stable_identity_and_exact_decimal_coordinates(): void
    {
        $area = $this->area();
        $response = $this->actingAs($this->admin())->putJson("/admin/restaurant/areas/{$area->id}/layout", $this->layout($area, [$this->table()]))->assertOk()->assertJsonPath('area.tables.0.position_x', '0.2000');
        $id = $response->json('area.tables.0.id');
        $this->putJson("/admin/restaurant/areas/{$area->id}/layout", $this->layout($area->fresh(), [$this->table(['id' => $id, 'code' => 'VIP', 'name' => 'Barra', 'is_active' => false, 'capacity' => 4])]))->assertOk()->assertJsonPath('area.tables.0.id', $id)->assertJsonPath('area.tables.0.is_active', false)->assertJsonPath('area.tables.0.name', 'Barra');
        $this->assertDatabaseCount('dining_tables', 1);
    }

    #[DataProvider('validTables')]
    public function test_valid_table_metadata(array $changes): void
    {
        $area = $this->area();
        $this->actingAs($this->admin())->putJson("/admin/restaurant/areas/{$area->id}/layout", $this->layout($area, [$this->table($changes)]))->assertOk();
    }

    public static function validTables(): array
    {
        return ['rectangle' => [['shape' => 'rectangle']], 'circle' => [['shape' => 'circle']], 'rotation 45' => [['rotation' => 45]], 'rotation 90' => [['rotation' => 90]], 'rotation 315' => [['rotation' => 315]], 'nullable capacity' => [['capacity' => null]], 'capacity' => [['capacity' => 4]], 'boundary' => [['position_x' => '0.9000', 'position_y' => '0.8400']]];
    }

    #[DataProvider('invalidTables')]
    public function test_invalid_table_metadata_is_rejected(array $changes, string $field): void
    {
        $area = $this->area();
        $this->actingAs($this->admin())->putJson("/admin/restaurant/areas/{$area->id}/layout", $this->layout($area, [$this->table($changes)]))->assertUnprocessable()->assertJsonValidationErrors("tables.0.{$field}");
        $this->assertDatabaseCount('dining_tables', 0);
        $this->assertSame(0, $area->fresh()->layout_version);
    }

    public static function invalidTables(): array
    {
        return ['shape' => [['shape' => 'polygon'], 'shape'], 'negative x' => [['position_x' => '-0.01'], 'position_x'], 'x above one' => [['position_x' => '1.1'], 'position_x'], 'negative y' => [['position_y' => '-0.01'], 'position_y'], 'y above one' => [['position_y' => '1.1'], 'position_y'], 'zero width' => [['width' => '0'], 'width'], 'negative width' => [['width' => '-0.1'], 'width'], 'zero height' => [['height' => '0'], 'height'], 'negative height' => [['height' => '-0.1'], 'height'], 'x bounds' => [['position_x' => '0.9500'], 'position_x'], 'y bounds' => [['position_y' => '0.9500'], 'position_y'], 'rotation bounds' => [['position_x' => '0', 'rotation' => 45], 'position_x'], 'rotation range' => [['rotation' => 360], 'rotation'], 'rotation step' => [['rotation' => 10], 'rotation'], 'negative capacity' => [['capacity' => -1], 'capacity'], 'zero capacity' => [['capacity' => 0], 'capacity'], 'fractional capacity' => [['capacity' => 2.5], 'capacity'], 'empty code' => [['code' => ''], 'code'], 'precision' => [['position_x' => '0.12345'], 'position_x'], 'unknown field' => [['occupied' => true], 'occupied'], 'ellipse not circle' => [['shape' => 'circle', 'height' => '0.1000'], 'height']];
    }

    public function test_identifier_is_unique_within_area_case_insensitively(): void
    {
        $area = $this->area();
        $this->actingAs($this->admin())->putJson("/admin/restaurant/areas/{$area->id}/layout", $this->layout($area, [$this->table(['code' => 'VIP']), $this->table(['code' => 'vip'])]))->assertUnprocessable();
        $this->assertDatabaseCount('dining_tables', 0);
    }

    public function test_same_code_is_allowed_in_another_area(): void
    {
        $this->actingAs($this->admin());
        foreach ([$this->area(), $this->area()] as $area) {
            $this->putJson("/admin/restaurant/areas/{$area->id}/layout", $this->layout($area, [$this->table()]))->assertOk();
        }
        $this->assertDatabaseCount('dining_tables', 2);
    }

    public function test_valid_obstacles_are_persisted(): void
    {
        $area = $this->area();
        $obstacles = [['type' => 'circle', 'x' => '0.4', 'y' => '0.3', 'width' => '0.05', 'height' => '0.08', 'label' => 'Column'], ['type' => 'rectangle', 'x' => '0.6', 'y' => '0.5', 'width' => '0.1', 'height' => '0.1']];
        $this->actingAs($this->admin())->putJson("/admin/restaurant/areas/{$area->id}/layout", $this->layout($area, changes: ['obstacles' => $obstacles]))->assertOk()->assertJsonPath('area.obstacles.0.x', '0.4000');
    }

    #[DataProvider('invalidObstacles')]
    public function test_invalid_obstacles_are_rejected(array $obstacle, string $field): void
    {
        $area = $this->area();
        $base = ['type' => 'circle', 'x' => '0.4', 'y' => '0.3', 'width' => '0.05', 'height' => '0.08'];
        $this->actingAs($this->admin())->putJson("/admin/restaurant/areas/{$area->id}/layout", $this->layout($area, changes: ['obstacles' => [array_replace($base, $obstacle)]]))->assertUnprocessable()->assertJsonValidationErrors("obstacles.0.{$field}");
    }

    public static function invalidObstacles(): array
    {
        return ['type' => [['type' => 'svg'], 'type'], 'bounds' => [['x' => '0.99'], 'x'], 'negative' => [['y' => '-0.1'], 'y'], 'zero' => [['width' => '0'], 'width'], 'unknown schema' => [['orders' => []], 'orders']];
    }

    public function test_stale_version_returns_conflict_without_losing_any_changes(): void
    {
        $area = $this->area();
        $draft = $this->layout($area, [$this->table()]);
        $this->actingAs($this->admin())->putJson("/admin/restaurant/areas/{$area->id}/layout", $draft)->assertOk()->assertJsonPath('area.layout_version', 1);
        $draft['name'] = 'Stale administrator';
        $draft['tables'][0]['position_x'] = '0.5';
        $this->putJson("/admin/restaurant/areas/{$area->id}/layout", $draft)->assertConflict()->assertJsonPath('code', 'layout_conflict')
            ->assertJsonPath('message', 'El plano cambió. Conservá tus cambios y recargá para reconciliar.');
        $this->assertSame('Test dining room', $area->fresh()->name);
        $this->assertSame('0.2000', DiningTable::query()->sole()->position_x);
    }

    public function test_database_error_mid_save_rolls_back_area_and_tables(): void
    {
        $area = $this->area();
        DB::unprepared("CREATE FUNCTION pos2_test_failure() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN IF NEW.code = 'FAIL' THEN RAISE EXCEPTION 'test failure'; END IF; RETURN NEW; END $$; CREATE TRIGGER pos2_test_failure BEFORE INSERT ON dining_tables FOR EACH ROW EXECUTE FUNCTION pos2_test_failure();");
        try {
            $this->actingAs($this->admin())->putJson("/admin/restaurant/areas/{$area->id}/layout", $this->layout($area, [$this->table(), $this->table(['code' => 'FAIL'])], ['name' => 'Must roll back']))->assertStatus(500);
            $this->assertSame('Test dining room', $area->fresh()->name);
            $this->assertSame(0, $area->fresh()->layout_version);
            $this->assertDatabaseCount('dining_tables', 0);
        } finally {
            DB::unprepared('DROP TRIGGER pos2_test_failure ON dining_tables; DROP FUNCTION pos2_test_failure();');
        }
    }

    public function test_omission_or_foreign_table_id_cannot_delete_or_move_history(): void
    {
        $area = $this->area();
        $foreign = $this->area();
        $this->actingAs($this->admin());
        $this->putJson("/admin/restaurant/areas/{$area->id}/layout", $this->layout($area, [$this->table()]))->assertOk();
        $id = DiningTable::query()->sole()->id;
        $this->putJson("/admin/restaurant/areas/{$area->id}/layout", $this->layout($area->fresh()))->assertUnprocessable()
            ->assertJsonPath('errors.tables.0', 'Incluí todas las mesas existentes de esta área. Si una mesa ya no se usa, desactivala en lugar de eliminarla.');
        $this->putJson("/admin/restaurant/areas/{$foreign->id}/layout", $this->layout($foreign, [$this->table(['id' => $id])]))->assertUnprocessable();
        $this->assertDatabaseCount('dining_tables', 1);
    }

    public function test_numeric_string_duplicate_ids_cannot_hide_an_omitted_table(): void
    {
        $area = $this->area();
        $this->actingAs($this->admin())->putJson("/admin/restaurant/areas/{$area->id}/layout", $this->layout($area, [$this->table(), $this->table(['code' => '2'])]))->assertOk();
        $id = DiningTable::query()->orderBy('id')->firstOrFail()->id;
        $this->putJson("/admin/restaurant/areas/{$area->id}/layout", $this->layout($area->fresh(), [$this->table(['id' => $id]), $this->table(['id' => (string) $id, 'code' => '3'])]))->assertUnprocessable();
        $this->assertSame(1, $area->fresh()->layout_version);
    }

    #[DataProvider('staff')]
    public function test_only_floor_managers_can_modify_floor_and_only_order_takers_can_read_it(?string $role, bool $reads): void
    {
        $user = $role === null ? User::factory()->create() : User::factory()->withRole(Role::from($role))->create();
        $area = $this->area();
        $response = $this->actingAs($user)->get('/admin/restaurant/floor');
        $reads ? $response->assertOk() : $response->assertForbidden();
        $this->postJson('/admin/restaurant/areas', ['name' => 'Forbidden', 'width' => 1600, 'height' => 1000, 'is_active' => true])->assertForbidden();
        $this->putJson("/admin/restaurant/areas/{$area->id}/layout", $this->layout($area))->assertForbidden();
    }

    public static function staff(): array
    {
        return [
            'waiter' => ['waiter', true],
            'cashier' => ['cashier', true],
            'kitchen' => ['kitchen', false],
            'accountant' => ['accountant', false],
            'no membership' => [null, false],
        ];
    }
}
