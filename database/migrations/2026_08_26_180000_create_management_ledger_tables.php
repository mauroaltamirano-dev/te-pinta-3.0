<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampTz('archived_at')->nullable();
            $table->timestampsTz();

            $table->index(['archived_at', 'sort_order', 'id'], 'wallets_management_list_index');
        });

        Schema::create('financial_transactions', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 32);
            $table->string('description', 240)->nullable();
            $table->date('effective_date');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reversal_of_id')->nullable()->constrained('financial_transactions')->restrictOnDelete();
            $table->timestampsTz();

            $table->unique('reversal_of_id', 'financial_transactions_single_reversal_unique');
            $table->index(['effective_date', 'id'], 'financial_transactions_history_index');
            $table->index(['type', 'effective_date'], 'financial_transactions_type_period_index');
        });

        Schema::create('ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('financial_transaction_id')->constrained()->restrictOnDelete();
            $table->foreignId('wallet_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->timestampsTz();

            $table->unique(['financial_transaction_id', 'wallet_id'], 'ledger_entries_transaction_wallet_unique');
            $table->index(['wallet_id', 'financial_transaction_id'], 'ledger_entries_wallet_history_index');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE wallets
            ADD CONSTRAINT wallets_sort_order_nonnegative CHECK (sort_order >= 0)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE financial_transactions
            ADD CONSTRAINT financial_transactions_type_valid CHECK (
                type IN ('opening', 'adjustment_increase', 'adjustment_decrease', 'manual_income', 'manual_expense', 'reversal')
            ),
            ADD CONSTRAINT financial_transactions_reversal_shape_valid CHECK (
                (type = 'reversal') = (reversal_of_id IS NOT NULL)
                AND (reversal_of_id IS NULL OR reversal_of_id <> id)
            )
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE ledger_entries
            ADD CONSTRAINT ledger_entries_amount_nonzero_integer_pesos CHECK (
                amount <> 0 AND amount = trunc(amount)
            )
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION management_prevent_financial_history_mutation()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'Financial ledger history is immutable'
                    USING ERRCODE = '55000';
            END;
            $$;

            CREATE TRIGGER financial_transactions_immutable
            BEFORE UPDATE OR DELETE ON financial_transactions
            FOR EACH ROW EXECUTE FUNCTION management_prevent_financial_history_mutation();

            CREATE TRIGGER ledger_entries_immutable
            BEFORE UPDATE OR DELETE ON ledger_entries
            FOR EACH ROW EXECUTE FUNCTION management_prevent_financial_history_mutation();

            CREATE OR REPLACE FUNCTION management_require_ledger_entries()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM ledger_entries
                    WHERE financial_transaction_id = NEW.id
                ) THEN
                    RAISE EXCEPTION 'Financial transaction % requires at least one ledger entry', NEW.id
                        USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE CONSTRAINT TRIGGER financial_transactions_require_entries
            AFTER INSERT ON financial_transactions
            DEFERRABLE INITIALLY DEFERRED
            FOR EACH ROW EXECUTE FUNCTION management_require_ledger_entries();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('financial_transactions');
        Schema::dropIfExists('wallets');
        DB::unprepared('DROP FUNCTION IF EXISTS management_require_ledger_entries();');
        DB::unprepared('DROP FUNCTION IF EXISTS management_prevent_financial_history_mutation();');
    }
};
