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
import { managementBusinessToday } from '../presentation';
import type { ManagementMovementTypeOption, ManagementWallet } from '../types';

export function MovementFormDialog({
    businessToday,
    open,
    onOpenChange,
    wallets,
    movementTypes,
}: {
    businessToday?: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    wallets: ManagementWallet[];
    movementTypes: ManagementMovementTypeOption[];
}) {
    const form = useForm<{
        wallet_id: string;
        type: string;
        amount: string;
        effective_date: string;
        description: string;
    }>({
        wallet_id: wallets[0]?.id.toString() ?? '',
        type: movementTypes[0]?.value ?? 'manual_income',
        amount: '',
        effective_date: businessToday ?? managementBusinessToday(),
        description: '',
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post('/admin/management/transactions', {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="admin-shell max-h-[calc(100dvh-2rem)] overflow-y-auto border-admin-border bg-admin-surface sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Registrar movimiento</DialogTitle>
                    <DialogDescription>
                        Ingresá un importe positivo. Gestión determina el signo
                        según el tipo y registra un movimiento inmutable.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <div className="grid gap-2 sm:grid-cols-2 sm:gap-4">
                        <SelectField
                            id="movement-wallet"
                            label="Billetera"
                            value={form.data.wallet_id}
                            error={form.errors.wallet_id}
                            onChange={(value) =>
                                form.setData('wallet_id', value)
                            }
                        >
                            {wallets.map((wallet) => (
                                <SelectItem
                                    key={wallet.id}
                                    value={wallet.id.toString()}
                                >
                                    {wallet.name}
                                </SelectItem>
                            ))}
                        </SelectField>
                        <SelectField
                            id="movement-type"
                            label="Tipo"
                            value={form.data.type}
                            error={form.errors.type}
                            onChange={(value) => form.setData('type', value)}
                        >
                            {movementTypes.map((type) => (
                                <SelectItem key={type.value} value={type.value}>
                                    {type.label}
                                </SelectItem>
                            ))}
                        </SelectField>
                    </div>
                    <div className="grid gap-2 sm:grid-cols-2 sm:gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="movement-amount">Importe</Label>
                            <Input
                                id="movement-amount"
                                autoFocus
                                required
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
                                        ? 'movement-amount-error'
                                        : 'movement-amount-help'
                                }
                                className="h-11 border-admin-border bg-admin-canvas"
                            />
                            <p
                                id="movement-amount-help"
                                className="text-xs text-admin-text-tertiary"
                            >
                                Pesos enteros, sin signo.
                            </p>
                            <FieldError
                                id="movement-amount-error"
                                error={form.errors.amount}
                            />
                        </div>
                        <div className="grid content-start gap-2">
                            <Label htmlFor="movement-effective-date">
                                Fecha
                            </Label>
                            <Input
                                id="movement-effective-date"
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
                                        ? 'movement-date-error'
                                        : undefined
                                }
                                className="h-11 border-admin-border bg-admin-canvas"
                            />
                            <FieldError
                                id="movement-date-error"
                                error={form.errors.effective_date}
                            />
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="movement-description">Concepto</Label>
                        <Input
                            id="movement-description"
                            maxLength={180}
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                            aria-invalid={Boolean(form.errors.description)}
                            aria-describedby={
                                form.errors.description
                                    ? 'movement-description-error'
                                    : undefined
                            }
                            placeholder="Ej.: fondo inicial de caja"
                            className="h-11 border-admin-border bg-admin-canvas"
                        />
                        <FieldError
                            id="movement-description-error"
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
                            disabled={form.processing || wallets.length === 0}
                            className="h-11"
                        >
                            {form.processing && (
                                <LoaderCircle className="animate-spin" />
                            )}
                            Registrar movimiento
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
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
                    <SelectValue />
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
