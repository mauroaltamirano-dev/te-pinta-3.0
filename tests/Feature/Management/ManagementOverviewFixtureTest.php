<?php

namespace Tests\Feature\Management;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Management\Models\FinancialTransaction;
use App\Modules\Management\Models\LedgerEntry;
use App\Modules\Management\Models\ManagementAttachment;
use App\Modules\Management\Models\Purchase;
use App\Modules\Management\Models\Wallet;
use App\Modules\Management\Queries\BuildManagementFinancialSummary;
use App\Modules\Orders\Support\OrderBusinessDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ManagementOverviewSeeder;
use Tests\TestCase;

final class ManagementOverviewFixtureTest extends TestCase
{
    use RefreshDatabase;

    public function test_representative_fixture_reconciles_and_reseeding_creates_no_duplicate_evidence(): void
    {
        Storage::fake('local');
        User::factory()->administrator()->create(['email' => 'playwright-admin@example.test']);
        $this->seed(ManagementOverviewSeeder::class);
        $today = app(OrderBusinessDate::class)->today();
        $summary = app(BuildManagementFinancialSummary::class)->execute($today->subDays(29)->toDateString(), $today->toDateString());
        foreach (['total_balance' => 267000, 'period_income' => 150000, 'period_operating_result' => 119500, 'period_opening_balance' => 155000, 'period_closing_balance' => 267000, 'period_purchases' => 15500, 'period_purchase_shipping' => 500, 'period_expenses' => 12000, 'period_expense' => 3000, 'period_personal_withdrawal' => 10000, 'period_distribution' => 30000, 'period_other_movements' => 2500, 'reconciliation_difference' => 0] as $key => $value) {
            $this->assertSame($value, $summary[$key], $key);
        }
        $balances = LedgerEntry::query()->join('wallets', 'wallets.id', '=', 'ledger_entries.wallet_id')->selectRaw('wallets.name, SUM(amount) as balance')->groupBy('wallets.name')->pluck('balance', 'name')->map(fn ($value) => (int) $value)->all();
        $this->assertSame(['AUX2A Banco' => 130000, 'AUX2A Caja' => 120000, 'AUX2A Histórica' => 5000, 'AUX2A Reserva' => 12000], collect($balances)->sortKeys()->all());
        $this->assertNotNull(Wallet::where('name', 'AUX2A Histórica')->sole()->archived_at);
        $this->assertSame(2, Purchase::count());
        $this->assertSame(3, StockMovement::count());
        $attachment = ManagementAttachment::sole();
        $this->assertSame('image/png', $attachment->mime_type);
        $this->assertSame(hash('sha256', Storage::disk('local')->get($attachment->path)), $attachment->checksum_sha256);
        $before = [FinancialTransaction::count(), LedgerEntry::count(), StockMovement::count(), ManagementAttachment::count(), Storage::disk('local')->allFiles()];
        $this->assertGreaterThan(25, $before[0]);
        $this->seed(ManagementOverviewSeeder::class);
        $this->assertSame($before, [FinancialTransaction::count(), LedgerEntry::count(), StockMovement::count(), ManagementAttachment::count(), Storage::disk('local')->allFiles()]);
        $yesterday = $today->subDay()->toDateString();
        $zero = app(BuildManagementFinancialSummary::class)->execute($yesterday, $yesterday);
        $this->assertSame(0, $zero['period_purchases']);
        $this->assertSame(0, $zero['period_operating_result']);
        $this->assertSame(2, FinancialTransaction::whereDate('effective_date', $yesterday)->count());
    }
}
