<?php

namespace Tests\Feature\Inventory;

use App\Core\Support\TestingDatabaseGuard;
use App\Core\Tenancy\RestaurantContext;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\InventoryCategory;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryUnit;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class InventoryConcurrencyConstraintTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TestingDatabaseGuard::ensureSafe(app()->environment(), DB::connection()->getDatabaseName());
        $this->beforeApplicationDestroyed(static function (): void {
            RefreshDatabaseState::$migrated = false;
        });
        // Separate PDO sessions require committed fixtures. Reset this private test schema,
        // without a full POS rollback or RefreshDatabase's invisible outer transaction.
        $this->artisan('migrate:fresh')->assertExitCode(0);
        RefreshDatabaseState::$migrated = true;
    }

    /** @return iterable<string, array{string}> */
    public static function identityChanges(): iterable
    {
        yield 'stockability' => ['stockability'];
        yield 'base unit and family' => ['measurement'];
        yield 'quantity precision' => ['precision'];
    }

    #[DataProvider('identityChanges')]
    public function test_pending_movement_locks_identity_and_committed_history_rejects_changes(string $change): void
    {
        [$item, $admin] = $this->fixtures();
        $movementWriter = $this->connection();
        $identityWriter = $this->connection();

        try {
            $movementWriter->beginTransaction();
            $this->insertMovement($movementWriter, $item, $admin);
            $identityWriter->beginTransaction();

            // A direct SQL update must wait for the pending movement's parent lock.
            $this->assertSqlState(fn () => $this->changeIdentity($identityWriter, $item, $change), '55P03');
            $identityWriter->rollBack();
            $movementWriter->commit();

            // Once visible, history must reject the same update rather than merely delay it.
            $this->assertSqlState(fn () => $this->changeIdentity($identityWriter, $item, $change), '55000');
            $this->assertTrue($item->fresh()->tracks_stock);
            $this->assertSame('weight', $item->fresh()->unit_family->value);
            $this->assertSame(3, $item->fresh()->quantity_precision);
            $this->assertSame($item->base_unit_id, $item->fresh()->base_unit_id);
            $this->assertDatabaseCount('inventory_stock_movements', 1);
            $this->assertDatabaseHas('inventory_stock_movements', [
                'inventory_item_id' => $item->id, 'quantity' => '0.125000', 'unit_id' => $item->base_unit_id,
            ]);
        } finally {
            $this->rollbackIfOpen($identityWriter);
            $this->rollbackIfOpen($movementWriter);
        }
    }

    #[DataProvider('identityChanges')]
    public function test_pending_identity_update_locks_movement_and_new_identity_is_validated(string $change): void
    {
        [$item, $admin] = $this->fixtures();
        $movementWriter = $this->connection();
        $identityWriter = $this->connection();

        try {
            $identityWriter->beginTransaction();
            $this->changeIdentity($identityWriter, $item, $change);
            $movementWriter->beginTransaction();

            // The insert must not validate a stale committed identity during a pending update.
            $this->assertSqlState(fn () => $this->insertMovement($movementWriter, $item, $admin), '55P03');
            $movementWriter->rollBack();
            $identityWriter->commit();

            $movementWriter->beginTransaction();
            $this->assertSqlState(fn () => $this->insertMovement($movementWriter, $item, $admin), '23514');
            $movementWriter->rollBack();
            $this->assertDatabaseCount('inventory_transactions', 0);
            $this->assertDatabaseCount('inventory_stock_movements', 0);
            match ($change) {
                'stockability' => $this->assertFalse($item->fresh()->tracks_stock),
                'measurement' => $this->assertSame('volume', $item->fresh()->unit_family->value),
                'precision' => $this->assertSame(0, $item->fresh()->quantity_precision),
            };
        } finally {
            $this->rollbackIfOpen($identityWriter);
            $this->rollbackIfOpen($movementWriter);
        }
    }

    /** @return array{InventoryItem, User} */
    private function fixtures(): array
    {
        $category = InventoryCategory::query()->create(['name' => 'Concurrency ingredients', 'is_active' => true]);
        $unit = InventoryUnit::query()->where('code', 'g')->sole();
        $item = InventoryItem::query()->create([
            'inventory_category_id' => $category->id, 'name' => 'Concurrency flour',
            'tracks_stock' => true, 'unit_family' => 'weight', 'base_unit_id' => $unit->id,
            'default_input_unit_id' => $unit->id, 'quantity_precision' => 3, 'is_active' => true,
        ]);

        return [$item, User::factory()->administrator()->create()];
    }

    private function connection(): PDO
    {
        $config = DB::connection()->getConfig();
        TestingDatabaseGuard::ensureSafe(app()->environment(), $config['database'], $config['url'] ?? null);
        $pdo = new PDO(
            "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}",
            $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
        // Real conflicting statements with bounded lock waits; no sleeps or race timing assumptions.
        $pdo->exec("SET lock_timeout = '300ms'; SET statement_timeout = '2s'");
        $pdo->exec("SET app.current_restaurant_id = '".app(RestaurantContext::class)->id()."'");

        return $pdo;
    }

    private function insertMovement(PDO $pdo, InventoryItem $item, User $admin): void
    {
        $statement = $pdo->prepare("INSERT INTO inventory_transactions (type, effective_date, source_type, source_id, created_by) VALUES ('adjustment', '2026-10-06', 'adjustment', ?, ?) RETURNING id");
        $statement->execute(['concurrency-'.$item->id, $admin->id]);
        $transactionId = $statement->fetchColumn();
        $statement = $pdo->prepare('INSERT INTO inventory_stock_movements (inventory_transaction_id, inventory_item_id, quantity, unit_id) VALUES (?, ?, ?, ?)');
        $statement->execute([$transactionId, $item->id, '0.125', $item->base_unit_id]);
    }

    private function changeIdentity(PDO $pdo, InventoryItem $item, string $change): void
    {
        $attributes = match ($change) {
            'stockability' => 'tracks_stock = false',
            'measurement' => 'unit_family = \'volume\', base_unit_id = '.InventoryUnit::query()->where('code', 'ml')->sole()->id.', default_input_unit_id = '.InventoryUnit::query()->where('code', 'ml')->sole()->id,
            'precision' => 'quantity_precision = 0',
        };
        $statement = $pdo->prepare('UPDATE inventory_items SET '.$attributes.' WHERE id = ?');
        $statement->execute([$item->id]);
    }

    private function assertSqlState(Closure $statement, string $expected): void
    {
        try {
            $statement();
        } catch (PDOException $exception) {
            $this->assertSame($expected, $exception->getCode());

            return;
        }

        $this->fail('Expected PostgreSQL SQLSTATE '.$expected.'; conflicting statement was accepted.');
    }

    private function rollbackIfOpen(PDO $pdo): void
    {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
}
