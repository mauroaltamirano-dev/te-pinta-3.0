import { useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, LoaderCircle, Plus, Trash2 } from 'lucide-react';
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
import type { DistributionRule, ManagementWallet } from '../types';

type DraftAllocation = { wallet_id: string; percentage: string };

export function DistributionRuleDialog({
    open,
    onOpenChange,
    wallets,
    rule,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    wallets: ManagementWallet[];
    rule?: DistributionRule;
}) {
    const sourceVersion = newestVersion(rule);
    const form = useForm({
        name: rule?.name ?? '',
        allocations: sourceVersion
            ? sourceVersion.allocations
                  .filter((allocation) =>
                      wallets.some(
                          (wallet) => wallet.id === allocation.wallet.id,
                      ),
                  )
                  .map((allocation) => ({
                      wallet_id: allocation.wallet.id.toString(),
                      percentage: (allocation.percentage_bps / 100).toString(),
                  }))
            : blankAllocations(wallets),
    });
    const totalBps = form.data.allocations.reduce(
        (sum, allocation) => sum + percentageToBps(allocation.percentage),
        0,
    );
    const updateAllocation = (
        index: number,
        key: keyof DraftAllocation,
        value: string,
    ) => {
        form.setData(
            'allocations',
            form.data.allocations.map((allocation, candidate) =>
                candidate === index
                    ? { ...allocation, [key]: value }
                    : allocation,
            ),
        );
    };
    const moveAllocation = (index: number, direction: -1 | 1) => {
        const destination = index + direction;

        if (destination < 0 || destination >= form.data.allocations.length) {
            return;
        }

        const next = [...form.data.allocations];
        [next[index], next[destination]] = [next[destination], next[index]];
        form.setData('allocations', next);
    };
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const allocations = form.data.allocations.map((allocation, index) => ({
            wallet_id: Number(allocation.wallet_id),
            percentage_bps: percentageToBps(allocation.percentage),
            sort_order: index + 1,
        }));
        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };

        if (rule) {
            form.transform(() => ({ allocations }));

            form.post(
                `/admin/management/distribution-rules/${rule.id}/versions`,
                options,
            );

            return;
        }

        form.transform((data) => ({ name: data.name, allocations }));
        form.post('/admin/management/distribution-rules', options);
    };
    const valid =
        form.data.name.trim().length > 0 &&
        form.data.allocations.length > 0 &&
        form.data.allocations.every(
            (allocation) =>
                allocation.wallet_id &&
                percentageToBps(allocation.percentage) > 0,
        ) &&
        new Set(form.data.allocations.map(({ wallet_id }) => wallet_id))
            .size === form.data.allocations.length &&
        totalBps === 10_000;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="admin-shell dark max-h-[calc(100dvh-1rem)] overflow-y-auto border-admin-border bg-admin-surface sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {rule ? `Nueva versión de ${rule.name}` : 'Crear regla'}
                    </DialogTitle>
                    <DialogDescription>
                        Definí destinos activos y porcentajes. Cada versión
                        queda inmutable y debe sumar exactamente 100%.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="grid gap-5">
                    <div className="grid gap-2">
                        <Label htmlFor="distribution-rule-name">Nombre</Label>
                        <Input
                            id="distribution-rule-name"
                            required
                            autoFocus={!rule}
                            disabled={Boolean(rule)}
                            maxLength={120}
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            aria-invalid={Boolean(form.errors.name)}
                            className="h-11 border-admin-border bg-admin-canvas"
                        />
                        <FieldError error={form.errors.name} />
                    </div>

                    <fieldset className="grid gap-3">
                        <legend className="text-sm font-medium text-white">
                            Destinos
                        </legend>
                        {form.data.allocations.map((allocation, index) => (
                            <div
                                key={index}
                                className="grid gap-3 rounded-lg border border-admin-border bg-admin-surface-raised p-3 sm:grid-cols-[minmax(0,1fr)_8rem_auto] sm:items-end"
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor={`rule-wallet-${index}`}>
                                        Billetera {index + 1}
                                    </Label>
                                    <Select
                                        value={allocation.wallet_id}
                                        onValueChange={(value) =>
                                            updateAllocation(
                                                index,
                                                'wallet_id',
                                                value,
                                            )
                                        }
                                    >
                                        <SelectTrigger
                                            id={`rule-wallet-${index}`}
                                            aria-invalid={Boolean(
                                                nestedError(
                                                    form.errors,
                                                    index,
                                                    'wallet_id',
                                                ),
                                            )}
                                            className="h-11 w-full border-admin-border bg-admin-canvas"
                                        >
                                            <SelectValue placeholder="Seleccioná una billetera" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {wallets.map((wallet) => (
                                                <SelectItem
                                                    key={wallet.id}
                                                    value={wallet.id.toString()}
                                                    disabled={form.data.allocations.some(
                                                        (
                                                            candidate,
                                                            candidateIndex,
                                                        ) =>
                                                            candidateIndex !==
                                                                index &&
                                                            candidate.wallet_id ===
                                                                wallet.id.toString(),
                                                    )}
                                                >
                                                    {wallet.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor={`rule-percentage-${index}`}>
                                        Porcentaje
                                    </Label>
                                    <div className="relative">
                                        <Input
                                            id={`rule-percentage-${index}`}
                                            required
                                            type="number"
                                            min="0.01"
                                            max="100"
                                            step="0.01"
                                            inputMode="decimal"
                                            value={allocation.percentage}
                                            onChange={(event) =>
                                                updateAllocation(
                                                    index,
                                                    'percentage',
                                                    event.target.value,
                                                )
                                            }
                                            aria-invalid={Boolean(
                                                nestedError(
                                                    form.errors,
                                                    index,
                                                    'percentage_bps',
                                                ),
                                            )}
                                            className="h-11 border-admin-border bg-admin-canvas pr-8"
                                        />
                                        <span className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-admin-text-tertiary">
                                            %
                                        </span>
                                    </div>
                                </div>
                                <div className="flex gap-1">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        disabled={index === 0}
                                        aria-label={`Subir destino ${index + 1}`}
                                        onClick={() =>
                                            moveAllocation(index, -1)
                                        }
                                        className="size-11"
                                    >
                                        <ArrowUp />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        disabled={
                                            index ===
                                            form.data.allocations.length - 1
                                        }
                                        aria-label={`Bajar destino ${index + 1}`}
                                        onClick={() => moveAllocation(index, 1)}
                                        className="size-11"
                                    >
                                        <ArrowDown />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        disabled={
                                            form.data.allocations.length === 1
                                        }
                                        aria-label={`Eliminar destino ${index + 1}`}
                                        onClick={() =>
                                            form.setData(
                                                'allocations',
                                                form.data.allocations.filter(
                                                    (_, candidate) =>
                                                        candidate !== index,
                                                ),
                                            )
                                        }
                                        className="size-11 text-destructive"
                                    >
                                        <Trash2 />
                                    </Button>
                                </div>
                                <div className="sm:col-span-3">
                                    <FieldError
                                        error={
                                            nestedError(
                                                form.errors,
                                                index,
                                                'wallet_id',
                                            ) ??
                                            nestedError(
                                                form.errors,
                                                index,
                                                'percentage_bps',
                                            )
                                        }
                                    />
                                </div>
                            </div>
                        ))}
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                form.setData('allocations', [
                                    ...form.data.allocations,
                                    { wallet_id: '', percentage: '' },
                                ])
                            }
                            disabled={
                                form.data.allocations.length >= wallets.length
                            }
                            className="h-11 justify-self-start"
                        >
                            <Plus /> Agregar destino
                        </Button>
                    </fieldset>

                    <div
                        role="status"
                        className="flex items-center justify-between rounded-lg border border-admin-border bg-admin-canvas px-4 py-3 text-sm"
                    >
                        <span className="text-admin-text-secondary">
                            Total asignado
                        </span>
                        <strong
                            className={
                                totalBps === 10_000
                                    ? 'text-admin-accent'
                                    : 'text-destructive'
                            }
                        >
                            {(totalBps / 100).toLocaleString('es-AR', {
                                maximumFractionDigits: 2,
                            })}
                            %
                        </strong>
                    </div>
                    <FieldError error={form.errors.allocations} />

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            className="relative z-10 h-11"
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            disabled={!valid || form.processing}
                            className="relative z-10 h-11"
                        >
                            {form.processing && (
                                <LoaderCircle className="animate-spin" />
                            )}
                            {rule ? 'Crear nueva versión' : 'Crear regla'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function newestVersion(rule?: DistributionRule) {
    return rule?.current_version ?? rule?.latest_version ?? rule?.versions[0];
}

function blankAllocations(wallets: ManagementWallet[]): DraftAllocation[] {
    return Array.from({ length: Math.min(3, wallets.length) }, () => ({
        wallet_id: '',
        percentage: '',
    }));
}

function percentageToBps(value: string) {
    const percentage = Number(value);

    return Number.isFinite(percentage) ? Math.round(percentage * 100) : 0;
}

function nestedError(
    errors: Record<string, string | undefined>,
    index: number,
    field: 'wallet_id' | 'percentage_bps',
) {
    return errors[`allocations.${index}.${field}`];
}

function FieldError({ error }: { error?: string }) {
    return error ? (
        <p role="alert" className="text-sm text-destructive">
            {error}
        </p>
    ) : null;
}
