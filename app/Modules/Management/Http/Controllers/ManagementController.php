<?php

namespace App\Modules\Management\Http\Controllers;

use App\Modules\Management\Actions\ApplyDistributionToOrderPayment;
use App\Modules\Management\Actions\ArchiveWallet;
use App\Modules\Management\Actions\CreateDistributionRule;
use App\Modules\Management\Actions\CreateDistributionRuleVersion;
use App\Modules\Management\Actions\CreateManagementDistribution;
use App\Modules\Management\Actions\CreateManagementOutflow;
use App\Modules\Management\Actions\CreateManagementTransfer;
use App\Modules\Management\Actions\CreateManualTransaction;
use App\Modules\Management\Actions\CreateWallet;
use App\Modules\Management\Actions\RestoreWallet;
use App\Modules\Management\Actions\ReverseFinancialTransaction;
use App\Modules\Management\Actions\SetDistributionRuleActive;
use App\Modules\Management\Actions\UpdateWallet;
use App\Modules\Management\Data\DistributionRuleData;
use App\Modules\Management\Data\ManagementDistributionData;
use App\Modules\Management\Data\ManagementOutflowData;
use App\Modules\Management\Data\ManagementTransferData;
use App\Modules\Management\Data\ManualTransactionData;
use App\Modules\Management\Data\ReversalData;
use App\Modules\Management\Data\WalletData;
use App\Modules\Management\Enums\FinancialTransactionType;
use App\Modules\Management\Enums\ManagementOutflowType;
use App\Modules\Management\Http\Requests\ManagementOverviewRequest;
use App\Modules\Management\Http\Requests\ReverseFinancialTransactionRequest;
use App\Modules\Management\Http\Requests\StoreDistributionRuleRequest;
use App\Modules\Management\Http\Requests\StoreDistributionRuleVersionRequest;
use App\Modules\Management\Http\Requests\StoreManagementDistributionRequest;
use App\Modules\Management\Http\Requests\StoreManagementOutflowRequest;
use App\Modules\Management\Http\Requests\StoreManagementTransferRequest;
use App\Modules\Management\Http\Requests\StoreManualTransactionRequest;
use App\Modules\Management\Http\Requests\StoreWalletRequest;
use App\Modules\Management\Http\Requests\UpdateDistributionRuleStatusRequest;
use App\Modules\Management\Http\Requests\UpdateWalletRequest;
use App\Modules\Management\Http\Resources\AdminDistributionRuleResource;
use App\Modules\Management\Http\Resources\AdminFinancialTransactionResource;
use App\Modules\Management\Http\Resources\AdminWalletResource;
use App\Modules\Management\Models\DistributionRule;
use App\Modules\Management\Models\FinancialTransaction;
use App\Modules\Management\Models\ManagementDistribution;
use App\Modules\Management\Models\ManagementOrderPayment;
use App\Modules\Management\Models\ManagementOutflow;
use App\Modules\Management\Models\ManagementOutflowCategory;
use App\Modules\Management\Models\ManagementTransfer;
use App\Modules\Management\Models\Wallet;
use App\Modules\Management\Queries\BuildManagementOverview;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class ManagementController
{
    public function index(ManagementOverviewRequest $request, BuildManagementOverview $overview): Response
    {
        Gate::authorize('viewAny', Wallet::class);
        Gate::authorize('viewAny', FinancialTransaction::class);
        Gate::authorize('viewAny', ManagementOutflow::class);
        Gate::authorize('viewAny', ManagementTransfer::class);
        Gate::authorize('viewAny', DistributionRule::class);
        Gate::authorize('viewAny', ManagementDistribution::class);
        $validated = $request->validated();
        $transactionId = isset($validated['transaction_id']) ? (int) $validated['transaction_id'] : null;
        $deepLinkedTransaction = $transactionId === null
            ? null
            : FinancialTransaction::query()->findOrFail($transactionId);
        $filters = [
            'period' => $validated['period'],
            'history_period' => $transactionId === null ? $validated['history_period'] : 'custom',
            'history_from' => $deepLinkedTransaction?->effective_date->toDateString() ?? $validated['history_from'],
            'history_to' => $deepLinkedTransaction?->effective_date->toDateString() ?? $validated['history_to'],
            'transaction_id' => $transactionId,
            'wallet_id' => $transactionId === null && isset($validated['wallet_id']) ? (int) $validated['wallet_id'] : null,
            'type' => $transactionId === null ? ($validated['type'] ?? null) : null,
            'category_id' => $transactionId === null && isset($validated['category_id']) ? (int) $validated['category_id'] : null,
            'from' => $deepLinkedTransaction?->effective_date->toDateString() ?? $validated['from'],
            'to' => $deepLinkedTransaction?->effective_date->toDateString() ?? $validated['to'],
        ];
        $data = $overview->execute($filters);

        return Inertia::render('admin/management/index', [
            'business_today' => CarbonImmutable::today(config('orders.timezone'))->toDateString(),
            'summary' => $data['summary'],
            'wallets' => $data['wallets']->map(fn (Wallet $wallet): array => (new AdminWalletResource($wallet))->resolve($request))->all(),
            'transactions' => $data['transactions']->through(fn (FinancialTransaction $transaction): array => (new AdminFinancialTransactionResource($transaction))->resolve($request)),
            'filters' => $filters,
            'movement_types' => collect(FinancialTransactionType::manualCases())->map(fn (FinancialTransactionType $type): array => ['value' => $type->value, 'label' => $type->label()])->all(),
            'outflow_types' => collect(ManagementOutflowType::cases())->map(fn (ManagementOutflowType $type): array => ['value' => $type->value, 'label' => $type->label()])->all(),
            'outflow_categories' => ManagementOutflowCategory::query()
                ->orderBy('type')->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn (ManagementOutflowCategory $category): array => [
                    'id' => $category->id,
                    'type' => $category->type->value,
                    'code' => $category->code,
                    'name' => $category->name,
                    'is_active' => $category->is_active,
                ])->all(),
            'distribution_rules' => DistributionRule::query()
                ->with(['versions.allocations.wallet'])
                ->orderBy('id')->get()
                ->map(fn (DistributionRule $rule): array => (new AdminDistributionRuleResource($rule))->resolve($request))->all(),
        ]);
    }

    public function storeWallet(StoreWalletRequest $request, CreateWallet $create): RedirectResponse
    {
        $create->execute(WalletData::fromRequest($request), (int) $request->user()->getAuthIdentifier());

        return back()->with('success', 'Billetera creada.');
    }

    public function updateWallet(UpdateWalletRequest $request, Wallet $wallet, UpdateWallet $update): RedirectResponse
    {
        $update->execute($wallet, WalletData::fromRequest($request), (int) $request->user()->getAuthIdentifier());

        return back()->with('success', 'Billetera actualizada.');
    }

    public function archiveWallet(Request $request, Wallet $wallet, ArchiveWallet $archive): RedirectResponse
    {
        Gate::authorize('archive', $wallet);
        $archive->execute($wallet, (int) $request->user()->getAuthIdentifier());

        return back()->with('success', 'Billetera archivada.');
    }

    public function restoreWallet(Request $request, Wallet $wallet, RestoreWallet $restore): RedirectResponse
    {
        Gate::authorize('restore', $wallet);
        $restore->execute($wallet, (int) $request->user()->getAuthIdentifier());

        return back()->with('success', 'Billetera reactivada.');
    }

    public function storeTransaction(StoreManualTransactionRequest $request, CreateManualTransaction $create): RedirectResponse
    {
        $create->execute(ManualTransactionData::fromRequest($request), (int) $request->user()->getAuthIdentifier());

        return back()->with('success', 'Movimiento registrado.');
    }

    public function storeOutflow(StoreManagementOutflowRequest $request, CreateManagementOutflow $create): RedirectResponse
    {
        $create->execute(ManagementOutflowData::fromRequest($request), (int) $request->user()->getAuthIdentifier());

        return back()->with('success', 'Egreso registrado.');
    }

    public function storeTransfer(StoreManagementTransferRequest $request, CreateManagementTransfer $create): RedirectResponse
    {
        $create->execute(ManagementTransferData::fromRequest($request), (int) $request->user()->getAuthIdentifier());

        return back()->with('success', 'Transferencia registrada.');
    }

    public function storeDistributionRule(StoreDistributionRuleRequest $request, CreateDistributionRule $create): RedirectResponse
    {
        $create->execute(DistributionRuleData::fromRequest($request), (int) $request->user()->getAuthIdentifier());

        return back()->with('success', 'Regla de distribución creada.');
    }

    public function storeDistributionRuleVersion(StoreDistributionRuleVersionRequest $request, DistributionRule $distributionRule, CreateDistributionRuleVersion $create): RedirectResponse
    {
        $create->execute($distributionRule, DistributionRuleData::allocationsFromRequest($request), (int) $request->user()->getAuthIdentifier());

        return back()->with('success', 'Nueva versión de la regla creada.');
    }

    public function updateDistributionRuleStatus(UpdateDistributionRuleStatusRequest $request, DistributionRule $distributionRule, SetDistributionRuleActive $setActive): RedirectResponse
    {
        $setActive->execute($distributionRule, $request->boolean('active'), (int) $request->user()->getAuthIdentifier());

        return back()->with('success', 'Estado de la regla actualizado.');
    }

    public function storeDistribution(StoreManagementDistributionRequest $request, CreateManagementDistribution $create): RedirectResponse
    {
        $create->execute(ManagementDistributionData::fromRequest($request), (int) $request->user()->getAuthIdentifier());

        return back()->with('success', 'Distribución registrada.');
    }

    public function distributeOrderPayment(Request $request, ManagementOrderPayment $managementOrderPayment, ApplyDistributionToOrderPayment $apply): RedirectResponse
    {
        Gate::authorize('create', ManagementDistribution::class);
        $distribution = $apply->execute($managementOrderPayment, (int) $request->user()->getAuthIdentifier());

        return back()->with(
            'success',
            $distribution === null
                ? 'El pago continúa pendiente porque no hay una regla de distribución activa.'
                : 'Pago distribuido correctamente.',
        );
    }

    public function reverseTransaction(ReverseFinancialTransactionRequest $request, FinancialTransaction $financialTransaction, ReverseFinancialTransaction $reverse): RedirectResponse
    {
        $reverse->execute($financialTransaction, ReversalData::fromRequest($request), (int) $request->user()->getAuthIdentifier());

        return back()->with('success', 'Movimiento revertido.');
    }
}
