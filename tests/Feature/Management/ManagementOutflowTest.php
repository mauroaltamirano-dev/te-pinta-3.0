<?php

namespace Tests\Feature\Management;

use App\Modules\Identity\Models\User;
use App\Modules\Management\Actions\CreateManagementOutflow;
use App\Modules\Management\Actions\ReverseFinancialTransaction;
use App\Modules\Management\Data\ManagementOutflowData;
use App\Modules\Management\Data\ReversalData;
use App\Modules\Management\Enums\FinancialTransactionType;
use App\Modules\Management\Enums\ManagementOutflowType;
use App\Modules\Management\Models\FinancialTransaction;
use App\Modules\Management\Models\LedgerEntry;
use App\Modules\Management\Models\ManagementOutflow;
use App\Modules\Management\Models\ManagementOutflowCategory;
use App\Modules\Management\Models\Wallet;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

final class ManagementOutflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_only_administrators_can_register_outflows(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::query()->create(['name' => 'Caja']);
        $payload = $this->payload($wallet, ManagementOutflowType::Purchase, 'raw_material');

        $this->post('/admin/management/outflows', $payload)->assertRedirect('/login');
        $this->actingAs($user)->post('/admin/management/outflows', $payload)->assertForbidden();
        $this->assertDatabaseCount('management_outflows', 0);
    }

    public function test_purchase_expense_and_personal_withdrawal_create_one_negative_ledger_impact_each(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Banco']);
        $cases = [
            [ManagementOutflowType::Purchase, 'raw_material'],
            [ManagementOutflowType::Expense, 'services'],
            [ManagementOutflowType::PersonalWithdrawal, 'personal'],
        ];

        foreach ($cases as [$type, $categoryCode]) {
            $this->actingAs($admin)->post('/admin/management/outflows', $this->payload(
                $wallet, $type, $categoryCode, amount: 25000, description: $type->label(),
            ))->assertRedirect();

            $outflow = ManagementOutflow::query()->latest('id')->firstOrFail();
            $this->assertSame($type, $outflow->type);
            $this->assertSame(25000, (int) $outflow->amount);
            $this->assertSame($admin->id, $outflow->created_by);
            $this->assertSame(FinancialTransactionType::ManagementOutflow, $outflow->financialTransaction->type);
            $this->assertCount(1, $outflow->financialTransaction->entries);
            $this->assertSame(-25000, (int) $outflow->financialTransaction->entries->sole()->amount);
        }

        $this->assertDatabaseCount('management_outflows', 3);
        $this->assertDatabaseCount('financial_transactions', 3);
        $this->assertDatabaseCount('ledger_entries', 3);
        $this->assertSame(-75000, (int) $wallet->ledgerEntries()->sum('amount'));
        $this->assertFalse(array_key_exists('balance', $wallet->getAttributes()));
    }

    public function test_positive_amount_is_required_and_backend_owns_the_negative_sign(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Caja']);
        $payload = $this->payload($wallet, ManagementOutflowType::Expense, 'services');

        $this->actingAs($admin)->post('/admin/management/outflows', [...$payload, 'amount' => 0])->assertSessionHasErrors('amount');
        $this->actingAs($admin)->post('/admin/management/outflows', [...$payload, 'amount' => -1000])->assertSessionHasErrors('amount');
        $this->actingAs($admin)->post('/admin/management/outflows', [...$payload, 'amount' => 1000])->assertRedirect();

        $this->assertSame(-1000, (int) LedgerEntry::query()->sole()->amount);
    }

    public function test_archived_wallet_and_inactive_or_mismatched_category_are_rejected(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Caja', 'archived_at' => now()]);
        $purchase = $this->category(ManagementOutflowType::Purchase, 'raw_material');

        $this->actingAs($admin)->post('/admin/management/outflows', [
            ...$this->payload($wallet, ManagementOutflowType::Purchase, 'raw_material'),
            'category_id' => $purchase->id,
        ])->assertSessionHasErrors('wallet_id');

        $wallet->update(['archived_at' => null]);
        $purchase->update(['is_active' => false]);
        $this->actingAs($admin)->post('/admin/management/outflows', [
            ...$this->payload($wallet, ManagementOutflowType::Purchase, 'raw_material'),
            'category_id' => $purchase->id,
        ])->assertSessionHasErrors('category_id');

        $expenseCategory = $this->category(ManagementOutflowType::Expense, 'services');
        $this->actingAs($admin)->post('/admin/management/outflows', [
            ...$this->payload($wallet, ManagementOutflowType::Purchase, 'raw_material'),
            'category_id' => $expenseCategory->id,
        ])->assertSessionHasErrors('category_id');

        $this->assertDatabaseCount('management_outflows', 0);
        $this->assertDatabaseCount('financial_transactions', 0);
        $this->assertDatabaseCount('ledger_entries', 0);
    }

    public function test_outflow_header_transaction_and_entry_roll_back_together_after_an_intermediate_failure(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Banco']);
        $event = 'eloquent.creating: '.ManagementOutflow::class;
        Event::listen($event, static function (): never {
            throw new RuntimeException('Simulated semantic source failure.');
        });

        try {
            $this->createOutflow($admin, $wallet, ManagementOutflowType::Purchase, 'raw_material', 12000);
            $this->fail('Expected the semantic source to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated semantic source failure.', $exception->getMessage());
        } finally {
            Event::forget($event);
        }

        $this->assertDatabaseCount('management_outflows', 0);
        $this->assertDatabaseCount('financial_transactions', 0);
        $this->assertDatabaseCount('ledger_entries', 0);
    }

    public function test_reversal_preserves_the_outflow_and_works_after_wallet_archival_only_once(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Banco']);
        $outflow = $this->createOutflow($admin, $wallet, ManagementOutflowType::Purchase, 'raw_material', 25000);
        $original = $outflow->financialTransaction;
        $originalEntryId = $original->entries()->sole()->id;
        $wallet->update(['archived_at' => now()]);

        $url = "/admin/management/transactions/{$original->id}/reverse";
        $this->actingAs($admin)->post($url, ['effective_date' => '2026-08-27', 'description' => 'Corrección'])->assertRedirect();
        $reversal = FinancialTransaction::query()->where('reversal_of_id', $original->id)->sole();

        $this->assertSame(25000, (int) $reversal->entries()->sole()->amount);
        $this->assertSame(-25000, (int) LedgerEntry::query()->findOrFail($originalEntryId)->amount);
        $this->assertDatabaseHas('management_outflows', ['id' => $outflow->id, 'financial_transaction_id' => $original->id]);
        $this->assertSame(0, (int) $wallet->ledgerEntries()->sum('amount'));

        $before = [FinancialTransaction::query()->count(), LedgerEntry::query()->count(), ManagementOutflow::query()->count()];
        $this->actingAs($admin)->post($url, ['effective_date' => '2026-08-28'])->assertSessionHasErrors('transaction');
        $this->assertSame($before, [FinancialTransaction::query()->count(), LedgerEntry::query()->count(), ManagementOutflow::query()->count()]);
    }

    public function test_history_filters_semantic_types_and_categories_and_marks_reversals(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Caja']);
        $purchase = $this->createOutflow($admin, $wallet, ManagementOutflowType::Purchase, 'raw_material', 10000, 'Harina', date: '2026-08-10');
        $this->createOutflow($admin, $wallet, ManagementOutflowType::Expense, 'services', 3000, 'Internet', date: '2026-08-11');
        app(ReverseFinancialTransaction::class)->execute(
            $purchase->financialTransaction,
            new ReversalData(CarbonImmutable::parse('2026-08-20'), 'Compra duplicada'),
            $admin->id,
        );

        $category = $this->category(ManagementOutflowType::Purchase, 'raw_material');
        $this->actingAs($admin)->get('/admin/management?type=purchase&category_id='.$category->id.'&from=2026-08-01&to=2026-08-31')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.type', 'purchase')
                ->where('filters.category_id', $category->id)
                ->has('transactions.data', 2)
                ->where('transactions.data.0.type', 'reversal')
                ->where('transactions.data.0.management_outflow.type', 'purchase')
                ->where('transactions.data.0.management_outflow.is_reversal', true)
                ->where('transactions.data.1.management_outflow.description', 'Harina')
                ->where('transactions.data.1.management_outflow.category.code', 'raw_material')
                ->where('transactions.data.1.management_outflow.reversed', true));
    }

    public function test_period_metrics_classify_operating_outflows_and_withdrawals_using_ledger_effective_dates(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Caja']);
        $purchase = $this->createOutflow($admin, $wallet, ManagementOutflowType::Purchase, 'raw_material', 10000, date: '2026-08-10');
        $this->createOutflow($admin, $wallet, ManagementOutflowType::Expense, 'services', 3000, date: '2026-08-11');
        $this->createOutflow($admin, $wallet, ManagementOutflowType::PersonalWithdrawal, 'personal', 2000, date: '2026-08-12');
        app(ReverseFinancialTransaction::class)->execute(
            $purchase->financialTransaction,
            new ReversalData(CarbonImmutable::parse('2026-08-20'), 'Corrección'),
            $admin->id,
        );

        $this->actingAs($admin)->get('/admin/management?from=2026-08-01&to=2026-08-15')
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('summary.period_operating_outflow', 13000)
            ->where('summary.period_personal_withdrawal', 2000));
        $this->actingAs($admin)->get('/admin/management?from=2026-08-16&to=2026-08-31')
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('summary.period_operating_outflow', -10000)
            ->where('summary.period_personal_withdrawal', 0));
        $this->actingAs($admin)->get('/admin/management?from=2026-08-01&to=2026-08-31')
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('summary.period_operating_outflow', 3000)
            ->where('summary.period_personal_withdrawal', 2000));
    }

    public function test_outflows_have_no_destructive_delete_endpoint(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Caja']);
        $outflow = $this->createOutflow($admin, $wallet, ManagementOutflowType::Expense, 'services', 3000);

        $this->actingAs($admin)->delete("/admin/management/outflows/{$outflow->id}")->assertNotFound();
        $this->assertDatabaseHas('management_outflows', ['id' => $outflow->id]);
        $this->assertDatabaseHas('ledger_entries', ['financial_transaction_id' => $outflow->financial_transaction_id, 'amount' => -3000]);
    }

    public function test_outflow_history_query_count_is_bounded_and_logs_exclude_semantic_text(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Caja']);
        Event::fake([MessageLogged::class]);
        $this->createOutflow($admin, $wallet, ManagementOutflowType::Purchase, 'raw_material', 1, 'Private product', 'Private supplier');
        Event::assertDispatched(MessageLogged::class, fn (MessageLogged $event): bool => $event->message === '[Management] outflow.created'
            && $event->context['wallet_id'] === $wallet->id
            && ! array_key_exists('description', $event->context)
            && ! array_key_exists('counterparty', $event->context));

        $baseline = $this->requestQueryCount($admin);
        for ($i = 0; $i < 15; $i++) {
            $this->createOutflow($admin, $wallet, ManagementOutflowType::Expense, 'services', $i + 2);
        }
        $this->assertSame($baseline, $this->requestQueryCount($admin));
    }

    /** @return array<string, int|string|null> */
    private function payload(
        Wallet $wallet,
        ManagementOutflowType $type,
        string $categoryCode,
        int $amount = 25000,
        string $description = 'Operación administrativa',
        ?string $counterparty = 'Comercio',
        string $date = '2026-08-26',
    ): array {
        return [
            'type' => $type->value,
            'category_id' => $this->category($type, $categoryCode)->id,
            'wallet_id' => $wallet->id,
            'description' => $description,
            'counterparty' => $counterparty,
            'amount' => $amount,
            'effective_date' => $date,
        ];
    }

    private function category(ManagementOutflowType $type, string $code): ManagementOutflowCategory
    {
        return ManagementOutflowCategory::query()->where('type', $type)->where('code', $code)->sole();
    }

    private function createOutflow(
        User $admin,
        Wallet $wallet,
        ManagementOutflowType $type,
        string $categoryCode,
        int $amount,
        string $description = 'Operación administrativa',
        ?string $counterparty = null,
        string $date = '2026-08-26',
    ): ManagementOutflow {
        return app(CreateManagementOutflow::class)->execute(new ManagementOutflowData(
            type: $type,
            categoryId: $this->category($type, $categoryCode)->id,
            walletId: $wallet->id,
            description: $description,
            counterparty: $counterparty,
            amount: $amount,
            effectiveDate: CarbonImmutable::parse($date),
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
