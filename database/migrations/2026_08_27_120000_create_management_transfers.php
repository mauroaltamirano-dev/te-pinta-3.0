<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE financial_transactions DROP CONSTRAINT financial_transactions_type_valid');
        DB::statement(<<<'SQL'
            ALTER TABLE financial_transactions
            ADD CONSTRAINT financial_transactions_type_valid CHECK (
                type IN ('opening', 'adjustment_increase', 'adjustment_decrease', 'manual_income', 'manual_expense', 'management_outflow', 'transfer', 'reversal')
            )
            SQL);

        Schema::create('management_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->foreignId('destination_wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->foreignId('financial_transaction_id')->unique('management_transfers_transaction_unique')->constrained('financial_transactions')->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('effective_date');
            $table->string('description', 240)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

        });

        DB::statement(<<<'SQL'
            ALTER TABLE management_transfers
            ADD CONSTRAINT management_transfers_amount_positive_integer_pesos CHECK (
                amount > 0 AND amount = trunc(amount)
            ),
            ADD CONSTRAINT management_transfers_distinct_wallets CHECK (
                source_wallet_id <> destination_wallet_id
            )
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION management_validate_transfer_source()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            DECLARE
                source_transaction financial_transactions%ROWTYPE;
                total_entries integer;
                matching_source_entries integer;
                matching_destination_entries integer;
                entry_total numeric(14, 2);
            BEGIN
                SELECT * INTO source_transaction
                FROM financial_transactions
                WHERE id = NEW.financial_transaction_id;

                IF source_transaction.type <> 'transfer'
                    OR source_transaction.created_by <> NEW.created_by
                    OR source_transaction.effective_date <> NEW.effective_date THEN
                    RAISE EXCEPTION 'Management transfer % is inconsistent with financial transaction %', NEW.id, NEW.financial_transaction_id
                        USING ERRCODE = '23514';
                END IF;

                SELECT
                    COUNT(*),
                    COUNT(*) FILTER (WHERE wallet_id = NEW.source_wallet_id AND amount = -NEW.amount),
                    COUNT(*) FILTER (WHERE wallet_id = NEW.destination_wallet_id AND amount = NEW.amount),
                    COALESCE(SUM(amount), 0)
                INTO total_entries, matching_source_entries, matching_destination_entries, entry_total
                FROM ledger_entries
                WHERE financial_transaction_id = NEW.financial_transaction_id;

                IF total_entries <> 2
                    OR matching_source_entries <> 1
                    OR matching_destination_entries <> 1
                    OR entry_total <> 0 THEN
                    RAISE EXCEPTION 'Management transfer % requires exactly two opposite matching ledger entries', NEW.id
                        USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE CONSTRAINT TRIGGER management_transfers_validate_source
            AFTER INSERT ON management_transfers
            DEFERRABLE INITIALLY DEFERRED
            FOR EACH ROW EXECUTE FUNCTION management_validate_transfer_source();

            CREATE OR REPLACE FUNCTION management_require_transfer_source()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF NEW.type = 'transfer' AND NOT EXISTS (
                    SELECT 1 FROM management_transfers
                    WHERE financial_transaction_id = NEW.id
                ) THEN
                    RAISE EXCEPTION 'Financial transaction % requires a management transfer source', NEW.id
                        USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE CONSTRAINT TRIGGER financial_transactions_require_transfer_source
            AFTER INSERT ON financial_transactions
            DEFERRABLE INITIALLY DEFERRED
            FOR EACH ROW EXECUTE FUNCTION management_require_transfer_source();

            CREATE TRIGGER management_transfers_immutable
            BEFORE UPDATE OR DELETE ON management_transfers
            FOR EACH ROW EXECUTE FUNCTION management_prevent_financial_history_mutation();
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS financial_transactions_require_transfer_source ON financial_transactions');
        Schema::dropIfExists('management_transfers');
        DB::unprepared('DROP FUNCTION IF EXISTS management_require_transfer_source();');
        DB::unprepared('DROP FUNCTION IF EXISTS management_validate_transfer_source();');

        DB::statement('ALTER TABLE financial_transactions DROP CONSTRAINT financial_transactions_type_valid');
        DB::statement(<<<'SQL'
            ALTER TABLE financial_transactions
            ADD CONSTRAINT financial_transactions_type_valid CHECK (
                type IN ('opening', 'adjustment_increase', 'adjustment_decrease', 'manual_income', 'manual_expense', 'management_outflow', 'reversal')
            )
            SQL);
    }
};
