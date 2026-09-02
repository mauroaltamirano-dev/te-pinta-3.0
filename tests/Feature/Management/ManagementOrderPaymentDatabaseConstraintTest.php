<?php

namespace Tests\Feature\Management;

use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Management\Models\Wallet;
use App\Modules\Orders\Events\OrderPaymentConfirmed;
use App\Modules\Orders\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ManagementOrderPaymentDatabaseConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_postgresql_defines_order_payment_constraints_indexes_and_immutable_triggers(): void
    {
        $constraints = DB::table('pg_constraint')->whereIn('conname', [
            'wallets_order_payment_default_active',
            'management_order_payments_order_unique',
            'management_order_payments_transaction_unique',
            'management_order_payments_amount_positive_integer_pesos',
        ])->pluck('conname')->all();
        $this->assertEqualsCanonicalizing([
            'wallets_order_payment_default_active',
            'management_order_payments_order_unique',
            'management_order_payments_transaction_unique',
            'management_order_payments_amount_positive_integer_pesos',
        ], $constraints);

        $indexes = DB::table('pg_indexes')->whereIn('indexname', [
            'wallets_single_order_payment_default_unique',
            'management_order_payments_order_unique',
            'management_order_payments_transaction_unique',
        ])->pluck('indexname')->all();
        $this->assertEqualsCanonicalizing([
            'wallets_single_order_payment_default_unique',
            'management_order_payments_order_unique',
            'management_order_payments_transaction_unique',
        ], $indexes);

        $triggers = DB::table('pg_trigger')->whereIn('tgname', [
            'management_order_payments_validate_source',
            'financial_transactions_require_order_payment_source',
            'management_order_payments_immutable',
        ])->where('tgisinternal', false)->pluck('tgname')->all();
        $this->assertEqualsCanonicalizing([
            'management_order_payments_validate_source',
            'financial_transactions_require_order_payment_source',
            'management_order_payments_immutable',
        ], $triggers);
    }

    public function test_postgresql_allows_only_one_active_unarchived_default_wallet(): void
    {
        Wallet::query()->create(['name' => 'Caja', 'is_order_payment_default' => true]);

        foreach ([
            fn () => Wallet::query()->create(['name' => 'Banco', 'is_order_payment_default' => true]),
            fn () => DB::table('wallets')->where('name', 'Caja')->update(['archived_at' => now()]),
        ] as $mutation) {
            try {
                DB::transaction($mutation);
                $this->fail('Expected the default-wallet invariant to fail.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_postgresql_rejects_incomplete_or_mismatched_order_payment_sources(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Caja', 'is_order_payment_default' => true]);
        $order = $this->order();

        foreach ([false, true] as $withSemanticSource) {
            try {
                DB::transaction(function () use ($admin, $wallet, $order, $withSemanticSource): void {
                    $transactionId = DB::table('financial_transactions')->insertGetId([
                        'type' => 'order_payment',
                        'effective_date' => '2026-08-28',
                        'created_by' => $admin->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    DB::table('ledger_entries')->insert([
                        'financial_transaction_id' => $transactionId,
                        'wallet_id' => $wallet->id,
                        'amount' => $withSemanticSource ? 14_999 : 15_000,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    if ($withSemanticSource) {
                        DB::table('management_order_payments')->insert([
                            'order_id' => $order->id,
                            'destination_wallet_id' => $wallet->id,
                            'financial_transaction_id' => $transactionId,
                            'amount' => 15_000,
                            'effective_date' => '2026-08-28',
                            'created_by' => $admin->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                    DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
                });
                $this->fail('Expected an incomplete order payment to fail.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_postgresql_rejects_raw_sources_for_unpaid_orders_or_totals_that_do_not_match(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Caja', 'is_order_payment_default' => true]);

        foreach ([
            [$this->order('unpaid'), 15_000],
            [$this->order('paid'), 14_000],
        ] as [$order, $amount]) {
            try {
                DB::transaction(function () use ($admin, $wallet, $order, $amount): void {
                    $transactionId = DB::table('financial_transactions')->insertGetId([
                        'type' => 'order_payment',
                        'effective_date' => '2026-08-28',
                        'created_by' => $admin->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    DB::table('ledger_entries')->insert([
                        'financial_transaction_id' => $transactionId,
                        'wallet_id' => $wallet->id,
                        'amount' => $amount,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    DB::table('management_order_payments')->insert([
                        'order_id' => $order->id,
                        'destination_wallet_id' => $wallet->id,
                        'financial_transaction_id' => $transactionId,
                        'amount' => $amount,
                        'effective_date' => '2026-08-28',
                        'created_by' => $admin->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
                });
                $this->fail('Expected the raw order-payment source to fail.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_postgresql_rejects_zero_fractional_and_mutated_order_payments(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Caja', 'is_order_payment_default' => true]);
        $order = $this->order();

        foreach (['0', '1.50'] as $amount) {
            try {
                DB::transaction(function () use ($order, $wallet, $admin, $amount): void {
                    DB::table('management_order_payments')->insert([
                        'order_id' => $order->id,
                        'destination_wallet_id' => $wallet->id,
                        'financial_transaction_id' => PHP_INT_MAX,
                        'amount' => $amount,
                        'effective_date' => '2026-08-28',
                        'created_by' => $admin->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                });
                $this->fail("Expected amount {$amount} to fail.");
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }

        event(new OrderPaymentConfirmed(
            $order->id,
            15_000,
            CarbonImmutable::parse('2026-08-28'),
            $admin->id,
        ));
        $payment = DB::table('management_order_payments')->sole();
        foreach ([
            fn () => DB::table('management_order_payments')->where('id', $payment->id)->update(['amount' => 14_000]),
            fn () => DB::table('management_order_payments')->where('id', $payment->id)->delete(),
        ] as $mutation) {
            try {
                DB::transaction($mutation);
                $this->fail('Expected immutable order payment history to reject mutation.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    private function order(string $paymentStatus = 'paid'): Order
    {
        $customer = Customer::query()->create(['name' => 'Order customer']);

        return Order::query()->create([
            'customer_id' => $customer->id,
            'source' => 'admin',
            'customer_name_snapshot' => $customer->name,
            'status' => 'waiting',
            'payment_status' => $paymentStatus,
            'payment_method' => $paymentStatus === 'paid' ? 'cash' : null,
            'condition' => 'raw',
            'delivery_type' => 'pickup',
            'delivery_period' => 'night',
            'delivery_date' => '2026-08-28',
            'items_cooked_subtotal' => 15_000,
            'raw_discount' => 0,
            'items_subtotal' => 15_000,
            'toppings_subtotal' => 0,
            'shipping_fee_snapshot' => 0,
            'combined_dozen_savings' => 0,
            'discount_amount' => 0,
            'total' => 15_000,
        ])->refresh();
    }
}
