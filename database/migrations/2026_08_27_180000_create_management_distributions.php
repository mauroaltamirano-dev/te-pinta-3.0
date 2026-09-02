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
                type IN ('opening', 'adjustment_increase', 'adjustment_decrease', 'manual_income', 'manual_expense', 'management_outflow', 'transfer', 'distribution', 'reversal')
            )
            SQL);

        Schema::create('management_distribution_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
            $table->index(['active', 'id'], 'management_distribution_rules_selection_index');
        });

        Schema::create('management_distribution_rule_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('distribution_rule_id')->constrained('management_distribution_rules')->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['distribution_rule_id', 'version_number'], 'management_distribution_rule_versions_number_unique');
        });

        Schema::create('management_distribution_rule_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rule_version_id')->constrained('management_distribution_rule_versions')->restrictOnDelete();
            $table->foreignId('wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->unsignedSmallInteger('percentage_bps');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['rule_version_id', 'wallet_id'], 'management_distribution_rule_allocations_wallet_unique');
            $table->index(['rule_version_id', 'sort_order', 'wallet_id'], 'management_distribution_rule_allocations_order_index');
        });

        Schema::create('management_distributions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rule_version_id')->constrained('management_distribution_rule_versions')->restrictOnDelete();
            $table->foreignId('financial_transaction_id')->unique('management_distributions_transaction_unique')->constrained('financial_transactions')->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('effective_date');
            $table->string('description', 240)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
            $table->index(['effective_date', 'id'], 'management_distributions_history_index');
            $table->index(['rule_version_id', 'effective_date'], 'management_distributions_rule_period_index');
        });

        Schema::create('management_distribution_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('management_distribution_id')->constrained('management_distributions')->restrictOnDelete();
            $table->foreignId('wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->string('wallet_name', 120);
            $table->unsignedSmallInteger('percentage_bps');
            $table->decimal('amount', 14, 2);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['management_distribution_id', 'wallet_id'], 'management_distribution_allocations_wallet_unique');
            $table->index(['management_distribution_id', 'sort_order', 'wallet_id'], 'management_distribution_allocations_order_index');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE management_distribution_rule_versions
            ADD CONSTRAINT management_distribution_rule_versions_number_positive CHECK (version_number > 0)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE management_distribution_rule_allocations
            ADD CONSTRAINT management_distribution_rule_allocations_percentage_valid CHECK (percentage_bps > 0 AND percentage_bps <= 10000),
            ADD CONSTRAINT management_distribution_rule_allocations_sort_order_nonnegative CHECK (sort_order >= 0)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE management_distributions
            ADD CONSTRAINT management_distributions_amount_positive_integer_pesos CHECK (amount > 0 AND amount = trunc(amount))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE management_distribution_allocations
            ADD CONSTRAINT management_distribution_allocations_percentage_valid CHECK (percentage_bps > 0 AND percentage_bps <= 10000),
            ADD CONSTRAINT management_distribution_allocations_amount_positive_integer CHECK (amount > 0 AND amount = trunc(amount)),
            ADD CONSTRAINT management_distribution_allocations_sort_order_nonnegative CHECK (sort_order >= 0)
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION management_validate_distribution_rule_version()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            DECLARE
                total_bps integer;
                allocation_count integer;
                archived_count integer;
            BEGIN
                SELECT COALESCE(SUM(a.percentage_bps), 0), COUNT(*), COUNT(*) FILTER (WHERE w.archived_at IS NOT NULL)
                INTO total_bps, allocation_count, archived_count
                FROM management_distribution_rule_allocations a
                JOIN wallets w ON w.id = a.wallet_id
                WHERE a.rule_version_id = NEW.id;

                IF allocation_count = 0 OR total_bps <> 10000 OR archived_count <> 0 THEN
                    RAISE EXCEPTION 'Distribution rule version % requires active wallet allocations totaling exactly 10000 bps', NEW.id
                        USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$;

            CREATE CONSTRAINT TRIGGER management_distribution_rule_versions_validate
            AFTER INSERT ON management_distribution_rule_versions
            DEFERRABLE INITIALLY DEFERRED
            FOR EACH ROW EXECUTE FUNCTION management_validate_distribution_rule_version();

            SQL);

        // A separate id-based validator lets deferred allocation inserts protect already-committed versions too.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION management_validate_distribution_rule_version_for_id(target_version_id bigint)
            RETURNS void
            LANGUAGE plpgsql
            AS $$
            DECLARE
                total_bps integer;
                allocation_count integer;
                archived_count integer;
            BEGIN
                SELECT COALESCE(SUM(a.percentage_bps), 0), COUNT(*), COUNT(*) FILTER (WHERE w.archived_at IS NOT NULL)
                INTO total_bps, allocation_count, archived_count
                FROM management_distribution_rule_allocations a
                JOIN wallets w ON w.id = a.wallet_id
                WHERE a.rule_version_id = target_version_id;
                IF allocation_count = 0 OR total_bps <> 10000 OR archived_count <> 0 THEN
                    RAISE EXCEPTION 'Distribution rule version % requires active wallet allocations totaling exactly 10000 bps', target_version_id
                        USING ERRCODE = '23514';
                END IF;
            END;
            $$;

            CREATE OR REPLACE FUNCTION management_validate_distribution_rule_allocation()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                PERFORM management_validate_distribution_rule_version_for_id(NEW.rule_version_id);
                RETURN NEW;
            END;
            $$;

            CREATE CONSTRAINT TRIGGER management_distribution_rule_allocations_validate
            AFTER INSERT ON management_distribution_rule_allocations
            DEFERRABLE INITIALLY DEFERRED
            FOR EACH ROW EXECUTE FUNCTION management_validate_distribution_rule_allocation();

            CREATE OR REPLACE FUNCTION management_validate_distribution_source_for_id(target_distribution_id bigint)
            RETURNS void
            LANGUAGE plpgsql
            AS $$
            DECLARE
                distribution management_distributions%ROWTYPE;
                source_transaction financial_transactions%ROWTYPE;
                snapshot_count integer;
                snapshot_total numeric(14, 2);
                snapshot_bps integer;
                rule_count integer;
                entry_count integer;
                unmatched_count integer;
                incorrect_rounding_count integer;
                archived_wallet_count integer;
            BEGIN
                SELECT * INTO distribution FROM management_distributions WHERE id = target_distribution_id;
                SELECT * INTO source_transaction FROM financial_transactions WHERE id = distribution.financial_transaction_id;

                IF source_transaction.type <> 'distribution'
                    OR source_transaction.created_by <> distribution.created_by
                    OR source_transaction.effective_date <> distribution.effective_date THEN
                    RAISE EXCEPTION 'Management distribution % is inconsistent with financial transaction %', distribution.id, distribution.financial_transaction_id
                        USING ERRCODE = '23514';
                END IF;

                SELECT COUNT(*), COALESCE(SUM(amount), 0), COALESCE(SUM(percentage_bps), 0)
                INTO snapshot_count, snapshot_total, snapshot_bps
                FROM management_distribution_allocations WHERE management_distribution_id = distribution.id;
                SELECT COUNT(*) INTO rule_count FROM management_distribution_rule_allocations WHERE rule_version_id = distribution.rule_version_id;
                SELECT COUNT(*) INTO entry_count FROM ledger_entries WHERE financial_transaction_id = distribution.financial_transaction_id;
                SELECT COUNT(*) INTO archived_wallet_count
                FROM management_distribution_allocations s
                JOIN wallets w ON w.id = s.wallet_id
                WHERE s.management_distribution_id = distribution.id AND w.archived_at IS NOT NULL;

                SELECT COUNT(*) INTO unmatched_count
                FROM management_distribution_allocations s
                LEFT JOIN management_distribution_rule_allocations r
                    ON r.rule_version_id = distribution.rule_version_id
                    AND r.wallet_id = s.wallet_id
                    AND r.percentage_bps = s.percentage_bps
                    AND r.sort_order = s.sort_order
                LEFT JOIN ledger_entries e
                    ON e.financial_transaction_id = distribution.financial_transaction_id
                    AND e.wallet_id = s.wallet_id
                    AND e.amount = s.amount
                WHERE s.management_distribution_id = distribution.id
                    AND (r.id IS NULL OR e.id IS NULL);

                SELECT COUNT(*) INTO incorrect_rounding_count
                FROM management_distribution_allocations s
                WHERE s.management_distribution_id = distribution.id
                  AND s.amount <> (
                    trunc(distribution.amount * s.percentage_bps / 10000)
                    + CASE WHEN (s.sort_order, s.wallet_id) = (
                        SELECT x.sort_order, x.wallet_id
                        FROM management_distribution_allocations x
                        WHERE x.management_distribution_id = distribution.id
                        ORDER BY x.sort_order DESC, x.wallet_id DESC LIMIT 1
                    ) THEN distribution.amount - (
                        SELECT SUM(trunc(distribution.amount * y.percentage_bps / 10000))
                        FROM management_distribution_allocations y
                        WHERE y.management_distribution_id = distribution.id
                    ) ELSE 0 END
                  );

                IF snapshot_count = 0 OR snapshot_count <> rule_count OR snapshot_count <> entry_count
                    OR snapshot_total <> distribution.amount OR snapshot_bps <> 10000
                    OR unmatched_count <> 0 OR incorrect_rounding_count <> 0 OR archived_wallet_count <> 0 THEN
                    RAISE EXCEPTION 'Management distribution % requires exact rule snapshots and matching positive ledger entries', distribution.id
                        USING ERRCODE = '23514';
                END IF;
            END;
            $$;

            CREATE OR REPLACE FUNCTION management_validate_distribution_source()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                PERFORM management_validate_distribution_source_for_id(NEW.id);
                RETURN NEW;
            END;
            $$;

            CREATE CONSTRAINT TRIGGER management_distributions_validate_source
            AFTER INSERT ON management_distributions
            DEFERRABLE INITIALLY DEFERRED
            FOR EACH ROW EXECUTE FUNCTION management_validate_distribution_source();

            CREATE OR REPLACE FUNCTION management_validate_distribution_allocation()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                PERFORM management_validate_distribution_source_for_id(NEW.management_distribution_id);
                RETURN NEW;
            END;
            $$;

            CREATE CONSTRAINT TRIGGER management_distribution_allocations_validate
            AFTER INSERT ON management_distribution_allocations
            DEFERRABLE INITIALLY DEFERRED
            FOR EACH ROW EXECUTE FUNCTION management_validate_distribution_allocation();

            CREATE OR REPLACE FUNCTION management_validate_distribution_ledger_entry()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            DECLARE
                transaction_row financial_transactions%ROWTYPE;
                distribution_id bigint;
                original_distribution_id bigint;
                reversal_entry_count integer;
                original_entry_count integer;
                unmatched_reversal_count integer;
            BEGIN
                SELECT * INTO transaction_row FROM financial_transactions WHERE id = NEW.financial_transaction_id;
                IF transaction_row.type = 'distribution' THEN
                    SELECT id INTO distribution_id FROM management_distributions WHERE financial_transaction_id = transaction_row.id;
                    IF distribution_id IS NOT NULL THEN
                        PERFORM management_validate_distribution_source_for_id(distribution_id);
                    END IF;
                ELSIF transaction_row.type = 'reversal' THEN
                    SELECT id INTO original_distribution_id
                    FROM management_distributions WHERE financial_transaction_id = transaction_row.reversal_of_id;
                    IF original_distribution_id IS NOT NULL THEN
                        SELECT COUNT(*) INTO reversal_entry_count FROM ledger_entries WHERE financial_transaction_id = transaction_row.id;
                        SELECT COUNT(*) INTO original_entry_count FROM ledger_entries WHERE financial_transaction_id = transaction_row.reversal_of_id;
                        SELECT COUNT(*) INTO unmatched_reversal_count
                        FROM ledger_entries original
                        LEFT JOIN ledger_entries reversal
                          ON reversal.financial_transaction_id = transaction_row.id
                         AND reversal.wallet_id = original.wallet_id
                         AND reversal.amount = -original.amount
                        WHERE original.financial_transaction_id = transaction_row.reversal_of_id
                          AND reversal.id IS NULL;
                        IF reversal_entry_count <> original_entry_count OR unmatched_reversal_count <> 0 THEN
                            RAISE EXCEPTION 'Distribution reversal % requires exact opposite ledger entries', transaction_row.id
                                USING ERRCODE = '23514';
                        END IF;
                    END IF;
                END IF;
                RETURN NEW;
            END;
            $$;

            CREATE CONSTRAINT TRIGGER management_distribution_ledger_entries_validate
            AFTER INSERT ON ledger_entries
            DEFERRABLE INITIALLY DEFERRED
            FOR EACH ROW EXECUTE FUNCTION management_validate_distribution_ledger_entry();

            CREATE OR REPLACE FUNCTION management_require_distribution_source()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF NEW.type = 'distribution' AND NOT EXISTS (
                    SELECT 1 FROM management_distributions WHERE financial_transaction_id = NEW.id
                ) THEN
                    RAISE EXCEPTION 'Financial transaction % requires a management distribution source', NEW.id
                        USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$;

            CREATE CONSTRAINT TRIGGER financial_transactions_require_distribution_source
            AFTER INSERT ON financial_transactions
            DEFERRABLE INITIALLY DEFERRED
            FOR EACH ROW EXECUTE FUNCTION management_require_distribution_source();

            CREATE TRIGGER management_distribution_rule_versions_immutable
            BEFORE UPDATE OR DELETE ON management_distribution_rule_versions
            FOR EACH ROW EXECUTE FUNCTION management_prevent_financial_history_mutation();
            CREATE TRIGGER management_distribution_rule_allocations_immutable
            BEFORE UPDATE OR DELETE ON management_distribution_rule_allocations
            FOR EACH ROW EXECUTE FUNCTION management_prevent_financial_history_mutation();
            CREATE TRIGGER management_distributions_immutable
            BEFORE UPDATE OR DELETE ON management_distributions
            FOR EACH ROW EXECUTE FUNCTION management_prevent_financial_history_mutation();
            CREATE TRIGGER management_distribution_allocations_immutable
            BEFORE UPDATE OR DELETE ON management_distribution_allocations
            FOR EACH ROW EXECUTE FUNCTION management_prevent_financial_history_mutation();
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS financial_transactions_require_distribution_source ON financial_transactions');
        DB::statement('DROP TRIGGER IF EXISTS management_distribution_ledger_entries_validate ON ledger_entries');
        Schema::dropIfExists('management_distribution_allocations');
        Schema::dropIfExists('management_distributions');
        Schema::dropIfExists('management_distribution_rule_allocations');
        Schema::dropIfExists('management_distribution_rule_versions');
        Schema::dropIfExists('management_distribution_rules');
        DB::unprepared('DROP FUNCTION IF EXISTS management_require_distribution_source();');
        DB::unprepared('DROP FUNCTION IF EXISTS management_validate_distribution_ledger_entry();');
        DB::unprepared('DROP FUNCTION IF EXISTS management_validate_distribution_allocation();');
        DB::unprepared('DROP FUNCTION IF EXISTS management_validate_distribution_source();');
        DB::unprepared('DROP FUNCTION IF EXISTS management_validate_distribution_source_for_id(bigint);');
        DB::unprepared('DROP FUNCTION IF EXISTS management_validate_distribution_rule_allocation();');
        DB::unprepared('DROP FUNCTION IF EXISTS management_validate_distribution_rule_version();');
        DB::unprepared('DROP FUNCTION IF EXISTS management_validate_distribution_rule_version_for_id(bigint);');

        DB::statement('ALTER TABLE financial_transactions DROP CONSTRAINT financial_transactions_type_valid');
        DB::statement(<<<'SQL'
            ALTER TABLE financial_transactions
            ADD CONSTRAINT financial_transactions_type_valid CHECK (
                type IN ('opening', 'adjustment_increase', 'adjustment_decrease', 'manual_income', 'manual_expense', 'management_outflow', 'transfer', 'reversal')
            )
            SQL);
    }
};
