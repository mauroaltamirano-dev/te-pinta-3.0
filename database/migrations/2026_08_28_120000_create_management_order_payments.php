<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table): void {
            $table->boolean('is_order_payment_default')->default(false);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE wallets
            ADD CONSTRAINT wallets_order_payment_default_active CHECK (
                NOT is_order_payment_default OR archived_at IS NULL
            )
            SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX wallets_single_order_payment_default_unique
            ON wallets (is_order_payment_default)
            WHERE is_order_payment_default
            SQL);

        DB::statement('ALTER TABLE financial_transactions DROP CONSTRAINT financial_transactions_type_valid');
        DB::statement(<<<'SQL'
            ALTER TABLE financial_transactions
            ADD CONSTRAINT financial_transactions_type_valid CHECK (
                type IN ('opening', 'adjustment_increase', 'adjustment_decrease', 'manual_income', 'manual_expense', 'management_outflow', 'transfer', 'distribution', 'order_payment', 'reversal')
            )
            SQL);

        Schema::create('management_order_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->unique('management_order_payments_order_unique')->constrained('orders')->restrictOnDelete();
            $table->foreignId('destination_wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->foreignId('financial_transaction_id')->unique('management_order_payments_transaction_unique')->constrained('financial_transactions')->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('effective_date');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->index(['effective_date', 'id'], 'management_order_payments_history_index');
            $table->index(['destination_wallet_id', 'effective_date'], 'management_order_payments_wallet_period_index');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE management_order_payments
            ADD CONSTRAINT management_order_payments_amount_positive_integer_pesos CHECK (
                amount > 0 AND amount = trunc(amount)
            )
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION management_validate_order_payment_source()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            DECLARE
                source_transaction financial_transactions%ROWTYPE;
                source_order_payment_status text;
                source_order_total numeric(12, 2);
                destination_archived_at timestamptz;
                destination_is_default boolean;
                matching_entries integer;
                total_entries integer;
            BEGIN
                SELECT * INTO source_transaction
                FROM financial_transactions
                WHERE id = NEW.financial_transaction_id;

                IF source_transaction.type <> 'order_payment'
                    OR source_transaction.created_by <> NEW.created_by
                    OR source_transaction.effective_date <> NEW.effective_date THEN
                    RAISE EXCEPTION 'Management order payment % is inconsistent with financial transaction %', NEW.id, NEW.financial_transaction_id
                        USING ERRCODE = '23514';
                END IF;

                SELECT payment_status, total
                INTO source_order_payment_status, source_order_total
                FROM orders
                WHERE id = NEW.order_id;

                IF source_order_payment_status <> 'paid' OR source_order_total <> NEW.amount THEN
                    RAISE EXCEPTION 'Management order payment % requires a paid order with a matching total', NEW.id
                        USING ERRCODE = '23514';
                END IF;

                SELECT archived_at, is_order_payment_default
                INTO destination_archived_at, destination_is_default
                FROM wallets
                WHERE id = NEW.destination_wallet_id;

                IF destination_archived_at IS NOT NULL OR NOT destination_is_default THEN
                    RAISE EXCEPTION 'Management order payment % requires the active default destination wallet', NEW.id
                        USING ERRCODE = '23514';
                END IF;

                SELECT COUNT(*), COUNT(*) FILTER (
                    WHERE wallet_id = NEW.destination_wallet_id AND amount = NEW.amount
                ) INTO total_entries, matching_entries
                FROM ledger_entries
                WHERE financial_transaction_id = NEW.financial_transaction_id;

                IF total_entries <> 1 OR matching_entries <> 1 THEN
                    RAISE EXCEPTION 'Management order payment % requires exactly one matching positive ledger entry', NEW.id
                        USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE CONSTRAINT TRIGGER management_order_payments_validate_source
            AFTER INSERT ON management_order_payments
            DEFERRABLE INITIALLY DEFERRED
            FOR EACH ROW EXECUTE FUNCTION management_validate_order_payment_source();

            CREATE OR REPLACE FUNCTION management_require_order_payment_source()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF NEW.type = 'order_payment' AND NOT EXISTS (
                    SELECT 1 FROM management_order_payments
                    WHERE financial_transaction_id = NEW.id
                ) THEN
                    RAISE EXCEPTION 'Financial transaction % requires a management order payment source', NEW.id
                        USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE CONSTRAINT TRIGGER financial_transactions_require_order_payment_source
            AFTER INSERT ON financial_transactions
            DEFERRABLE INITIALLY DEFERRED
            FOR EACH ROW EXECUTE FUNCTION management_require_order_payment_source();

            CREATE TRIGGER management_order_payments_immutable
            BEFORE UPDATE OR DELETE ON management_order_payments
            FOR EACH ROW EXECUTE FUNCTION management_prevent_financial_history_mutation();
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS financial_transactions_require_order_payment_source ON financial_transactions');
        Schema::dropIfExists('management_order_payments');
        DB::unprepared('DROP FUNCTION IF EXISTS management_require_order_payment_source();');
        DB::unprepared('DROP FUNCTION IF EXISTS management_validate_order_payment_source();');

        DB::statement('ALTER TABLE financial_transactions DROP CONSTRAINT financial_transactions_type_valid');
        DB::statement(<<<'SQL'
            ALTER TABLE financial_transactions
            ADD CONSTRAINT financial_transactions_type_valid CHECK (
                type IN ('opening', 'adjustment_increase', 'adjustment_decrease', 'manual_income', 'manual_expense', 'management_outflow', 'transfer', 'distribution', 'reversal')
            )
            SQL);

        DB::statement('DROP INDEX IF EXISTS wallets_single_order_payment_default_unique');
        Schema::table('wallets', function (Blueprint $table): void {
            $table->dropColumn('is_order_payment_default');
        });
    }
};
