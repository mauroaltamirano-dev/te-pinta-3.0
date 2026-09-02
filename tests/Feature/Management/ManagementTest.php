<?php

namespace Tests\Feature\Management;

use App\Modules\Identity\Models\User;
use App\Modules\Management\Actions\CreateManualTransaction;
use App\Modules\Management\Actions\ReverseFinancialTransaction;
use App\Modules\Management\Data\ManualTransactionData;
use App\Modules\Management\Data\ReversalData;
use App\Modules\Management\Enums\FinancialTransactionType;
use App\Modules\Management\Models\FinancialTransaction;
use App\Modules\Management\Models\LedgerEntry;
use App\Modules\Management\Models\Wallet;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

final class ManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_management_routes_require_an_administrator(): void
    {
        $wallet = Wallet::query()->create(['name' => 'Caja']);
        $user = User::factory()->create();

        $this->get('/admin/management')->assertRedirect('/login');
        $this->actingAs($user)->get('/admin/management')->assertForbidden();
        $this->actingAs($user)->post('/admin/management/wallets', ['name' => 'Banco'])->assertForbidden();
        $this->actingAs($user)->post("/admin/management/wallets/{$wallet->id}/archive")->assertForbidden();
    }

    public function test_admin_can_create_update_archive_and_restore_a_wallet(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->post('/admin/management/wallets', ['name' => ' Caja ', 'sort_order' => 2])->assertRedirect();
        $wallet = Wallet::query()->sole();
        $this->assertSame('Caja', $wallet->name);
        $this->assertSame(2, $wallet->sort_order);

        $this->actingAs($admin)->patch("/admin/management/wallets/{$wallet->id}", ['name' => 'Efectivo', 'sort_order' => 1])->assertRedirect();
        $this->assertDatabaseHas('wallets', ['id' => $wallet->id, 'name' => 'Efectivo', 'sort_order' => 1]);

        $this->actingAs($admin)->post("/admin/management/wallets/{$wallet->id}/archive")->assertRedirect();
        $this->assertNotNull($wallet->fresh()->archived_at);
        $this->actingAs($admin)->post("/admin/management/wallets/{$wallet->id}/restore")->assertRedirect();
        $this->assertNull($wallet->fresh()->archived_at);
    }

    public function test_admin_can_move_the_order_payment_default_but_cannot_archive_it(): void
    {
        $admin = User::factory()->administrator()->create();
        $cash = Wallet::query()->create(['name' => 'Caja']);
        $bank = Wallet::query()->create(['name' => 'Banco']);

        $this->actingAs($admin)->patch("/admin/management/wallets/{$cash->id}", [
            'name' => 'Caja',
            'sort_order' => 1,
            'is_order_payment_default' => true,
        ])->assertRedirect();
        $this->assertTrue($cash->fresh()->is_order_payment_default);

        $this->actingAs($admin)->patch("/admin/management/wallets/{$cash->id}", [
            'name' => 'Caja principal',
            'sort_order' => 1,
        ])->assertRedirect();
        $this->assertTrue($cash->fresh()->is_order_payment_default);

        $this->actingAs($admin)->patch("/admin/management/wallets/{$cash->id}", [
            'name' => 'Caja principal',
            'sort_order' => 1,
            'is_order_payment_default' => false,
        ])->assertSessionHasErrors('is_order_payment_default');

        $this->actingAs($admin)->post("/admin/management/wallets/{$cash->id}/archive")
            ->assertSessionHasErrors('wallet');
        $this->assertNull($cash->fresh()->archived_at);

        $this->actingAs($admin)->patch("/admin/management/wallets/{$bank->id}", [
            'name' => 'Banco',
            'sort_order' => 2,
            'is_order_payment_default' => true,
        ])->assertRedirect();
        $this->assertFalse($cash->fresh()->is_order_payment_default);
        $this->assertTrue($bank->fresh()->is_order_payment_default);

        $this->actingAs($admin)->post("/admin/management/wallets/{$cash->id}/archive")->assertRedirect();
        $this->assertNotNull($cash->fresh()->archived_at);
    }

    public function test_manual_types_determine_sign_and_balance_is_derived_even_when_negative(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Caja']);

        foreach ([
            [FinancialTransactionType::Opening, 50000, 50000],
            [FinancialTransactionType::ManualIncome, 5000, 5000],
            [FinancialTransactionType::ManualExpense, 70000, -70000],
            [FinancialTransactionType::AdjustmentIncrease, 2000, 2000],
            [FinancialTransactionType::AdjustmentDecrease, 1000, -1000],
        ] as [$type, $amount, $signed]) {
            $this->actingAs($admin)->post('/admin/management/transactions', [
                'wallet_id' => $wallet->id,
                'type' => $type->value,
                'amount' => $amount,
                'effective_date' => '2026-08-26',
                'description' => $type->label(),
            ])->assertRedirect();
            $this->assertSame($signed, (int) FinancialTransaction::query()->latest('id')->firstOrFail()->entries()->value('amount'));
        }

        $this->assertSame(-14000, (int) $wallet->ledgerEntries()->sum('amount'));
        $this->assertFalse(array_key_exists('balance', $wallet->getAttributes()));
        $this->assertDatabaseCount('financial_transactions', 5);
        $this->assertDatabaseCount('ledger_entries', 5);
    }

    public function test_zero_and_client_supplied_negative_amounts_are_rejected(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Caja']);
        $payload = ['wallet_id' => $wallet->id, 'type' => 'manual_expense', 'effective_date' => '2026-08-26'];

        $this->actingAs($admin)->post('/admin/management/transactions', [...$payload, 'amount' => 0])->assertSessionHasErrors('amount');
        $this->actingAs($admin)->post('/admin/management/transactions', [...$payload, 'amount' => -500])->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_archiving_preserves_history_blocks_new_movements_and_still_allows_reversal(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Banco']);
        $original = $this->movement($admin, $wallet, FinancialTransactionType::ManualExpense, 12000, '2026-08-20');
        $originalAmount = (int) $original->entries()->value('amount');

        $this->actingAs($admin)->post("/admin/management/wallets/{$wallet->id}/archive")->assertRedirect();
        $this->actingAs($admin)->post('/admin/management/transactions', [
            'wallet_id' => $wallet->id, 'type' => 'manual_income', 'amount' => 500,
            'effective_date' => '2026-08-21',
        ])->assertSessionHasErrors('wallet_id');

        $this->actingAs($admin)->post("/admin/management/transactions/{$original->id}/reverse", [
            'effective_date' => '2026-08-22', 'description' => 'Corrección',
        ])->assertRedirect();

        $reversal = FinancialTransaction::query()->where('reversal_of_id', $original->id)->firstOrFail();
        $this->assertSame(-$originalAmount, (int) $reversal->entries()->value('amount'));
        $this->assertSame($originalAmount, (int) $original->fresh()->entries()->value('amount'));
        $this->assertNotNull($wallet->fresh()->archived_at);
        $this->assertSame(0, (int) $wallet->ledgerEntries()->sum('amount'));
    }

    public function test_transaction_can_be_reversed_only_once_and_a_failed_repeat_writes_nothing(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Caja']);
        $original = $this->movement($admin, $wallet, FinancialTransactionType::ManualIncome, 9000, '2026-08-20');
        $url = "/admin/management/transactions/{$original->id}/reverse";

        $this->actingAs($admin)->post($url, ['effective_date' => '2026-08-21'])->assertRedirect();
        $before = [FinancialTransaction::query()->count(), LedgerEntry::query()->count()];
        $this->actingAs($admin)->post($url, ['effective_date' => '2026-08-22'])->assertSessionHasErrors('transaction');
        $this->assertSame($before, [FinancialTransaction::query()->count(), LedgerEntry::query()->count()]);
    }

    public function test_reversal_rolls_back_its_header_when_an_entry_cannot_be_created(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Caja']);
        $original = $this->movement($admin, $wallet, FinancialTransactionType::ManualExpense, 9000, '2026-08-20');
        $before = [FinancialTransaction::query()->count(), LedgerEntry::query()->count()];
        $event = 'eloquent.creating: '.LedgerEntry::class;

        Event::listen($event, static function (): never {
            throw new RuntimeException('Simulated ledger entry failure.');
        });

        try {
            app(ReverseFinancialTransaction::class)->execute(
                $original,
                new ReversalData(CarbonImmutable::parse('2026-08-21'), 'Correction'),
                $admin->id,
            );
            $this->fail('Expected the reversal entry to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated ledger entry failure.', $exception->getMessage());
        } finally {
            Event::forget($event);
        }

        $this->assertSame($before, [FinancialTransaction::query()->count(), LedgerEntry::query()->count()]);
        $this->assertDatabaseMissing('financial_transactions', ['reversal_of_id' => $original->id]);
        $this->assertSame(-9000, (int) $original->fresh()->entries()->value('amount'));
    }

    public function test_history_filters_and_period_indicators_use_only_manual_income_and_expense(): void
    {
        CarbonImmutable::setTestNow('2026-08-26 12:00:00');
        $admin = User::factory()->administrator()->create();
        $cash = Wallet::query()->create(['name' => 'Caja']);
        $bank = Wallet::query()->create(['name' => 'Banco']);
        $this->movement($admin, $cash, FinancialTransactionType::Opening, 50000, '2026-08-01');
        $this->movement($admin, $cash, FinancialTransactionType::ManualIncome, 10000, '2026-08-10');
        $this->movement($admin, $cash, FinancialTransactionType::ManualExpense, 3000, '2026-08-11');
        $this->movement($admin, $bank, FinancialTransactionType::AdjustmentIncrease, 7000, '2026-08-12');

        $this->actingAs($admin)->get('/admin/management?wallet_id='.$cash->id.'&type=manual_income&from=2026-08-01&to=2026-08-31')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/management/index')
                ->where('summary.total_balance', 64000)
                ->where('summary.period_income', 10000)
                ->where('summary.period_expense', 3000)
                ->has('transactions.data', 1)
                ->where('transactions.data.0.type', 'manual_income')
                ->where('transactions.data.0.entries.0.wallet.id', $cash->id));
    }

    public function test_management_read_model_query_count_does_not_grow_per_row(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Caja']);
        $this->movement($admin, $wallet, FinancialTransactionType::ManualIncome, 1, '2026-08-20');
        $baseline = $this->requestQueryCount($admin);
        for ($i = 0; $i < 15; $i++) {
            $this->movement($admin, $wallet, FinancialTransactionType::ManualIncome, $i + 2, '2026-08-20');
        }

        $this->assertSame($baseline, $this->requestQueryCount($admin));
    }

    public function test_partial_period_filters_receive_the_missing_default_boundary(): void
    {
        CarbonImmutable::setTestNow('2026-08-26 12:00:00');
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->get('/admin/management?from=2026-08-10')
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('filters.from', '2026-08-10')->where('filters.to', '2026-08-26'));
        $this->actingAs($admin)->get('/admin/management?to=2026-08-20')
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('filters.from', '2026-08-01')->where('filters.to', '2026-08-20'));
    }

    public function test_header_and_entry_creation_roll_back_together_on_database_failure(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Caja']);
        $action = app(CreateManualTransaction::class);

        try {
            $action->execute(new ManualTransactionData(
                $wallet->id, FinancialTransactionType::ManualIncome, 1_000_000_000_000,
                CarbonImmutable::parse('2026-08-26'), 'Must roll back',
            ), $admin->id);
            $this->fail('Expected the decimal precision constraint to reject the entry.');
        } catch (QueryException) {
            $this->assertDatabaseMissing('financial_transactions', ['description' => 'Must roll back']);
            $this->assertDatabaseCount('ledger_entries', 0);
        }
    }

    public function test_business_log_uses_identifiers_and_never_the_financial_description(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Caja']);
        Event::fake([MessageLogged::class]);

        $this->movement($admin, $wallet, FinancialTransactionType::ManualIncome, 1000, '2026-08-26', 'Private supplier detail');
        Event::assertDispatched(MessageLogged::class, fn (MessageLogged $event): bool => $event->message === '[Management] transaction.created'
            && $event->context['module'] === 'Management'
            && $event->context['wallet_id'] === $wallet->id
            && $event->context['admin_id'] === $admin->id
            && ! array_key_exists('description', $event->context));
    }

    private function movement(User $admin, Wallet $wallet, FinancialTransactionType $type, int $amount, string $date, ?string $description = null): FinancialTransaction
    {
        return app(CreateManualTransaction::class)->execute(new ManualTransactionData(
            $wallet->id, $type, $amount, CarbonImmutable::parse($date), $description ?? $type->label(),
        ), $admin->id);
    }

    private function requestQueryCount(User $admin): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($admin)->get('/admin/management?from=2026-08-01&to=2026-08-31')->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
