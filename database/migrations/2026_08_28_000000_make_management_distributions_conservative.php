<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('management_distributions')->exists()) {
            throw new RuntimeException(
                'Cannot add a source wallet to existing management distributions without inventing financial history.'
            );
        }

        Schema::table('management_distributions', function (Blueprint $table): void {
            $table->foreignId('source_wallet_id')
                ->after('id')
                ->constrained('wallets')
                ->restrictOnDelete();
            $table->index(['source_wallet_id', 'effective_date'], 'management_distributions_source_period_index');
        });

        DB::unprepared(<<<'SQL'
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
                positive_entry_count integer;
                negative_entry_count integer;
                entry_total numeric(14, 2);
                unmatched_count integer;
                incorrect_rounding_count integer;
                archived_wallet_count integer;
                source_archived_at timestamptz;
                source_entry_count integer;
                source_is_destination boolean;
            BEGIN
                SELECT * INTO distribution FROM management_distributions WHERE id = target_distribution_id;
                SELECT * INTO source_transaction FROM financial_transactions WHERE id = distribution.financial_transaction_id;

                IF source_transaction.type <> 'distribution'
                    OR source_transaction.created_by <> distribution.created_by
                    OR source_transaction.effective_date <> distribution.effective_date THEN
                    RAISE EXCEPTION 'Management distribution % is inconsistent with financial transaction %', distribution.id, distribution.financial_transaction_id
                        USING ERRCODE = '23514';
                END IF;

                SELECT archived_at INTO source_archived_at FROM wallets WHERE id = distribution.source_wallet_id;
                SELECT EXISTS (
                    SELECT 1 FROM management_distribution_allocations
                    WHERE management_distribution_id = distribution.id
                      AND wallet_id = distribution.source_wallet_id
                ) INTO source_is_destination;

                SELECT COUNT(*), COALESCE(SUM(amount), 0), COALESCE(SUM(percentage_bps), 0)
                INTO snapshot_count, snapshot_total, snapshot_bps
                FROM management_distribution_allocations WHERE management_distribution_id = distribution.id;
                SELECT COUNT(*) INTO rule_count FROM management_distribution_rule_allocations WHERE rule_version_id = distribution.rule_version_id;
                SELECT COUNT(*),
                       COUNT(*) FILTER (WHERE amount > 0),
                       COUNT(*) FILTER (WHERE amount < 0),
                       COALESCE(SUM(amount), 0)
                INTO entry_count, positive_entry_count, negative_entry_count, entry_total
                FROM ledger_entries WHERE financial_transaction_id = distribution.financial_transaction_id;
                SELECT COUNT(*) INTO source_entry_count
                FROM ledger_entries
                WHERE financial_transaction_id = distribution.financial_transaction_id
                  AND wallet_id = distribution.source_wallet_id
                  AND amount = -distribution.amount;
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

                IF snapshot_count = 0 OR snapshot_count <> rule_count
                    OR entry_count <> snapshot_count + 1
                    OR positive_entry_count <> snapshot_count OR negative_entry_count <> 1
                    OR entry_total <> 0 OR source_entry_count <> 1
                    OR snapshot_total <> distribution.amount OR snapshot_bps <> 10000
                    OR source_archived_at IS NOT NULL OR source_is_destination
                    OR unmatched_count <> 0 OR incorrect_rounding_count <> 0 OR archived_wallet_count <> 0 THEN
                    RAISE EXCEPTION 'Management distribution % requires one active source debit and exact active destination credits totaling zero', distribution.id
                        USING ERRCODE = '23514';
                END IF;
            END;
            $$;
            SQL);
    }

    public function down(): void
    {
        if (DB::table('management_distributions')->exists()) {
            throw new RuntimeException(
                'Cannot remove source wallets from existing management distributions without corrupting financial history.'
            );
        }

        Schema::table('management_distributions', function (Blueprint $table): void {
            $table->dropIndex('management_distributions_source_period_index');
            $table->dropConstrainedForeignId('source_wallet_id');
        });

        DB::unprepared(<<<'SQL'
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
            SQL);
    }
};
