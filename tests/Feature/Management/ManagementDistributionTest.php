<?php

namespace Tests\Feature\Management;

use App\Modules\Identity\Models\User;
use App\Modules\Management\Actions\CreateDistributionRule;
use App\Modules\Management\Actions\CreateManagementDistribution;
use App\Modules\Management\Actions\ReverseFinancialTransaction;
use App\Modules\Management\Data\DistributionAllocationData;
use App\Modules\Management\Data\DistributionRuleData;
use App\Modules\Management\Data\ManagementDistributionData;
use App\Modules\Management\Data\ReversalData;
use App\Modules\Management\Enums\FinancialTransactionType;
use App\Modules\Management\Models\DistributionRule;
use App\Modules\Management\Models\DistributionRuleVersion;
use App\Modules\Management\Models\LedgerEntry;
use App\Modules\Management\Models\ManagementDistribution;
use App\Modules\Management\Models\Wallet;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ManagementDistributionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_only_administrators_can_manage_rules_and_distributions(): void
    {
        $user = User::factory()->create();
        $wallets = $this->wallets();
        $payload = $this->rulePayload($wallets);

        $this->post('/admin/management/distribution-rules', $payload)->assertRedirect('/login');
        $this->actingAs($user)->post('/admin/management/distribution-rules', $payload)->assertForbidden();
        $this->assertDatabaseCount('management_distribution_rules', 0);
    }

    public function test_rule_requires_exactly_10000_positive_bps_and_active_distinct_wallets(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallets = $this->wallets();

        $this->actingAs($admin)->post('/admin/management/distribution-rules', $this->rulePayload($wallets, [5000, 3000, 1999]))
            ->assertSessionHasErrors('allocations');
        $this->actingAs($admin)->post('/admin/management/distribution-rules', $this->rulePayload($wallets, [5000, 5000, 0]))
            ->assertSessionHasErrors('allocations.2.percentage_bps');
        $wallets[2]->update(['archived_at' => now()]);
        $this->actingAs($admin)->post('/admin/management/distribution-rules', $this->rulePayload($wallets))
            ->assertSessionHasErrors('allocations.2.wallet_id');
        $this->assertDatabaseCount('management_distribution_rules', 0);
    }

    public function test_new_versions_are_immutable_and_do_not_change_previous_versions(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallets = $this->wallets();
        $rule = $this->createRule($admin, $wallets, [5000, 3000, 2000]);
        $v1 = $rule->versions()->sole();

        $this->actingAs($admin)->post("/admin/management/distribution-rules/{$rule->id}/versions", [
            'allocations' => $this->allocations($wallets, [6000, 2500, 1500]),
        ])->assertRedirect();

        $versions = $rule->versions()->with('allocations')->orderBy('version_number')->get();
        $this->assertSame([1, 2], $versions->pluck('version_number')->all());
        $this->assertSame([5000, 3000, 2000], $versions[0]->allocations->pluck('percentage_bps')->all());
        $this->assertSame([6000, 2500, 1500], $versions[1]->allocations->pluck('percentage_bps')->all());
        $this->expectException(QueryException::class);
        DB::table('management_distribution_rule_allocations')->where('rule_version_id', $v1->id)->limit(1)->update(['percentage_bps' => 4999]);
    }

    public function test_rule_can_be_deactivated_and_blocks_new_distributions_until_reactivated(): void
    {
        $admin = User::factory()->administrator()->create();
        $rule = $this->createRule($admin, $this->wallets());
        $version = $rule->versions()->sole();
        $source = $this->sourceWallet();

        $this->actingAs($admin)->patch("/admin/management/distribution-rules/{$rule->id}/status", ['active' => false])
            ->assertRedirect();
        $this->assertFalse($rule->fresh()->active);
        $this->actingAs($admin)->post('/admin/management/distributions', [
            'source_wallet_id' => $source->id,
            'rule_version_id' => $version->id,
            'amount' => 10000,
            'effective_date' => '2026-08-27',
            'description' => null,
        ])->assertSessionHasErrors('rule_version_id');

        $this->actingAs($admin)->patch("/admin/management/distribution-rules/{$rule->id}/status", ['active' => true])
            ->assertRedirect();
        $this->assertTrue($rule->fresh()->active);
    }

    public function test_creating_or_reactivating_a_rule_explicitly_replaces_the_global_active_rule(): void
    {
        $admin = User::factory()->administrator()->create();
        $firstWallets = $this->wallets();
        $first = $this->createRule($admin, $firstWallets);
        $secondWallets = [
            Wallet::query()->create(['name' => 'Operación 2']),
            Wallet::query()->create(['name' => 'Ganancia 2']),
            Wallet::query()->create(['name' => 'Reserva 2']),
        ];
        $second = app(CreateDistributionRule::class)->execute(
            new DistributionRuleData('Segunda', [
                new DistributionAllocationData($secondWallets[0]->id, 5000, 0),
                new DistributionAllocationData($secondWallets[1]->id, 3000, 1),
                new DistributionAllocationData($secondWallets[2]->id, 2000, 2),
            ]),
            $admin->id,
        );

        $this->assertFalse($first->fresh()->active);
        $this->assertTrue($second->fresh()->active);

        $this->actingAs($admin)->patch("/admin/management/distribution-rules/{$first->id}/status", ['active' => true])
            ->assertRedirect();

        $this->assertTrue($first->fresh()->active);
        $this->assertFalse($second->fresh()->active);
        $this->assertSame(1, DistributionRule::query()->where('active', true)->count());
    }

    public function test_distribution_requires_an_active_source_distinct_from_destinations(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallets = $this->wallets();
        $rule = $this->createRule($admin, $wallets);
        $version = $rule->versions()->sole();

        $this->actingAs($admin)->post('/admin/management/distributions', [
            'rule_version_id' => $version->id,
            'amount' => 10000,
            'effective_date' => '2026-08-27',
        ])->assertSessionHasErrors('source_wallet_id');

        foreach ([$wallets[0], tap($this->sourceWallet(), fn (Wallet $wallet) => $wallet->update(['archived_at' => now()]))] as $source) {
            try {
                $this->createDistribution($admin, $version, 10000, source: $source);
                $this->fail('Expected invalid source wallet validation.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('source_wallet_id', $exception->errors());
            }
        }

        $this->assertDatabaseCount('management_distributions', 0);
    }

    public function test_distribution_creates_one_semantic_zero_sum_transaction_and_relational_snapshot(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallets = $this->wallets();
        Event::fake([MessageLogged::class]);
        $rule = $this->createRule($admin, $wallets);

        $distribution = $this->createDistribution($admin, $rule->versions()->sole(), 10000, '2026-08-10', 'Asignación semanal');
        $entries = $distribution->financialTransaction->entries()->pluck('amount')->map(fn ($amount): int => (int) $amount)->all();

        $this->assertSame(FinancialTransactionType::Distribution, $distribution->financialTransaction->type);
        $this->assertSame(-10000, min($entries));
        $this->assertSame(1, collect($entries)->filter(fn (int $amount): bool => $amount < 0)->count());
        $this->assertSame(3, collect($entries)->filter(fn (int $amount): bool => $amount > 0)->count());
        $this->assertSame(0, array_sum($entries));
        $this->assertSame('Caja General', $distribution->sourceWallet->name);
        $this->assertSame([5000, 3000, 2000], $distribution->allocations()->pluck('percentage_bps')->all());
        $this->assertSame(['Operación', 'Ganancia', 'Reserva'], $distribution->allocations()->pluck('wallet_name')->all());
        $this->actingAs($admin)->get('/admin/management?from=2026-08-01&to=2026-08-31')
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('summary.total_balance', 0)
            ->where('summary.period_income', 0)
            ->where('summary.period_expense', 0)
            ->where('summary.period_distribution', 10000)
            ->where('transactions.data.0.management_distribution.source_wallet.name', 'Caja General'));
        Event::assertDispatched(MessageLogged::class, fn (MessageLogged $event): bool => $event->message === '[Management] distribution.created'
            && (int) $event->context['management_distribution_id'] === $distribution->id
            && (int) $event->context['source_wallet_id'] === $distribution->source_wallet_id
            && ! array_key_exists('amount', $event->context)
            && ! array_key_exists('description', $event->context));
    }

    public function test_rounding_is_deterministic_and_assigns_remainder_to_last_canonical_allocation(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallets = $this->wallets();
        $rule = $this->createRule($admin, $wallets, [3300, 3300, 3400]);
        $distribution = $this->createDistribution($admin, $rule->versions()->sole(), 10);

        $this->assertSame([3, 3, 4], $distribution->allocations()->pluck('amount')->map(fn ($amount): int => (int) $amount)->all());
        $this->assertSame(0, (int) $distribution->financialTransaction->entries()->sum('amount'));
    }

    public function test_amount_that_would_create_a_zero_allocation_is_rejected_atomically(): void
    {
        $admin = User::factory()->administrator()->create();
        $rule = $this->createRule($admin, $this->wallets());

        try {
            $this->createDistribution($admin, $rule->versions()->sole(), 1);
            $this->fail('Expected a zero allocation to be rejected.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
        $this->assertDatabaseCount('management_distributions', 0);
        $this->assertDatabaseCount('financial_transactions', 0);
        $this->assertDatabaseCount('ledger_entries', 0);
    }

    public function test_archived_wallet_or_inactive_rule_cannot_be_used_but_historical_distribution_remains_reversible_once(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallets = $this->wallets();
        Event::fake([MessageLogged::class]);
        $rule = $this->createRule($admin, $wallets);
        $distribution = $this->createDistribution($admin, $rule->versions()->sole(), 10000);
        $wallets[0]->update(['archived_at' => now()]);
        $distribution->sourceWallet->update(['archived_at' => now()]);

        $reversal = app(ReverseFinancialTransaction::class)->execute(
            $distribution->financialTransaction,
            new ReversalData(CarbonImmutable::parse('2026-08-28'), 'Corrección'),
            $admin->id,
        );
        $reversalAmounts = $reversal->entries()->pluck('amount')->map(fn ($amount): int => (int) $amount)->all();
        $this->assertEqualsCanonicalizing([10000, -5000, -3000, -2000], $reversalAmounts);
        $this->assertSame(0, array_sum($reversalAmounts));
        $this->assertDatabaseHas('management_distributions', ['id' => $distribution->id]);
        Event::assertDispatched(MessageLogged::class, fn (MessageLogged $event): bool => $event->message === '[Management] distribution.reversed');

        try {
            app(ReverseFinancialTransaction::class)->execute($distribution->financialTransaction, new ReversalData(CarbonImmutable::parse('2026-08-29'), null), $admin->id);
            $this->fail('Expected the second reversal to fail.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
        try {
            $this->createDistribution($admin, $rule->versions()->sole(), 10000);
            $this->fail('Expected archived wallet validation.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_distribution_is_separate_from_income_outflows_and_history_filters_semantically(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallets = $this->wallets();
        $rule = $this->createRule($admin, $wallets);
        $distribution = $this->createDistribution($admin, $rule->versions()->sole(), 10000, '2026-08-10');
        app(ReverseFinancialTransaction::class)->execute(
            $distribution->financialTransaction,
            new ReversalData(CarbonImmutable::parse('2026-08-20'), null),
            $admin->id,
        );

        $this->actingAs($admin)->get("/admin/management?wallet_id={$wallets[1]->id}&type=distribution&from=2026-08-01&to=2026-08-31")
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('summary.period_income', 0)
            ->where('summary.period_expense', 0)
            ->where('summary.period_operating_outflow', 0)
            ->where('summary.period_personal_withdrawal', 0)
            ->where('summary.period_distribution', 0)
            ->has('transactions.data', 2)
            ->where('transactions.data.0.management_distribution.is_reversal', true)
            ->where('transactions.data.1.management_distribution.rule_version.version_number', 1)
            ->where('transactions.data.1.management_distribution.allocations.1.amount', 3000));
        $this->assertSame(0, (int) LedgerEntry::query()->sum('amount'));
    }

    public function test_snapshot_keeps_v1_after_v2_and_overview_query_count_is_bounded(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallets = $this->wallets();
        $rule = $this->createRule($admin, $wallets);
        $v1Distribution = $this->createDistribution($admin, $rule->versions()->sole(), 10000);
        $this->actingAs($admin)->post("/admin/management/distribution-rules/{$rule->id}/versions", [
            'allocations' => $this->allocations($wallets, [6000, 2500, 1500]),
        ])->assertRedirect();

        $this->assertSame([5000, 3000, 2000], $v1Distribution->allocations()->pluck('amount')->map(fn ($amount): int => (int) $amount)->all());
        $baseline = $this->requestQueryCount($admin);
        for ($i = 0; $i < 15; $i++) {
            $this->createDistribution($admin, $rule->versions()->latest('version_number')->firstOrFail(), 10000 + $i);
        }
        $this->assertSame($baseline, $this->requestQueryCount($admin));
    }

    public function test_creation_rolls_back_completely_when_snapshot_insert_fails(): void
    {
        $admin = User::factory()->administrator()->create();
        $rule = $this->createRule($admin, $this->wallets());
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION test_reject_distribution_snapshot()
            RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'Injected failure'; END; $$;
            CREATE TRIGGER test_reject_distribution_snapshot BEFORE INSERT ON management_distribution_allocations
            FOR EACH ROW EXECUTE FUNCTION test_reject_distribution_snapshot();
            SQL);
        try {
            try {
                $this->createDistribution($admin, $rule->versions()->sole(), 10000);
                $this->fail('Expected the injected failure.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS test_reject_distribution_snapshot ON management_distribution_allocations; DROP FUNCTION IF EXISTS test_reject_distribution_snapshot();');
        }
        $this->assertDatabaseCount('management_distributions', 0);
        $this->assertDatabaseCount('financial_transactions', 0);
        $this->assertDatabaseCount('ledger_entries', 0);
    }

    /** @return list<Wallet> */
    private function wallets(): array
    {
        $wallets = [];
        foreach (['Operación', 'Ganancia', 'Reserva'] as $index => $name) {
            $wallets[] = Wallet::query()->create(['name' => $name, 'sort_order' => $index]);
        }

        return $wallets;
    }

    /**
     * @param  list<Wallet>  $wallets
     * @param  list<int>  $bps
     * @return list<array{wallet_id: int, percentage_bps: int, sort_order: int}>
     */
    private function allocations(array $wallets, array $bps = [5000, 3000, 2000]): array
    {
        $allocations = [];
        foreach ($wallets as $index => $wallet) {
            $allocations[] = [
                'wallet_id' => $wallet->id,
                'percentage_bps' => $bps[$index],
                'sort_order' => $index,
            ];
        }

        return $allocations;
    }

    /**
     * @param  list<Wallet>  $wallets
     * @param  list<int>  $bps
     * @return array{name: string, allocations: list<array{wallet_id: int, percentage_bps: int, sort_order: int}>}
     */
    private function rulePayload(array $wallets, array $bps = [5000, 3000, 2000]): array
    {
        return ['name' => 'Distribución negocio', 'allocations' => $this->allocations($wallets, $bps)];
    }

    /**
     * @param  list<Wallet>  $wallets
     * @param  list<int>  $bps
     */
    private function createRule(User $admin, array $wallets, array $bps = [5000, 3000, 2000]): DistributionRule
    {
        $this->actingAs($admin)->post('/admin/management/distribution-rules', $this->rulePayload($wallets, $bps))->assertRedirect();

        return DistributionRule::query()->sole();
    }

    private function createDistribution(User $admin, DistributionRuleVersion $version, int $amount, string $date = '2026-08-27', ?string $description = null, ?Wallet $source = null): ManagementDistribution
    {
        $source ??= $this->sourceWallet();

        return app(CreateManagementDistribution::class)->execute(new ManagementDistributionData(
            $source->id, $version->id, $amount, CarbonImmutable::parse($date), $description,
        ), $admin->id);
    }

    private function sourceWallet(): Wallet
    {
        return Wallet::query()->firstOrCreate(['name' => 'Caja General'], ['sort_order' => 99]);
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
