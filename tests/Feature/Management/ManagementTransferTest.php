<?php

namespace Tests\Feature\Management;

use App\Modules\Identity\Models\User;
use App\Modules\Management\Actions\CreateManagementTransfer;
use App\Modules\Management\Actions\ReverseFinancialTransaction;
use App\Modules\Management\Data\ManagementTransferData;
use App\Modules\Management\Data\ReversalData;
use App\Modules\Management\Enums\FinancialTransactionType;
use App\Modules\Management\Models\LedgerEntry;
use App\Modules\Management\Models\ManagementTransfer;
use App\Modules\Management\Models\Wallet;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ManagementTransferTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_only_administrators_can_create_transfers(): void
    {
        $user = User::factory()->create();
        [$source, $destination] = $this->wallets();
        $payload = $this->payload($source, $destination);

        $this->post('/admin/management/transfers', $payload)->assertRedirect('/login');
        $this->actingAs($user)->post('/admin/management/transfers', $payload)->assertForbidden();
        $this->assertDatabaseCount('management_transfers', 0);
    }

    public function test_transfer_creates_one_semantic_source_and_two_neutral_ledger_entries(): void
    {
        $admin = User::factory()->administrator()->create();
        [$source, $destination] = $this->wallets();
        Event::fake([MessageLogged::class]);

        $this->actingAs($admin)->post('/admin/management/transfers', $this->payload(
            $source, $destination, amount: 30000, description: 'Retiro para caja',
        ))->assertRedirect();

        $transfer = ManagementTransfer::query()->sole();
        $transaction = $transfer->financialTransaction;
        $entries = $transaction->entries()->orderBy('wallet_id')->get();
        $this->assertSame(FinancialTransactionType::Transfer, $transaction->type);
        $this->assertSame($admin->id, $transfer->created_by);
        $this->assertSame(30000, (int) $transfer->amount);
        $this->assertCount(2, $entries);
        $this->assertSame(-30000, (int) $entries->firstWhere('wallet_id', $source->id)?->amount);
        $this->assertSame(30000, (int) $entries->firstWhere('wallet_id', $destination->id)?->amount);
        $this->assertSame(0, (int) $entries->sum('amount'));
        $this->assertDatabaseCount('financial_transactions', 1);
        $this->assertDatabaseCount('ledger_entries', 2);
        Event::assertDispatched(MessageLogged::class, fn (MessageLogged $event): bool => $event->message === '[Management] transfer.created'
            && $event->context['management_transfer_id'] === $transfer->id
            && ! array_key_exists('amount', $event->context)
            && ! array_key_exists('description', $event->context));
    }

    public function test_backend_rejects_same_wallet_non_positive_amounts_and_archived_wallets(): void
    {
        $admin = User::factory()->administrator()->create();
        [$source, $destination] = $this->wallets();
        $payload = $this->payload($source, $destination);

        $this->actingAs($admin)->post('/admin/management/transfers', [
            ...$payload, 'destination_wallet_id' => $source->id,
        ])->assertSessionHasErrors('destination_wallet_id');
        foreach ([0, -1000] as $amount) {
            $this->actingAs($admin)->post('/admin/management/transfers', [...$payload, 'amount' => $amount])
                ->assertSessionHasErrors('amount');
        }

        $source->update(['archived_at' => now()]);
        $this->actingAs($admin)->post('/admin/management/transfers', $payload)->assertSessionHasErrors('source_wallet_id');
        $source->update(['archived_at' => null]);
        $destination->update(['archived_at' => now()]);
        $this->actingAs($admin)->post('/admin/management/transfers', $payload)->assertSessionHasErrors('destination_wallet_id');
        $this->assertDatabaseCount('management_transfers', 0);
    }

    public function test_negative_wallet_balance_is_allowed_while_global_balance_and_metrics_stay_neutral(): void
    {
        $admin = User::factory()->administrator()->create();
        [$source, $destination] = $this->wallets();
        $this->createTransfer($admin, $source, $destination, 10000, '2026-08-10');

        $this->assertSame(-10000, (int) $source->ledgerEntries()->sum('amount'));
        $this->assertSame(10000, (int) $destination->ledgerEntries()->sum('amount'));
        $this->assertSame(0, (int) LedgerEntry::query()->sum('amount'));
        $this->actingAs($admin)->get('/admin/management?from=2026-08-01&to=2026-08-31')
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('summary.total_balance', 0)
            ->where('summary.period_income', 0)
            ->where('summary.period_expense', 0)
            ->where('summary.period_operating_outflow', 0)
            ->where('summary.period_personal_withdrawal', 0));
    }

    public function test_creation_is_atomic_when_the_second_ledger_impact_fails(): void
    {
        $admin = User::factory()->administrator()->create();
        [$source, $destination] = $this->wallets();
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION test_reject_positive_transfer_entry()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF NEW.amount > 0 THEN
                    RAISE EXCEPTION 'Injected transfer entry failure';
                END IF;
                RETURN NEW;
            END;
            $$;
            CREATE TRIGGER test_reject_positive_transfer_entry
            BEFORE INSERT ON ledger_entries
            FOR EACH ROW EXECUTE FUNCTION test_reject_positive_transfer_entry();
            SQL);

        try {
            try {
                $this->createTransfer($admin, $source, $destination, 1000);
                $this->fail('Expected the injected ledger failure.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS test_reject_positive_transfer_entry ON ledger_entries; DROP FUNCTION IF EXISTS test_reject_positive_transfer_entry();');
        }

        $this->assertDatabaseCount('management_transfers', 0);
        $this->assertDatabaseCount('financial_transactions', 0);
        $this->assertDatabaseCount('ledger_entries', 0);
    }

    public function test_wallets_are_locked_together_in_ascending_id_order(): void
    {
        $admin = User::factory()->administrator()->create();
        [$first, $second] = $this->wallets();
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });

        $this->createTransfer($admin, $second, $first, 1000);

        $lockQuery = collect($queries)->first(fn (string $sql): bool => str_contains($sql, 'from "wallets"') && str_contains($sql, 'for update'));
        $this->assertIsString($lockQuery);
        $this->assertStringContainsString('order by "id" asc', $lockQuery);
    }

    public function test_reversal_creates_two_opposite_entries_preserves_original_and_works_after_archival_once(): void
    {
        $admin = User::factory()->administrator()->create();
        [$source, $destination] = $this->wallets();
        Event::fake([MessageLogged::class]);
        $transfer = $this->createTransfer($admin, $source, $destination, 30000);
        $original = $transfer->financialTransaction;
        $originalEntries = $original->entries()->orderBy('wallet_id')->pluck('amount', 'wallet_id')->map(fn ($amount): int => (int) $amount)->all();
        $source->update(['archived_at' => now()]);
        $destination->update(['archived_at' => now()]);
        $reversal = app(ReverseFinancialTransaction::class)->execute(
            $original,
            new ReversalData(CarbonImmutable::parse('2026-08-27'), 'Corrección'),
            $admin->id,
        );
        $reversalEntries = $reversal->entries()->orderBy('wallet_id')->pluck('amount', 'wallet_id')->map(fn ($amount): int => (int) $amount)->all();

        $this->assertSame([-30000, 30000], array_values($originalEntries));
        $this->assertSame([30000, -30000], array_values($reversalEntries));
        $this->assertSame(0, array_sum($reversalEntries));
        $this->assertDatabaseHas('management_transfers', ['id' => $transfer->id, 'financial_transaction_id' => $original->id]);
        Event::assertDispatched(MessageLogged::class, fn (MessageLogged $event): bool => $event->message === '[Management] transfer.reversed'
            && $event->context['management_transfer_id'] === $transfer->id);

        $this->expectException(ValidationException::class);
        app(ReverseFinancialTransaction::class)->execute(
            $original,
            new ReversalData(CarbonImmutable::parse('2026-08-28'), null),
            $admin->id,
        );
    }

    public function test_history_represents_transfer_once_and_filters_by_either_wallet_type_and_effective_period(): void
    {
        $admin = User::factory()->administrator()->create();
        [$source, $destination] = $this->wallets();
        $transfer = $this->createTransfer($admin, $source, $destination, 30000, '2026-08-10', 'Caja operativa');
        app(ReverseFinancialTransaction::class)->execute(
            $transfer->financialTransaction,
            new ReversalData(CarbonImmutable::parse('2026-08-20'), 'Corrección'),
            $admin->id,
        );

        foreach ([$source, $destination] as $wallet) {
            $this->actingAs($admin)->get("/admin/management?wallet_id={$wallet->id}&type=transfer&from=2026-08-01&to=2026-08-31")
                ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->has('transactions.data', 2)
                ->where('transactions.data.0.management_transfer.is_reversal', true)
                ->where('transactions.data.1.management_transfer.source_wallet.id', $source->id)
                ->where('transactions.data.1.management_transfer.destination_wallet.id', $destination->id)
                ->where('transactions.data.1.management_transfer.description', 'Caja operativa')
                ->where('transactions.data.1.management_transfer.reversed', true));
        }
        $this->actingAs($admin)->get('/admin/management?type=transfer&from=2026-08-11&to=2026-08-19')
            ->assertOk()->assertInertia(fn (Assert $page) => $page->has('transactions.data', 0));
    }

    public function test_transfers_have_no_destructive_endpoint_and_history_queries_remain_bounded(): void
    {
        $admin = User::factory()->administrator()->create();
        [$source, $destination] = $this->wallets();
        $transfer = $this->createTransfer($admin, $source, $destination, 1);
        $this->actingAs($admin)->delete("/admin/management/transfers/{$transfer->id}")->assertNotFound();

        $baseline = $this->requestQueryCount($admin);
        for ($i = 0; $i < 15; $i++) {
            $this->createTransfer($admin, $source, $destination, $i + 2);
        }
        $this->assertSame($baseline, $this->requestQueryCount($admin));
    }

    /** @return array{Wallet, Wallet} */
    private function wallets(): array
    {
        return [Wallet::query()->create(['name' => 'Banco']), Wallet::query()->create(['name' => 'Caja'])];
    }

    /** @return array<string, int|string|null> */
    private function payload(Wallet $source, Wallet $destination, int $amount = 30000, ?string $description = null): array
    {
        return [
            'source_wallet_id' => $source->id,
            'destination_wallet_id' => $destination->id,
            'amount' => $amount,
            'effective_date' => '2026-08-27',
            'description' => $description,
        ];
    }

    private function createTransfer(
        User $admin,
        Wallet $source,
        Wallet $destination,
        int $amount,
        string $date = '2026-08-27',
        ?string $description = null,
    ): ManagementTransfer {
        return app(CreateManagementTransfer::class)->execute(new ManagementTransferData(
            sourceWalletId: $source->id,
            destinationWalletId: $destination->id,
            amount: $amount,
            effectiveDate: CarbonImmutable::parse($date),
            description: $description,
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
