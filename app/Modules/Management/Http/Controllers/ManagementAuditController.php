<?php

namespace App\Modules\Management\Http\Controllers;

use App\Modules\Management\Models\FinancialTransaction;
use App\Modules\Management\Models\Wallet;
use App\Modules\Management\Queries\BuildManagementAudit;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class ManagementAuditController
{
    public function __invoke(Request $request, BuildManagementAudit $audit): Response
    {
        Gate::authorize('viewAny', Wallet::class);
        Gate::authorize('viewAny', FinancialTransaction::class);

        $period = $request->input('period', '30_days');
        $validated = $request->validate([
            'period' => ['nullable', Rule::in(['today', '7_days', '30_days', 'custom'])],
            'wallet_id' => ['nullable', 'integer', Rule::exists('wallets', 'id')],
            'type' => ['nullable', Rule::in([
                'pending_distribution', 'reversed_payment', 'reversed_distribution',
                'missing_semantic_source', 'amount_mismatch', 'wallet_mismatch',
                'date_mismatch', 'ledger_structure_mismatch',
                'order_payment', 'management_outflow', 'transfer', 'distribution', 'reversal', 'configuration',
            ])],
            'severity' => ['nullable', Rule::in(['info', 'warning', 'critical'])],
            'status' => ['nullable', Rule::in(['ok', 'pending', 'reversed', 'requires_review'])],
            'from' => [Rule::requiredIf($period === 'custom'), 'nullable', 'date_format:Y-m-d'],
            'to' => [Rule::requiredIf($period === 'custom'), 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $today = CarbonImmutable::today(config('orders.timezone'));
        [$from, $to] = match ($period) {
            'today' => [$today->toDateString(), $today->toDateString()],
            '7_days' => [$today->subDays(6)->toDateString(), $today->toDateString()],
            'custom' => [$validated['from'], $validated['to']],
            default => [$today->subDays(29)->toDateString(), $today->toDateString()],
        };
        $filters = [
            'wallet_id' => isset($validated['wallet_id']) ? (int) $validated['wallet_id'] : null,
            'type' => $validated['type'] ?? null,
            'severity' => $validated['severity'] ?? null,
            'status' => $validated['status'] ?? null,
            'from' => $from,
            'to' => $to,
        ];
        $data = $audit->execute($filters);

        return Inertia::render('admin/management/audit', [
            'audit' => [
                'summary' => [
                    'ok' => $data['summary']['ok'],
                    'pending' => $data['summary']['pending'],
                    'warning' => $data['summary']['warnings'],
                    'critical' => $data['summary']['critical'],
                ],
                'findings' => $data['findings'],
                'wallet_balances' => $data['wallets'],
                'filters' => [...$filters, 'period' => $period],
                'options' => [
                    'wallets' => array_map(fn (array $wallet): array => [
                        'value' => $wallet['wallet_id'],
                        'label' => $wallet['wallet_name'],
                    ], $data['wallets']),
                    'types' => [
                        ['value' => 'order_payment', 'label' => 'Pagos de pedidos'],
                        ['value' => 'management_outflow', 'label' => 'Egresos'],
                        ['value' => 'transfer', 'label' => 'Transferencias'],
                        ['value' => 'distribution', 'label' => 'Distribuciones'],
                        ['value' => 'reversal', 'label' => 'Reversiones'],
                        ['value' => 'configuration', 'label' => 'Configuración'],
                    ],
                    'severities' => [
                        ['value' => 'info', 'label' => 'Información'],
                        ['value' => 'warning', 'label' => 'Warning'],
                        ['value' => 'critical', 'label' => 'Crítico'],
                    ],
                    'statuses' => [
                        ['value' => 'ok', 'label' => 'OK'],
                        ['value' => 'pending', 'label' => 'Pendiente'],
                        ['value' => 'reversed', 'label' => 'Revertido'],
                        ['value' => 'requires_review', 'label' => 'Requiere revisión'],
                    ],
                ],
            ],
        ]);
    }
}
