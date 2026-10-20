<?php

namespace Tests\Feature\Printing;

use App\Core\Support\TestingDatabaseGuard;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Actions\ConfirmTableRound;
use App\Modules\Orders\Enums\TableRoundKind;
use App\Modules\Orders\Models\TableRound;
use App\Modules\Printing\Actions\ClaimPrintJobs;
use App\Modules\Printing\Models\PrintJob;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Restaurant\Models\DiningArea;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Committed rows and a second connection: a claim must skip a job another station holds, not wait for it. */
final class PrintJobClaimConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TestingDatabaseGuard::ensureSafe(app()->environment(), DB::connection()->getDatabaseName());
        $this->artisan('migrate:fresh')->assertExitCode(0);
        RefreshDatabaseState::$migrated = true;
        $this->beforeApplicationDestroyed(static function (): void {
            RefreshDatabaseState::$migrated = false;
        });
    }

    public function test_a_claim_skips_jobs_another_station_holds_locked(): void
    {
        $cashier = User::factory()->withRole(Role::Cashier)->create();
        $category = Category::query()->create(['name' => 'Bebidas', 'slug' => 'bebidas', 'sort_order' => 1, 'is_active' => true]);
        $coke = Product::query()->create([
            'category_id' => $category->id, 'name' => 'Coca-Cola', 'slug' => 'coca-cola', 'pricing_family' => 'unit',
            'unit_price' => 2500, 'half_dozen_price' => 2500, 'dozen_price' => 2500, 'is_available' => true, 'sort_order' => 1,
        ]);
        $table = DiningArea::query()->create(['name' => 'Vereda', 'width' => 1600, 'height' => 1000, 'is_active' => true])->tables()->create([
            'code' => 'M1', 'shape' => 'rectangle', 'position_x' => '0.1000', 'position_y' => '0.1000',
            'width' => '0.1000', 'height' => '0.1000', 'rotation' => 0, 'is_active' => true,
        ]);
        foreach ([null, 'session'] as $round) {
            app(ConfirmTableRound::class)->execute(
                diningTableId: $table->id, expectedSessionId: $round === null ? null : TableRound::query()->value('table_session_id'),
                kind: TableRoundKind::Order, items: [['product_id' => $coke->id, 'quantity' => 1]], addons: [], note: null,
                roundKey: (string) Str::uuid(), actor: $cashier,
            );
        }
        [$first, $second] = PrintJob::query()->orderBy('id')->pluck('id')->all();

        config(['database.connections.other_station' => config('database.connections.'.config('database.default'))]);
        $other = DB::connection('other_station');
        $other->beginTransaction();
        try {
            $other->select('select id from print_jobs where id = ? for update', [$first]);
            // Without SKIP LOCKED the claim would wait on the other station's lock; fail fast instead.
            DB::unprepared("SET lock_timeout = '2s'");

            $claimed = app(ClaimPrintJobs::class)->execute($cashier->id);
        } finally {
            $other->rollBack();
            DB::unprepared('RESET lock_timeout');
            DB::purge('other_station');
        }

        $this->assertSame([$second], $claimed->pluck('id')->all());
        $this->assertSame(['pending', 'claimed'], PrintJob::query()->orderBy('id')->pluck('status')->all());
        $this->assertSame([$first], app(ClaimPrintJobs::class)->execute($cashier->id)->pluck('id')->all());
    }
}
