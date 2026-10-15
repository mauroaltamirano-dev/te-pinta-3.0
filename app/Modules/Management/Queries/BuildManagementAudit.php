<?php

namespace App\Modules\Management\Queries;

use App\Modules\Management\Data\ManagementAuditFindingRow;
use App\Modules\Management\Data\ManagementAuditSummaryCounts;
use App\Modules\Management\Data\ManagementAuditWalletBalance;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class BuildManagementAudit
{
    /**
     * @param  array{wallet_id: ?int, type: ?string, severity: ?string, status: ?string, from: string, to: string}  $filters
     * @return array{
     *     summary: array{ok: int, pending: int, warnings: int, critical: int},
     *     findings: LengthAwarePaginator<int, array<string, mixed>>,
     *     wallets: array<int, array{wallet_id: int, wallet_name: string, archived_at: ?string, derived_balance: int, transaction_count: int, last_transaction_at: ?string, status: string}>
     * }
     */
    public function execute(array $filters): array
    {
        [$sql, $baseBindings] = $this->findingsSql($filters);
        $bindings = $baseBindings;
        $filteredSql = $this->filteredSql($sql, $filters, $bindings);

        $summary = ManagementAuditSummaryCounts::fromDatabaseRow(DB::selectOne(
            "SELECT
                COUNT(*) FILTER (WHERE type = 'pending_distribution') AS pending,
                COUNT(*) FILTER (WHERE severity = 'warning') AS warnings,
                COUNT(*) FILTER (WHERE severity = 'critical') AS critical,
                COUNT(DISTINCT (entity_type, entity_id)) FILTER (
                    WHERE entity_type IN ('order_payment', 'management_outflow', 'transfer', 'distribution')
                      AND entity_id IS NOT NULL
                ) AS affected_operations
             FROM ({$sql}) AS audit_findings",
            $baseBindings,
        ));

        $auditedOperations = (int) DB::table('financial_transactions as transactions')
            ->whereBetween('transactions.effective_date', [$filters['from'], $filters['to']])
            ->whereIn('transactions.type', ['order_payment', 'management_outflow', 'transfer', 'distribution'])
            ->when($filters['wallet_id'] !== null, fn ($query) => $query->whereExists(function ($entries) use ($filters): void {
                $entries->selectRaw('1')->from('ledger_entries')
                    ->whereColumn('ledger_entries.financial_transaction_id', 'transactions.id')
                    ->where('ledger_entries.wallet_id', $filters['wallet_id']);
            }))
            ->count();

        $perPage = 25;
        $page = LengthAwarePaginator::resolveCurrentPage();
        $total = $this->databaseInteger(
            DB::selectOne("SELECT COUNT(*) AS aggregate FROM ({$filteredSql}) AS audit_findings", $bindings),
            'aggregate',
        );
        $rows = DB::select(
            "SELECT * FROM ({$filteredSql}) AS audit_findings
             ORDER BY severity_rank DESC, effective_date DESC NULLS FIRST, entity_id DESC NULLS LAST, finding_key
             LIMIT {$perPage} OFFSET ".(($page - 1) * $perPage),
            $bindings,
        );

        $walletStatusRows = DB::select(
            "WITH audit_findings AS ({$sql}),
             impacted_wallets AS (
                 SELECT wallet_id, severity_rank FROM audit_findings
                 WHERE wallet_id IS NOT NULL AND severity_rank >= 2
                 UNION ALL
                 SELECT entries.wallet_id, findings.severity_rank
                 FROM audit_findings AS findings
                 JOIN ledger_entries AS entries
                   ON entries.financial_transaction_id = findings.financial_transaction_id
                 WHERE findings.severity_rank >= 2
             )
             SELECT wallet_id, MAX(severity_rank) AS severity_rank
             FROM impacted_wallets
             GROUP BY wallet_id",
            $baseBindings,
        );
        /** @var Collection<int, 'warning'|'critical'> $walletStatuses */
        $walletStatuses = collect($walletStatusRows)->mapWithKeys(function (object $row): array {
            $walletId = $this->databaseInteger($row, 'wallet_id');
            $severityRank = $this->databaseInteger($row, 'severity_rank');

            return [$walletId => $severityRank === 3 ? 'critical' : 'warning'];
        });
        $walletModels = $this->walletBalances($walletStatuses);
        $walletMetadata = $walletModels->keyBy(fn (ManagementAuditWalletBalance $wallet): int => $wallet->walletId);
        /** @var Collection<int, ManagementAuditFindingRow> $findingRows */
        $findingRows = collect($rows)
            ->map(fn (object $row): ManagementAuditFindingRow => ManagementAuditFindingRow::fromDatabaseRow($row))
            ->values();
        $findings = new LengthAwarePaginator(
            $findingRows->map(fn (ManagementAuditFindingRow $row): array => $this->mapFinding(
                $row,
                $walletMetadata,
            ))->values(),
            $total,
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => request()->query()],
        );

        return [
            'summary' => [
                'ok' => max(0, $auditedOperations - $summary->affectedOperations),
                'pending' => $summary->pending,
                'warnings' => $summary->warnings,
                'critical' => $summary->critical,
            ],
            'findings' => $findings,
            'wallets' => $walletModels->map(fn (ManagementAuditWalletBalance $wallet): array => $wallet->toArray())->values()->all(),
        ];
    }

    /** @return array{wallet_id: ?int, type: ?string, severity: ?string, status: ?string, from: string, to: string} */
    public static function defaultFilters(): array
    {
        $today = CarbonImmutable::today(config('orders.timezone'));

        return [
            'wallet_id' => null,
            'type' => null,
            'severity' => null,
            'status' => null,
            'from' => $today->subDays(29)->toDateString(),
            'to' => $today->toDateString(),
        ];
    }

    /**
     * @param  array{wallet_id: ?int, type: ?string, severity: ?string, status: ?string, from: string, to: string}  $filters
     * @param  array<int, mixed>  $bindings
     */
    private function filteredSql(string $sql, array $filters, array &$bindings): string
    {
        $conditions = [];
        if ($filters['type'] !== null) {
            $conditions[] = '(type = ? OR entity_type = ?)';
            $bindings[] = $filters['type'];
            $bindings[] = $filters['type'];
        }
        if ($filters['severity'] !== null) {
            $conditions[] = 'severity = ?';
            $bindings[] = $filters['severity'];
        }
        if ($filters['status'] !== null) {
            if ($filters['status'] === 'ok') {
                $conditions[] = 'FALSE';
            } elseif ($filters['status'] === 'pending') {
                $conditions[] = "type = 'pending_distribution'";
            } elseif ($filters['status'] === 'reversed') {
                $conditions[] = "type IN ('reversed_payment', 'reversed_distribution')";
            } else {
                $conditions[] = "type NOT IN ('pending_distribution', 'reversed_payment', 'reversed_distribution')";
            }
        }

        return $sql.($conditions === [] ? '' : ' WHERE '.implode(' AND ', $conditions));
    }

    /**
     * @param  array{wallet_id: ?int, type: ?string, severity: ?string, status: ?string, from: string, to: string}  $filters
     * @return array{string, array<int, mixed>}
     */
    private function findingsSql(array $filters): array
    {
        $sql = <<<'SQL'
            WITH filtered_transactions AS (
                SELECT transactions.*
                FROM financial_transactions AS transactions
                WHERE transactions.effective_date BETWEEN ? AND ?
                  AND (?::bigint IS NULL OR EXISTS (
                      SELECT 1 FROM ledger_entries AS wallet_entries
                      WHERE wallet_entries.financial_transaction_id = transactions.id
                        AND wallet_entries.wallet_id = ?::bigint
                  ))
            ),
            entry_stats AS (
                SELECT entries.financial_transaction_id,
                       COUNT(*) AS entry_count,
                       COUNT(*) FILTER (WHERE entries.amount > 0) AS positive_count,
                       COUNT(*) FILTER (WHERE entries.amount < 0) AS negative_count,
                       COALESCE(SUM(entries.amount), 0) AS entry_total
                FROM ledger_entries AS entries
                JOIN filtered_transactions AS transactions ON transactions.id = entries.financial_transaction_id
                GROUP BY entries.financial_transaction_id
            ),
            allocation_stats AS (
                SELECT allocations.management_distribution_id,
                       COUNT(*) AS allocation_count,
                       COALESCE(SUM(allocations.amount), 0) AS allocation_total,
                       COALESCE(SUM(allocations.percentage_bps), 0) AS bps_total
                FROM management_distribution_allocations AS allocations
                JOIN management_distributions AS distributions
                  ON distributions.id = allocations.management_distribution_id
                JOIN filtered_transactions AS transactions
                  ON transactions.id = distributions.financial_transaction_id
                GROUP BY allocations.management_distribution_id
            ),
            findings AS (
                SELECT 'pending_distribution:' || payments.id AS finding_key,
                       'pending_distribution' AS type, 'info' AS severity, 1 AS severity_rank,
                       'order_payment' AS entity_type, payments.id AS entity_id,
                       'Pendiente de distribución' AS title,
                       'El pago está registrado correctamente y todavía no tiene una distribución asociada.' AS message,
                       'Pedido #' || orders.public_id AS reference,
                       payments.effective_date, payments.destination_wallet_id AS wallet_id, wallets.name AS wallet_name,
                       payments.amount AS amount_expected, NULL::numeric AS amount_found,
                       '/admin/management?type=order_payment' AS action_url
                FROM management_order_payments AS payments
                JOIN filtered_transactions AS transactions ON transactions.id = payments.financial_transaction_id
                JOIN orders ON orders.id = payments.order_id
                JOIN wallets ON wallets.id = payments.destination_wallet_id
                JOIN entry_stats AS stats ON stats.financial_transaction_id = transactions.id
                LEFT JOIN management_distributions AS distributions ON distributions.management_order_payment_id = payments.id
                LEFT JOIN financial_transactions AS reversals ON reversals.reversal_of_id = transactions.id
                WHERE distributions.id IS NULL AND reversals.id IS NULL
                  AND payments.effective_date = transactions.effective_date
                  AND stats.entry_count = 1 AND stats.positive_count = 1
                  AND EXISTS (
                      SELECT 1 FROM ledger_entries AS entries
                      WHERE entries.financial_transaction_id = transactions.id
                        AND entries.wallet_id = payments.destination_wallet_id
                        AND entries.amount = payments.amount
                  )

                UNION ALL
                SELECT 'reversed_payment:' || payments.id, 'reversed_payment', 'warning', 2,
                       'order_payment', payments.id, 'Pago de pedido revertido',
                       'El impacto del pago fue compensado por una reversión y permanece como historia financiera.',
                       'Pedido #' || orders.public_id, payments.effective_date,
                       payments.destination_wallet_id, wallets.name, payments.amount, NULL::numeric, NULL::text
                FROM management_order_payments AS payments
                JOIN filtered_transactions AS transactions ON transactions.id = payments.financial_transaction_id
                JOIN orders ON orders.id = payments.order_id
                JOIN wallets ON wallets.id = payments.destination_wallet_id
                JOIN financial_transactions AS reversals ON reversals.reversal_of_id = transactions.id

                UNION ALL
                SELECT 'reversed_distribution:' || distributions.id, 'reversed_distribution', 'warning', 2,
                       'distribution', distributions.id, 'Distribución revertida',
                       'La distribución fue compensada y el movimiento original permanece intacto.',
                       'Distribución #' || distributions.id, distributions.effective_date,
                       distributions.source_wallet_id, wallets.name, distributions.amount, NULL::numeric, NULL::text
                FROM management_distributions AS distributions
                JOIN filtered_transactions AS transactions ON transactions.id = distributions.financial_transaction_id
                JOIN wallets ON wallets.id = distributions.source_wallet_id
                JOIN financial_transactions AS reversals ON reversals.reversal_of_id = transactions.id

                UNION ALL
                SELECT 'missing_semantic_source:' || transactions.id, 'missing_semantic_source', 'critical', 3,
                       transactions.type, transactions.id, 'Origen semántico faltante',
                       'La transacción financiera no tiene la entidad semántica requerida para explicar su origen.',
                       'Movimiento #' || transactions.id, transactions.effective_date,
                       NULL::bigint, NULL::text, NULL::numeric, NULL::numeric, NULL::text
                FROM filtered_transactions AS transactions
                LEFT JOIN management_order_payments AS payments ON payments.financial_transaction_id = transactions.id
                LEFT JOIN management_outflows AS outflows ON outflows.financial_transaction_id = transactions.id
                LEFT JOIN management_transfers AS transfers ON transfers.financial_transaction_id = transactions.id
                LEFT JOIN management_distributions AS distributions ON distributions.financial_transaction_id = transactions.id
                WHERE (transactions.type = 'order_payment' AND payments.id IS NULL)
                   OR (transactions.type = 'management_outflow' AND outflows.id IS NULL)
                   OR (transactions.type = 'transfer' AND transfers.id IS NULL)
                   OR (transactions.type = 'distribution' AND distributions.id IS NULL)

                UNION ALL
                SELECT 'amount_mismatch:order_payment:' || payments.id, 'amount_mismatch', 'critical', 3,
                       'order_payment', payments.id, 'Importe de pago inconsistente',
                       'El importe del pago no coincide con su entry positiva.',
                       'Pedido #' || orders.public_id, payments.effective_date,
                       payments.destination_wallet_id, wallets.name, payments.amount,
                       COALESCE((SELECT SUM(entries.amount) FROM ledger_entries entries WHERE entries.financial_transaction_id = transactions.id AND entries.amount > 0), 0), NULL::text
                FROM management_order_payments AS payments
                JOIN filtered_transactions AS transactions ON transactions.id = payments.financial_transaction_id
                JOIN orders ON orders.id = payments.order_id
                JOIN wallets ON wallets.id = payments.destination_wallet_id
                WHERE payments.amount <> COALESCE((SELECT SUM(entries.amount) FROM ledger_entries entries WHERE entries.financial_transaction_id = transactions.id AND entries.amount > 0), 0)

                UNION ALL
                SELECT 'wallet_mismatch:order_payment:' || payments.id, 'wallet_mismatch', 'critical', 3,
                       'order_payment', payments.id, 'Wallet de pago inconsistente',
                       'La wallet del pago no coincide con la wallet acreditada en el ledger.',
                       'Pedido #' || orders.public_id, payments.effective_date,
                       payments.destination_wallet_id, wallets.name, payments.amount, NULL::numeric, NULL::text
                FROM management_order_payments AS payments
                JOIN filtered_transactions AS transactions ON transactions.id = payments.financial_transaction_id
                JOIN orders ON orders.id = payments.order_id
                JOIN wallets ON wallets.id = payments.destination_wallet_id
                WHERE NOT EXISTS (
                    SELECT 1 FROM ledger_entries entries
                    WHERE entries.financial_transaction_id = transactions.id
                      AND entries.wallet_id = payments.destination_wallet_id
                      AND entries.amount = payments.amount
                )

                UNION ALL
                SELECT 'date_mismatch:order_payment:' || payments.id, 'date_mismatch', 'critical', 3,
                       'order_payment', payments.id, 'Fecha de pago inconsistente',
                       'La fecha efectiva del pago no coincide con su transacción financiera.',
                       'Pedido #' || orders.public_id, payments.effective_date,
                       payments.destination_wallet_id, wallets.name, NULL::numeric, NULL::numeric, NULL::text
                FROM management_order_payments AS payments
                JOIN filtered_transactions AS transactions ON transactions.id = payments.financial_transaction_id
                JOIN orders ON orders.id = payments.order_id
                JOIN wallets ON wallets.id = payments.destination_wallet_id
                WHERE payments.effective_date <> transactions.effective_date

                UNION ALL
                SELECT 'ledger_structure_mismatch:order_payment:' || payments.id, 'ledger_structure_mismatch', 'critical', 3,
                       'order_payment', payments.id, 'Estructura de pago inconsistente',
                       'Un pago requiere exactamente una entry positiva.',
                       'Pedido #' || orders.public_id, payments.effective_date,
                       payments.destination_wallet_id, wallets.name, payments.amount, stats.entry_total, NULL::text
                FROM management_order_payments AS payments
                JOIN filtered_transactions AS transactions ON transactions.id = payments.financial_transaction_id
                JOIN orders ON orders.id = payments.order_id
                JOIN wallets ON wallets.id = payments.destination_wallet_id
                LEFT JOIN entry_stats AS stats ON stats.financial_transaction_id = transactions.id
                WHERE COALESCE(stats.entry_count, 0) <> 1 OR COALESCE(stats.positive_count, 0) <> 1

                UNION ALL
                SELECT 'amount_mismatch:management_outflow:' || outflows.id, 'amount_mismatch', 'critical', 3,
                       'management_outflow', outflows.id, 'Importe de egreso inconsistente',
                       'El importe del egreso no coincide con el débito del ledger.',
                       'Egreso #' || outflows.id, outflows.effective_date,
                       outflows.wallet_id, wallets.name, outflows.amount,
                       -COALESCE((SELECT SUM(entries.amount) FROM ledger_entries entries WHERE entries.financial_transaction_id = transactions.id AND entries.amount < 0), 0), NULL::text
                FROM management_outflows AS outflows
                JOIN filtered_transactions AS transactions ON transactions.id = outflows.financial_transaction_id
                JOIN wallets ON wallets.id = outflows.wallet_id
                WHERE outflows.amount <> -COALESCE((SELECT SUM(entries.amount) FROM ledger_entries entries WHERE entries.financial_transaction_id = transactions.id AND entries.amount < 0), 0)

                UNION ALL
                SELECT 'wallet_mismatch:management_outflow:' || outflows.id, 'wallet_mismatch', 'critical', 3,
                       'management_outflow', outflows.id, 'Wallet de egreso inconsistente',
                       'La wallet del egreso no coincide con el débito del ledger.',
                       'Egreso #' || outflows.id, outflows.effective_date,
                       outflows.wallet_id, wallets.name, outflows.amount, NULL::numeric, NULL::text
                FROM management_outflows AS outflows
                JOIN filtered_transactions AS transactions ON transactions.id = outflows.financial_transaction_id
                JOIN wallets ON wallets.id = outflows.wallet_id
                WHERE NOT EXISTS (SELECT 1 FROM ledger_entries entries WHERE entries.financial_transaction_id = transactions.id AND entries.wallet_id = outflows.wallet_id AND entries.amount = -outflows.amount)

                UNION ALL
                SELECT 'date_mismatch:management_outflow:' || outflows.id, 'date_mismatch', 'critical', 3,
                       'management_outflow', outflows.id, 'Fecha de egreso inconsistente',
                       'La fecha efectiva del egreso no coincide con su transacción financiera.',
                       'Egreso #' || outflows.id, outflows.effective_date,
                       outflows.wallet_id, wallets.name, NULL::numeric, NULL::numeric, NULL::text
                FROM management_outflows AS outflows
                JOIN filtered_transactions AS transactions ON transactions.id = outflows.financial_transaction_id
                JOIN wallets ON wallets.id = outflows.wallet_id
                WHERE outflows.effective_date <> transactions.effective_date

                UNION ALL
                SELECT 'ledger_structure_mismatch:management_outflow:' || outflows.id, 'ledger_structure_mismatch', 'critical', 3,
                       'management_outflow', outflows.id, 'Estructura de egreso inconsistente',
                       'Un egreso requiere exactamente una entry negativa.',
                       'Egreso #' || outflows.id, outflows.effective_date,
                       outflows.wallet_id, wallets.name, outflows.amount, stats.entry_total, NULL::text
                FROM management_outflows AS outflows
                JOIN filtered_transactions AS transactions ON transactions.id = outflows.financial_transaction_id
                JOIN wallets ON wallets.id = outflows.wallet_id
                LEFT JOIN entry_stats AS stats ON stats.financial_transaction_id = transactions.id
                WHERE COALESCE(stats.entry_count, 0) <> 1 OR COALESCE(stats.negative_count, 0) <> 1

                UNION ALL
                SELECT 'amount_mismatch:transfer:' || transfers.id, 'amount_mismatch', 'critical', 3,
                       'transfer', transfers.id, 'Importe de transferencia inconsistente',
                       'El importe semántico no coincide con sus impactos opuestos.',
                       'Transferencia #' || transfers.id, transfers.effective_date,
                       transfers.source_wallet_id, source_wallets.name, transfers.amount,
                       COALESCE((SELECT MAX(entries.amount) FROM ledger_entries entries WHERE entries.financial_transaction_id = transactions.id), 0), NULL::text
                FROM management_transfers AS transfers
                JOIN filtered_transactions AS transactions ON transactions.id = transfers.financial_transaction_id
                JOIN wallets AS source_wallets ON source_wallets.id = transfers.source_wallet_id
                WHERE NOT EXISTS (SELECT 1 FROM ledger_entries entries WHERE entries.financial_transaction_id = transactions.id AND entries.wallet_id = transfers.source_wallet_id AND entries.amount = -transfers.amount)
                   OR NOT EXISTS (SELECT 1 FROM ledger_entries entries WHERE entries.financial_transaction_id = transactions.id AND entries.wallet_id = transfers.destination_wallet_id AND entries.amount = transfers.amount)

                UNION ALL
                SELECT 'wallet_mismatch:transfer:' || transfers.id, 'wallet_mismatch', 'critical', 3,
                       'transfer', transfers.id, 'Wallet de transferencia inconsistente',
                       'Las wallets de origen o destino no coinciden con el ledger.',
                       'Transferencia #' || transfers.id, transfers.effective_date,
                       transfers.source_wallet_id, source_wallets.name, transfers.amount, NULL::numeric, NULL::text
                FROM management_transfers AS transfers
                JOIN filtered_transactions AS transactions ON transactions.id = transfers.financial_transaction_id
                JOIN wallets AS source_wallets ON source_wallets.id = transfers.source_wallet_id
                WHERE NOT EXISTS (SELECT 1 FROM ledger_entries entries WHERE entries.financial_transaction_id = transactions.id AND entries.wallet_id = transfers.source_wallet_id)
                   OR NOT EXISTS (SELECT 1 FROM ledger_entries entries WHERE entries.financial_transaction_id = transactions.id AND entries.wallet_id = transfers.destination_wallet_id)

                UNION ALL
                SELECT 'date_mismatch:transfer:' || transfers.id, 'date_mismatch', 'critical', 3,
                       'transfer', transfers.id, 'Fecha de transferencia inconsistente',
                       'La fecha efectiva de la transferencia no coincide con su transacción financiera.',
                       'Transferencia #' || transfers.id, transfers.effective_date,
                       transfers.source_wallet_id, source_wallets.name, NULL::numeric, NULL::numeric, NULL::text
                FROM management_transfers AS transfers
                JOIN filtered_transactions AS transactions ON transactions.id = transfers.financial_transaction_id
                JOIN wallets AS source_wallets ON source_wallets.id = transfers.source_wallet_id
                WHERE transfers.effective_date <> transactions.effective_date

                UNION ALL
                SELECT 'ledger_structure_mismatch:transfer:' || transfers.id, 'ledger_structure_mismatch', 'critical', 3,
                       'transfer', transfers.id, 'Estructura de transferencia inconsistente',
                       'Una transferencia requiere dos entries opuestas cuya suma sea cero.',
                       'Transferencia #' || transfers.id, transfers.effective_date,
                       transfers.source_wallet_id, source_wallets.name, 0::numeric, stats.entry_total, NULL::text
                FROM management_transfers AS transfers
                JOIN filtered_transactions AS transactions ON transactions.id = transfers.financial_transaction_id
                JOIN wallets AS source_wallets ON source_wallets.id = transfers.source_wallet_id
                LEFT JOIN entry_stats AS stats ON stats.financial_transaction_id = transactions.id
                WHERE COALESCE(stats.entry_count, 0) <> 2 OR COALESCE(stats.positive_count, 0) <> 1
                   OR COALESCE(stats.negative_count, 0) <> 1 OR COALESCE(stats.entry_total, 0) <> 0

                UNION ALL
                SELECT 'amount_mismatch:distribution:' || distributions.id, 'amount_mismatch', 'critical', 3,
                       'distribution', distributions.id, 'Importe de distribución inconsistente',
                       'El importe no coincide con el débito de origen, los snapshots o el pago vinculado.',
                       'Distribución #' || distributions.id, distributions.effective_date,
                       distributions.source_wallet_id, wallets.name, distributions.amount,
                       COALESCE(stats.allocation_total, 0), NULL::text
                FROM management_distributions AS distributions
                JOIN filtered_transactions AS transactions ON transactions.id = distributions.financial_transaction_id
                JOIN wallets ON wallets.id = distributions.source_wallet_id
                LEFT JOIN allocation_stats AS stats ON stats.management_distribution_id = distributions.id
                LEFT JOIN management_order_payments AS payments ON payments.id = distributions.management_order_payment_id
                WHERE distributions.amount <> COALESCE(stats.allocation_total, 0)
                   OR (payments.id IS NOT NULL AND distributions.amount <> payments.amount)
                   OR NOT EXISTS (SELECT 1 FROM ledger_entries entries WHERE entries.financial_transaction_id = transactions.id AND entries.wallet_id = distributions.source_wallet_id AND entries.amount = -distributions.amount)

                UNION ALL
                SELECT 'wallet_mismatch:distribution:' || distributions.id, 'wallet_mismatch', 'critical', 3,
                       'distribution', distributions.id, 'Wallet de distribución inconsistente',
                       'La wallet de origen no coincide con el débito o con el pago vinculado.',
                       'Distribución #' || distributions.id, distributions.effective_date,
                       distributions.source_wallet_id, wallets.name, distributions.amount, NULL::numeric, NULL::text
                FROM management_distributions AS distributions
                JOIN filtered_transactions AS transactions ON transactions.id = distributions.financial_transaction_id
                JOIN wallets ON wallets.id = distributions.source_wallet_id
                LEFT JOIN management_order_payments AS payments ON payments.id = distributions.management_order_payment_id
                WHERE NOT EXISTS (SELECT 1 FROM ledger_entries entries WHERE entries.financial_transaction_id = transactions.id AND entries.wallet_id = distributions.source_wallet_id AND entries.amount = -distributions.amount)
                   OR (payments.id IS NOT NULL AND distributions.source_wallet_id <> payments.destination_wallet_id)

                UNION ALL
                SELECT 'date_mismatch:distribution:' || distributions.id, 'date_mismatch', 'critical', 3,
                       'distribution', distributions.id, 'Fecha de distribución inconsistente',
                       'La fecha no coincide con su transacción financiera o con el pago vinculado.',
                       'Distribución #' || distributions.id, distributions.effective_date,
                       distributions.source_wallet_id, wallets.name, NULL::numeric, NULL::numeric, NULL::text
                FROM management_distributions AS distributions
                JOIN filtered_transactions AS transactions ON transactions.id = distributions.financial_transaction_id
                JOIN wallets ON wallets.id = distributions.source_wallet_id
                LEFT JOIN management_order_payments AS payments ON payments.id = distributions.management_order_payment_id
                WHERE distributions.effective_date <> transactions.effective_date
                   OR (payments.id IS NOT NULL AND distributions.effective_date <> payments.effective_date)

                UNION ALL
                SELECT 'ledger_structure_mismatch:distribution:' || distributions.id, 'ledger_structure_mismatch', 'critical', 3,
                       'distribution', distributions.id, 'Estructura de distribución inconsistente',
                       'La distribución requiere un débito, un crédito por snapshot y suma total cero.',
                       'Distribución #' || distributions.id, distributions.effective_date,
                       distributions.source_wallet_id, wallets.name, 0::numeric, entry_stats.entry_total, NULL::text
                FROM management_distributions AS distributions
                JOIN filtered_transactions AS transactions ON transactions.id = distributions.financial_transaction_id
                JOIN wallets ON wallets.id = distributions.source_wallet_id
                LEFT JOIN allocation_stats ON allocation_stats.management_distribution_id = distributions.id
                LEFT JOIN entry_stats ON entry_stats.financial_transaction_id = transactions.id
                WHERE COALESCE(entry_stats.entry_count, 0) <> COALESCE(allocation_stats.allocation_count, 0) + 1
                   OR COALESCE(entry_stats.negative_count, 0) <> 1
                   OR COALESCE(entry_stats.positive_count, 0) <> COALESCE(allocation_stats.allocation_count, 0)
                   OR COALESCE(entry_stats.entry_total, 0) <> 0
                   OR COALESCE(allocation_stats.bps_total, 0) <> 10000
                   OR EXISTS (
                       SELECT 1 FROM management_distribution_allocations allocations
                       WHERE allocations.management_distribution_id = distributions.id
                         AND NOT EXISTS (
                             SELECT 1 FROM ledger_entries entries
                             WHERE entries.financial_transaction_id = transactions.id
                               AND entries.wallet_id = allocations.wallet_id
                               AND entries.amount = allocations.amount
                         )
                   )

                UNION ALL
                SELECT 'ledger_structure_mismatch:reversal:' || reversals.id, 'ledger_structure_mismatch', 'critical', 3,
                       'reversal', reversals.id, 'Reversión inconsistente',
                       'La reversión no contiene exactamente los impactos opuestos del movimiento original.',
                       'Reversión #' || reversals.id, reversals.effective_date,
                       NULL::bigint, NULL::text, NULL::numeric, NULL::numeric, NULL::text
                FROM filtered_transactions AS reversals
                JOIN financial_transactions AS originals ON originals.id = reversals.reversal_of_id
                WHERE reversals.type = 'reversal'
                  AND (
                      (SELECT COUNT(*) FROM ledger_entries entries WHERE entries.financial_transaction_id = reversals.id)
                          <> (SELECT COUNT(*) FROM ledger_entries entries WHERE entries.financial_transaction_id = originals.id)
                      OR EXISTS (
                          SELECT 1 FROM ledger_entries original_entries
                          WHERE original_entries.financial_transaction_id = originals.id
                            AND NOT EXISTS (
                                SELECT 1 FROM ledger_entries reversal_entries
                                WHERE reversal_entries.financial_transaction_id = reversals.id
                                  AND reversal_entries.wallet_id = original_entries.wallet_id
                                  AND reversal_entries.amount = -original_entries.amount
                            )
                      )
                  )

                UNION ALL
                SELECT 'missing_active_distribution_rule', 'missing_active_distribution_rule', 'warning', 2,
                       'configuration', NULL::bigint, 'No hay una regla de distribución activa',
                       'Los próximos pagos quedarán pendientes hasta activar una regla.',
                       'Configuración de distribución', NULL::date, NULL::bigint, NULL::text,
                       NULL::numeric, NULL::numeric, '/admin/management'::text
                WHERE (SELECT COUNT(*) FROM management_distribution_rules WHERE active) = 0

                UNION ALL
                SELECT 'multiple_active_distribution_rules', 'multiple_active_distribution_rules', 'critical', 3,
                       'configuration', NULL::bigint, 'Hay múltiples reglas activas',
                       'La selección automática de regla no es determinista.',
                       'Configuración de distribución', NULL::date, NULL::bigint, NULL::text,
                       1::numeric, (SELECT COUNT(*) FROM management_distribution_rules WHERE active), NULL::text
                WHERE (SELECT COUNT(*) FROM management_distribution_rules WHERE active) > 1

                UNION ALL
                SELECT 'missing_default_income_wallet', 'missing_default_income_wallet', 'warning', 2,
                       'configuration', NULL::bigint, 'No hay una wallet configurada para cobros',
                       'Los nuevos pagos de pedidos no pueden registrarse sin una wallet de cobros.',
                       'Configuración de wallets', NULL::date, NULL::bigint, NULL::text,
                       NULL::numeric, NULL::numeric, '/admin/management'::text
                WHERE (SELECT COUNT(*) FROM wallets WHERE is_order_payment_default) = 0

                UNION ALL
                SELECT 'multiple_default_income_wallets', 'multiple_default_income_wallets', 'critical', 3,
                       'configuration', NULL::bigint, 'Hay múltiples wallets de cobros',
                       'La wallet receptora de pagos no es determinista.',
                       'Configuración de wallets', NULL::date, NULL::bigint, NULL::text,
                       1::numeric, (SELECT COUNT(*) FROM wallets WHERE is_order_payment_default), NULL::text
                WHERE (SELECT COUNT(*) FROM wallets WHERE is_order_payment_default) > 1

                UNION ALL
                SELECT 'archived_default_income_wallet:' || wallets.id, 'archived_default_income_wallet', 'critical', 3,
                       'configuration', wallets.id, 'La wallet de cobros está archivada',
                       'Una wallet archivada no puede recibir pagos nuevos.',
                       wallets.name, NULL::date, wallets.id, wallets.name,
                       NULL::numeric, NULL::numeric, NULL::text
                FROM wallets WHERE is_order_payment_default AND archived_at IS NOT NULL
            ),
            findings_with_transactions AS (
                SELECT findings.*,
                       CASE
                           WHEN findings.type = 'missing_semantic_source' OR findings.entity_type = 'reversal'
                               THEN findings.entity_id
                           ELSE COALESCE(
                               payments.financial_transaction_id,
                               outflows.financial_transaction_id,
                               transfers.financial_transaction_id,
                               distributions.financial_transaction_id
                           )
                       END AS financial_transaction_id
                FROM findings
                LEFT JOIN management_order_payments AS payments
                  ON findings.entity_type = 'order_payment' AND payments.id = findings.entity_id
                LEFT JOIN management_outflows AS outflows
                  ON findings.entity_type = 'management_outflow' AND outflows.id = findings.entity_id
                LEFT JOIN management_transfers AS transfers
                  ON findings.entity_type = 'transfer' AND transfers.id = findings.entity_id
                LEFT JOIN management_distributions AS distributions
                  ON findings.entity_type = 'distribution' AND distributions.id = findings.entity_id
            )
            SELECT * FROM findings_with_transactions
            SQL;

        return [$sql, [$filters['from'], $filters['to'], $filters['wallet_id'], $filters['wallet_id']]];
    }

    /**
     * @param  Collection<int, 'warning'|'critical'>  $statuses
     * @return Collection<int, ManagementAuditWalletBalance>
     */
    private function walletBalances(Collection $statuses): Collection
    {
        return DB::table('wallets')
            ->leftJoin('ledger_entries', 'ledger_entries.wallet_id', '=', 'wallets.id')
            ->leftJoin('financial_transactions', 'financial_transactions.id', '=', 'ledger_entries.financial_transaction_id')
            ->groupBy('wallets.id', 'wallets.name', 'wallets.sort_order', 'wallets.archived_at')
            ->orderByRaw('wallets.archived_at IS NOT NULL')
            ->orderBy('wallets.sort_order')->orderBy('wallets.id')
            ->selectRaw('wallets.id, wallets.name, wallets.archived_at')
            ->selectRaw('COALESCE(SUM(ledger_entries.amount), 0) AS derived_balance')
            ->selectRaw('COUNT(DISTINCT financial_transactions.id) AS transaction_count')
            ->selectRaw('MAX(financial_transactions.effective_date) AS last_transaction_at')
            ->get()
            ->map(function (object $wallet) use ($statuses): ManagementAuditWalletBalance {
                $data = get_object_vars($wallet);
                $walletId = $this->databaseInteger($wallet, 'id');
                $status = $statuses->get($walletId, 'ok');

                return ManagementAuditWalletBalance::fromDatabaseRow((object) $data, $status);
            });
    }

    /**
     * @param  Collection<int, ManagementAuditWalletBalance>  $walletMetadata
     * @return array<string, mixed>
     */
    private function mapFinding(
        ManagementAuditFindingRow $row,
        Collection $walletMetadata,
    ): array {
        $status = match ($row->type) {
            'pending_distribution' => 'pending',
            'reversed_payment', 'reversed_distribution' => 'reversed',
            default => 'requires_review',
        };
        $wallet = $row->walletId === null ? null : $walletMetadata->get($row->walletId);

        return [
            'key' => $row->findingKey,
            'type' => $row->type,
            'severity' => $row->severity,
            'status' => $status,
            'entity_type' => $row->entityType,
            'entity_id' => $row->entityId,
            'title' => $row->title,
            'description' => $row->message,
            'entity_label' => $row->reference,
            'effective_date' => $row->effectiveDate,
            'age_days' => $row->effectiveDate === null ? null : (int) CarbonImmutable::parse($row->effectiveDate)->startOfDay()->diffInDays(CarbonImmutable::today(config('orders.timezone'))),
            'management_order_payment_id' => $row->entityType === 'order_payment' ? $row->entityId : null,
            'financial_transaction_id' => $row->financialTransactionId,
            'order_public_id' => $row->entityType === 'order_payment' && str_starts_with($row->reference, 'Pedido #')
                ? substr($row->reference, strlen('Pedido #'))
                : null,
            'wallet' => $wallet === null ? null : [
                'id' => $wallet->walletId,
                'name' => $wallet->walletName,
                'archived_at' => $wallet->archivedAt,
            ],
            'amount' => $row->amountExpected,
            'expected' => $row->amountExpected,
            'found' => $row->amountFound,
        ];
    }

    private function databaseInteger(?object $row, string $key): int
    {
        if ($row === null) {
            throw new \UnexpectedValueException("Audit query did not return column {$key}.");
        }
        $value = get_object_vars($row)[$key] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new \UnexpectedValueException("Audit query column {$key} must be an integer value.");
    }
}
