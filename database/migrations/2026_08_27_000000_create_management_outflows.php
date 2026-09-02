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
                type IN ('opening', 'adjustment_increase', 'adjustment_decrease', 'manual_income', 'manual_expense', 'management_outflow', 'reversal')
            )
            SQL);

        Schema::create('management_outflow_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 32);
            $table->string('code', 64);
            $table->string('name', 120);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampsTz();

            $table->unique(['type', 'code'], 'management_outflow_categories_type_code_unique');
            $table->index(['type', 'is_active', 'sort_order', 'id'], 'management_outflow_categories_selection_index');
        });

        Schema::create('management_outflows', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 32);
            $table->foreignId('category_id')->constrained('management_outflow_categories')->restrictOnDelete();
            $table->foreignId('wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->foreignId('financial_transaction_id')->unique('management_outflows_transaction_unique')->constrained('financial_transactions')->restrictOnDelete();
            $table->string('description', 240);
            $table->string('counterparty', 160)->nullable();
            $table->decimal('amount', 14, 2);
            $table->date('effective_date');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->index(['effective_date', 'id'], 'management_outflows_history_index');
            $table->index(['type', 'effective_date'], 'management_outflows_type_period_index');
            $table->index(['category_id', 'effective_date'], 'management_outflows_category_period_index');
            $table->index(['wallet_id', 'effective_date'], 'management_outflows_wallet_period_index');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE management_outflow_categories
            ADD CONSTRAINT management_outflow_categories_type_valid CHECK (
                type IN ('purchase', 'expense', 'personal_withdrawal')
            ),
            ADD CONSTRAINT management_outflow_categories_sort_order_nonnegative CHECK (sort_order >= 0)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE management_outflows
            ADD CONSTRAINT management_outflows_type_valid CHECK (
                type IN ('purchase', 'expense', 'personal_withdrawal')
            ),
            ADD CONSTRAINT management_outflows_amount_positive_integer_pesos CHECK (
                amount > 0 AND amount = trunc(amount)
            )
            SQL);

        $now = now();
        DB::table('management_outflow_categories')->insert([
            ['type' => 'purchase', 'code' => 'merchandise', 'name' => 'Productos / mercadería', 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'purchase', 'code' => 'raw_material', 'name' => 'Materia prima', 'sort_order' => 20, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'purchase', 'code' => 'materials_supplies', 'name' => 'Materiales / insumos', 'sort_order' => 30, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'purchase', 'code' => 'other', 'name' => 'Otros', 'sort_order' => 90, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'expense', 'code' => 'materials_supplies', 'name' => 'Materiales / insumos', 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'expense', 'code' => 'services', 'name' => 'Servicios', 'sort_order' => 20, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'expense', 'code' => 'other', 'name' => 'Otros', 'sort_order' => 90, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'personal_withdrawal', 'code' => 'personal', 'name' => 'Personal', 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION management_validate_outflow_source()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            DECLARE
                source_transaction financial_transactions%ROWTYPE;
                source_category_type text;
                matching_entries integer;
                total_entries integer;
            BEGIN
                SELECT * INTO source_transaction
                FROM financial_transactions
                WHERE id = NEW.financial_transaction_id;

                IF source_transaction.type <> 'management_outflow'
                    OR source_transaction.created_by <> NEW.created_by
                    OR source_transaction.effective_date <> NEW.effective_date THEN
                    RAISE EXCEPTION 'Management outflow % is inconsistent with financial transaction %', NEW.id, NEW.financial_transaction_id
                        USING ERRCODE = '23514';
                END IF;

                SELECT type INTO source_category_type
                FROM management_outflow_categories
                WHERE id = NEW.category_id;

                IF source_category_type <> NEW.type THEN
                    RAISE EXCEPTION 'Management outflow % category type is inconsistent', NEW.id
                        USING ERRCODE = '23514';
                END IF;

                SELECT COUNT(*), COUNT(*) FILTER (
                    WHERE wallet_id = NEW.wallet_id AND amount = -NEW.amount
                ) INTO total_entries, matching_entries
                FROM ledger_entries
                WHERE financial_transaction_id = NEW.financial_transaction_id;

                IF total_entries <> 1 OR matching_entries <> 1 THEN
                    RAISE EXCEPTION 'Management outflow % requires exactly one matching negative ledger entry', NEW.id
                        USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE CONSTRAINT TRIGGER management_outflows_validate_source
            AFTER INSERT ON management_outflows
            DEFERRABLE INITIALLY DEFERRED
            FOR EACH ROW EXECUTE FUNCTION management_validate_outflow_source();

            CREATE OR REPLACE FUNCTION management_require_outflow_source()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF NEW.type = 'management_outflow' AND NOT EXISTS (
                    SELECT 1 FROM management_outflows
                    WHERE financial_transaction_id = NEW.id
                ) THEN
                    RAISE EXCEPTION 'Financial transaction % requires a management outflow source', NEW.id
                        USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE CONSTRAINT TRIGGER financial_transactions_require_outflow_source
            AFTER INSERT ON financial_transactions
            DEFERRABLE INITIALLY DEFERRED
            FOR EACH ROW EXECUTE FUNCTION management_require_outflow_source();

            CREATE TRIGGER management_outflows_immutable
            BEFORE UPDATE OR DELETE ON management_outflows
            FOR EACH ROW EXECUTE FUNCTION management_prevent_financial_history_mutation();

            CREATE OR REPLACE FUNCTION management_protect_outflow_category_identity()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF NEW.type IS DISTINCT FROM OLD.type OR NEW.code IS DISTINCT FROM OLD.code THEN
                    RAISE EXCEPTION 'Management outflow category type and code are immutable'
                        USING ERRCODE = '55000';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER management_outflow_categories_identity_immutable
            BEFORE UPDATE ON management_outflow_categories
            FOR EACH ROW EXECUTE FUNCTION management_protect_outflow_category_identity();
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS financial_transactions_require_outflow_source ON financial_transactions');
        Schema::dropIfExists('management_outflows');
        Schema::dropIfExists('management_outflow_categories');
        DB::unprepared('DROP FUNCTION IF EXISTS management_require_outflow_source();');
        DB::unprepared('DROP FUNCTION IF EXISTS management_validate_outflow_source();');
        DB::unprepared('DROP FUNCTION IF EXISTS management_protect_outflow_category_identity();');

        DB::statement('ALTER TABLE financial_transactions DROP CONSTRAINT financial_transactions_type_valid');
        DB::statement(<<<'SQL'
            ALTER TABLE financial_transactions
            ADD CONSTRAINT financial_transactions_type_valid CHECK (
                type IN ('opening', 'adjustment_increase', 'adjustment_decrease', 'manual_income', 'manual_expense', 'reversal')
            )
            SQL);
    }
};
