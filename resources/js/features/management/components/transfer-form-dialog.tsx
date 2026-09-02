import { useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
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
import { formatManagementMoney } from '../presentation';
import type { ManagementWallet } from '../types';

const localToday = () => {
    const date = new Date();
    const offset = date.getTimezoneOffset();

    return new Date(date.getTime() - offset * 60_000)
        .toISOString()
        .slice(0, 10);
};

export function TransferFormDialog({
    open,
    onOpenChange,
    wallets,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    wallets: ManagementWallet[];
}) {
    const initialSource = wallets[0]?.id.toString() ?? '';
    const initialDestination =
        wallets
            .find((wallet) => wallet.id.toString() !== initialSource)
            ?.id.toString() ?? '';
    const form = useForm({
        source_wallet_id: initialSource,
        destination_wallet_id: initialDestination,
        amount: '',
        effective_date: localToday(),
        description: '',
    });
    const destinationWallets = wallets.filter(
        (wallet) => wallet.id.toString() !== form.data.source_wallet_id,
    );
    const changeSource = (sourceWalletId: string) => {
        const destinationStillAvailable = destinationWallets.some(
            (wallet) =>
                wallet.id.toString() === form.data.destination_wallet_id &&
                wallet.id.toString() !== sourceWalletId,
        );
        const nextDestination = destinationStillAvailable
            ? form.data.destination_wallet_id
            : (wallets
                  .find((wallet) => wallet.id.toString() !== sourceWalletId)
                  ?.id.toString() ?? '');

        form.setData((data) => ({
            ...data,
            source_wallet_id: sourceWalletId,
            destination_wallet_id: nextDestination,
        }));
        form.clearErrors('source_wallet_id', 'destination_wallet_id');
    };
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post('/admin/management/transfers', {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="admin-shell dark max-h-[calc(100dvh-1rem)] overflow-y-auto border-admin-border bg-admin-surface sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Transferir dinero</DialogTitle>
                    <DialogDescription>
                        Redistribuí dinero entre billeteras. El saldo total y
                        las métricas de ingresos y egresos no cambian.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="grid gap-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <SelectField
                            id="transfer-source-wallet"
                            label="Desde"
                            value={form.data.source_wallet_id}
                            error={form.errors.source_wallet_id}
                            onChange={changeSource}
                        >
                            {wallets.map((wallet) => (
                                <WalletOption key={wallet.id} wallet={wallet} />
                            ))}
                        </SelectField>
                        <SelectField
                            id="transfer-destination-wallet"
                            label="Hacia"
                            value={form.data.destination_wallet_id}
                            error={form.errors.destination_wallet_id}
                            onChange={(value) =>
                                form.setData('destination_wallet_id', value)
                            }
                        >
                            {destinationWallets.map((wallet) => (
                                <WalletOption key={wallet.id} wallet={wallet} />
                            ))}
                        </SelectField>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid content-start gap-2">
                            <Label htmlFor="transfer-amount">Importe</Label>
                            <Input
                                id="transfer-amount"
                                required
                                autoFocus
                                type="number"
                                inputMode="numeric"
                                min="1"
                                step="1"
                                value={form.data.amount}
                                onChange={(event) =>
                                    form.setData('amount', event.target.value)
                                }
                                aria-invalid={Boolean(form.errors.amount)}
                                aria-describedby={
                                    form.errors.amount
                                        ? 'transfer-amount-error'
                                        : 'transfer-amount-help'
                                }
                                className="h-11 border-admin-border bg-admin-canvas"
                            />
                            <p
                                id="transfer-amount-help"
                                className="text-xs text-admin-text-tertiary"
                            >
                                Pesos enteros, sin signo. Se permite saldo
                                negativo.
                            </p>
                            <FieldError
                                id="transfer-amount-error"
                                error={form.errors.amount}
                            />
                        </div>
                        <div className="grid content-start gap-2">
                            <Label htmlFor="transfer-effective-date">
                                Fecha
                            </Label>
                            <Input
                                id="transfer-effective-date"
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
                                aria-describedby={
                                    form.errors.effective_date
                                        ? 'transfer-date-error'
                                        : undefined
                                }
                                className="h-11 border-admin-border bg-admin-canvas"
                            />
                            <FieldError
                                id="transfer-date-error"
                                error={form.errors.effective_date}
                            />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="transfer-description">
                            Descripción{' '}
                            <span className="font-normal text-admin-text-tertiary">
                                (opcional)
                            </span>
                        </Label>
                        <Input
                            id="transfer-description"
                            maxLength={240}
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                            aria-invalid={Boolean(form.errors.description)}
                            aria-describedby={
                                form.errors.description
                                    ? 'transfer-description-error'
                                    : undefined
                            }
                            placeholder="Ej.: Retiro de efectivo para caja"
                            className="h-11 border-admin-border bg-admin-canvas"
                        />
                        <FieldError
                            id="transfer-description-error"
                            error={form.errors.description}
                        />
                    </div>

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
                            disabled={form.processing || wallets.length < 2}
                            className="h-11"
                        >
                            {form.processing && (
                                <LoaderCircle className="animate-spin" />
                            )}
                            Confirmar transferencia
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function WalletOption({ wallet }: { wallet: ManagementWallet }) {
    return (
        <SelectItem value={wallet.id.toString()}>
            {wallet.name} — {formatManagementMoney.format(wallet.balance)}
        </SelectItem>
    );
}

function SelectField({
    id,
    label,
    value,
    error,
    onChange,
    children,
}: {
    id: string;
    label: string;
    value: string;
    error?: string;
    onChange: (value: string) => void;
    children: ReactNode;
}) {
    const errorId = error ? `${id}-error` : undefined;

    return (
        <div className="grid content-start gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Select value={value} onValueChange={onChange}>
                <SelectTrigger
                    id={id}
                    aria-invalid={Boolean(error)}
                    aria-describedby={errorId}
                    className="h-11 w-full border-admin-border bg-admin-canvas"
                >
                    <SelectValue
                        placeholder={`Seleccioná ${label.toLowerCase()}`}
                    />
                </SelectTrigger>
                <SelectContent>{children}</SelectContent>
            </Select>
            <FieldError id={errorId ?? `${id}-error`} error={error} />
        </div>
    );
}

function FieldError({ id, error }: { id: string; error?: string }) {
    if (!error) {
        return null;
    }

    return (
        <p id={id} role="alert" className="text-sm text-destructive">
            {error}
        </p>
    );
}
