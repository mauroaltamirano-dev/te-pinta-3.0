import { useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { managementBusinessToday } from '../presentation';
import { formatManagementMoney } from '../presentation';
import type {
    DistributionRule,
    DistributionRuleVersion,
    ManagementWallet,
} from '../types';

export function DistributionFormDialog({
    businessToday,
    open,
    onOpenChange,
    rules,
    wallets,
}: {
    businessToday?: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    rules: DistributionRule[];
    wallets: ManagementWallet[];
}) {
    const availableRules = rules.filter(
        (rule) => (rule.active ?? rule.is_active ?? false) && rule.versions[0],
    );
    const form = useForm({
        source_wallet_id: '',
        rule_version_id: availableRules[0]?.versions[0]?.id.toString() ?? '',
        amount: '',
        effective_date: businessToday ?? managementBusinessToday(),
        description: '',
    });
    const selection = findVersion(availableRules, form.data.rule_version_id);
    const destinationWalletIds = new Set(
        selection?.version.allocations.map((allocation) =>
            allocation.wallet.id.toString(),
        ) ?? [],
    );
    const sourceWallets = wallets.filter(
        (wallet) => !destinationWalletIds.has(wallet.id.toString()),
    );
    const sourceWallet = sourceWallets.find(
        (wallet) => wallet.id.toString() === form.data.source_wallet_id,
    );
    const amount = Number(form.data.amount);
    const preview = selection?.version
        ? calculatePreview(
              selection.version,
              Number.isInteger(amount) ? amount : 0,
          )
        : [];
    const selectRuleVersion = (versionId: string) => {
        const nextSelection = findVersion(availableRules, versionId);
        const sourceBecomesDestination =
            nextSelection?.version.allocations.some(
                (allocation) =>
                    allocation.wallet.id.toString() ===
                    form.data.source_wallet_id,
            ) ?? false;

        form.setData('rule_version_id', versionId);

        if (sourceBecomesDestination) {
            form.setData('source_wallet_id', '');
        }
    };
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            source_wallet_id: Number(data.source_wallet_id),
            rule_version_id: Number(data.rule_version_id),
            amount: Number(data.amount),
            description: data.description || null,
        }));
        form.post('/admin/management/distributions', {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="admin-shell dark max-h-[calc(100dvh-1rem)] overflow-y-auto border-admin-border bg-admin-surface sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Distribuir dinero</DialogTitle>
                    <DialogDescription>
                        Aplicá la versión vigente de una regla. El backend
                        recalcula y conserva el detalle histórico.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="distribution-source-wallet">
                            Desde wallet
                        </Label>
                        <Select
                            value={form.data.source_wallet_id}
                            onValueChange={(value) =>
                                form.setData('source_wallet_id', value)
                            }
                        >
                            <SelectTrigger
                                id="distribution-source-wallet"
                                className="h-11 w-full border-admin-border bg-admin-canvas"
                                aria-invalid={Boolean(
                                    form.errors.source_wallet_id,
                                )}
                            >
                                <SelectValue placeholder="Seleccioná una billetera origen" />
                            </SelectTrigger>
                            <SelectContent>
                                {sourceWallets.map((wallet) => (
                                    <SelectItem
                                        key={wallet.id}
                                        value={wallet.id.toString()}
                                    >
                                        {wallet.name} ·{' '}
                                        {formatManagementMoney.format(
                                            wallet.balance,
                                        )}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <FieldError error={form.errors.source_wallet_id} />
                        {selection && sourceWallets.length === 0 && (
                            <p className="text-sm text-admin-text-tertiary">
                                La regla usa todas las billeteras activas. Creá
                                o reactivá una billetera origen distinta.
                            </p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="distribution-rule">Regla</Label>
                        <Select
                            value={form.data.rule_version_id}
                            onValueChange={selectRuleVersion}
                        >
                            <SelectTrigger
                                id="distribution-rule"
                                className="h-11 w-full border-admin-border bg-admin-canvas"
                            >
                                <SelectValue placeholder="Seleccioná una regla" />
                            </SelectTrigger>
                            <SelectContent>
                                {availableRules.map((rule) => {
                                    const version = rule.versions[0];

                                    return version ? (
                                        <SelectItem
                                            key={version.id}
                                            value={version.id.toString()}
                                        >
                                            {rule.name} · v
                                            {version.version_number}
                                        </SelectItem>
                                    ) : null;
                                })}
                            </SelectContent>
                        </Select>
                        <FieldError error={form.errors.rule_version_id} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="distribution-amount">Monto</Label>
                            <Input
                                id="distribution-amount"
                                required
                                autoFocus
                                type="number"
                                min="1"
                                step="1"
                                inputMode="numeric"
                                value={form.data.amount}
                                onChange={(event) =>
                                    form.setData('amount', event.target.value)
                                }
                                aria-invalid={Boolean(form.errors.amount)}
                                className="h-11 border-admin-border bg-admin-canvas"
                            />
                            <FieldError error={form.errors.amount} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="distribution-date">
                                Fecha efectiva
                            </Label>
                            <Input
                                id="distribution-date"
                                required
                                type="date"
                                value={form.data.effective_date}
                                onChange={(event) =>
                                    form.setData(
                                        'effective_date',
                                        event.target.value,
                                    )
                                }
                                aria-invalid={Boolean(
                                    form.errors.effective_date,
                                )}
                                className="h-11 border-admin-border bg-admin-canvas"
                            />
                            <FieldError error={form.errors.effective_date} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="distribution-description">
                            Descripción{' '}
                            <span className="font-normal text-admin-text-tertiary">
                                (opcional)
                            </span>
                        </Label>
                        <Input
                            id="distribution-description"
                            maxLength={240}
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                            placeholder="Ej.: Distribución semanal"
                            className="h-11 border-admin-border bg-admin-canvas"
                        />
                        <FieldError error={form.errors.description} />
                    </div>

                    <section
                        aria-labelledby="distribution-preview-title"
                        className="rounded-lg border border-admin-border bg-admin-surface-raised p-4"
                    >
                        <div className="flex items-baseline justify-between gap-3">
                            <h3
                                id="distribution-preview-title"
                                className="font-medium text-white"
                            >
                                Vista previa
                            </h3>
                            {selection && (
                                <span className="text-xs text-admin-text-tertiary">
                                    {selection.rule.name} v
                                    {selection.version.version_number}
                                </span>
                            )}
                        </div>
                        {preview.length === 0 || amount < 1 ? (
                            <p className="mt-3 text-sm text-admin-text-tertiary">
                                Ingresá un monto para calcular la asignación.
                            </p>
                        ) : (
                            <ul className="mt-3 grid gap-2" aria-live="polite">
                                {sourceWallet && (
                                    <li className="flex items-center justify-between gap-3 border-b border-admin-border pb-2 text-sm">
                                        <span className="min-w-0 truncate text-admin-text-secondary">
                                            {sourceWallet.name}{' '}
                                            <span className="text-admin-text-tertiary">
                                                (origen)
                                            </span>
                                        </span>
                                        <strong className="shrink-0 text-destructive tabular-nums">
                                            −
                                            {formatManagementMoney.format(
                                                amount,
                                            )}
                                        </strong>
                                    </li>
                                )}
                                {preview.map((allocation) => (
                                    <li
                                        key={allocation.walletId}
                                        className="flex items-center justify-between gap-3 text-sm"
                                    >
                                        <span className="min-w-0 truncate text-admin-text-secondary">
                                            {allocation.walletName}{' '}
                                            <span className="text-admin-text-tertiary">
                                                ({allocation.percentage}%)
                                            </span>
                                        </span>
                                        <strong className="shrink-0 text-admin-accent tabular-nums">
                                            +
                                            {formatManagementMoney.format(
                                                allocation.amount,
                                            )}
                                        </strong>
                                    </li>
                                ))}
                            </ul>
                        )}
                        <p className="mt-3 text-xs text-admin-text-tertiary">
                            Se redondea hacia abajo y el remanente se asigna al
                            último destino de la regla.
                        </p>
                    </section>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            className="h-11"
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            disabled={
                                form.processing ||
                                !sourceWallet ||
                                !selection ||
                                !Number.isInteger(amount) ||
                                amount < 1
                            }
                            className="h-11"
                        >
                            {form.processing && (
                                <LoaderCircle className="animate-spin" />
                            )}
                            Confirmar distribución
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function findVersion(rules: DistributionRule[], versionId: string) {
    for (const rule of rules) {
        const version = rule.versions.find(
            (candidate) => candidate.id.toString() === versionId,
        );

        if (version) {
            return { rule, version };
        }
    }

    return null;
}

function calculatePreview(version: DistributionRuleVersion, amount: number) {
    const allocations = [...version.allocations].sort(
        (left, right) =>
            left.sort_order - right.sort_order ||
            (left.id ?? left.wallet.id) - (right.id ?? right.wallet.id),
    );
    const parts = allocations.map((allocation) =>
        Math.floor((amount * allocation.percentage_bps) / 10_000),
    );
    const remainder = amount - parts.reduce((sum, part) => sum + part, 0);

    if (parts.length > 0) {
        parts[parts.length - 1] += remainder;
    }

    return allocations.map((allocation, index) => ({
        walletId: allocation.wallet.id,
        walletName: allocation.wallet.name,
        percentage: (allocation.percentage_bps / 100).toLocaleString('es-AR', {
            maximumFractionDigits: 2,
        }),
        amount: parts[index] ?? 0,
    }));
}

function FieldError({ error }: { error?: string }) {
    return error ? (
        <p role="alert" className="text-sm text-destructive">
            {error}
        </p>
    ) : null;
}
