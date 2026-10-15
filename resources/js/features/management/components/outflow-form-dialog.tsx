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
import { useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import type { FormEvent } from 'react';
import { managementBusinessToday } from '../presentation';
import { outflowTypeLabels } from '../presentation';
import type {
    ManagementOutflowCategory,
    ManagementOutflowType,
    ManagementOutflowTypeOption,
    ManagementWallet,
} from '../types';

export function OutflowFormDialog({
    businessToday,
    open,
    onOpenChange,
    initialType,
    wallets,
    outflowTypes,
    categories,
}: {
    businessToday?: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    initialType: ManagementOutflowType;
    wallets: ManagementWallet[];
    outflowTypes: ManagementOutflowTypeOption[];
    categories: ManagementOutflowCategory[];
}) {
    const initialCategory = categories.find(
        (category) => category.is_active && category.type === initialType,
    );
    const form = useForm({
        type: initialType,
        category_id: initialCategory?.id.toString() ?? '',
        wallet_id: wallets[0]?.id.toString() ?? '',
        description: '',
        counterparty: '',
        amount: '',
        effective_date: businessToday ?? managementBusinessToday(),
    });
    const availableCategories = categories.filter(
        (category) => category.is_active && category.type === form.data.type,
    );
    const typeLabel =
        outflowTypes.find((type) => type.value === form.data.type)?.label ??
        outflowTypeLabels[form.data.type];
    const changeType = (type: ManagementOutflowType) => {
        const firstCategory = categories.find(
            (category) => category.is_active && category.type === type,
        );

        form.setData((data) => ({
            ...data,
            type,
            category_id: firstCategory?.id.toString() ?? '',
        }));
        form.clearErrors('type', 'category_id');
    };
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post('/admin/management/outflows', {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="admin-shell dark max-h-[calc(100dvh-1rem)] overflow-y-auto border-admin-border bg-admin-surface sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        Registrar {typeLabel.toLowerCase()}
                    </DialogTitle>
                    <DialogDescription>
                        Documentá qué ocurrió. Gestión registra el impacto
                        negativo en el ledger de forma automática.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="grid gap-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <SelectField
                            id="outflow-type"
                            label="Tipo"
                            value={form.data.type}
                            error={form.errors.type}
                            onChange={(value) =>
                                changeType(value as ManagementOutflowType)
                            }
                        >
                            {outflowTypes.map((type) => (
                                <SelectItem key={type.value} value={type.value}>
                                    {type.label}
                                </SelectItem>
                            ))}
                        </SelectField>
                        <SelectField
                            id="outflow-category"
                            label="Categoría"
                            value={form.data.category_id}
                            error={form.errors.category_id}
                            onChange={(value) =>
                                form.setData('category_id', value)
                            }
                        >
                            {availableCategories.map((category) => (
                                <SelectItem
                                    key={category.id}
                                    value={category.id.toString()}
                                >
                                    {category.name}
                                </SelectItem>
                            ))}
                        </SelectField>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="outflow-description">
                            Descripción / concepto
                        </Label>
                        <Input
                            id="outflow-description"
                            autoFocus
                            required
                            maxLength={240}
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                            aria-invalid={Boolean(form.errors.description)}
                            aria-describedby={
                                form.errors.description
                                    ? 'outflow-description-error'
                                    : undefined
                            }
                            placeholder="Ej.: 10 kg de harina 000"
                            className="h-11 border-admin-border bg-admin-canvas"
                        />
                        <FieldError
                            id="outflow-description-error"
                            error={form.errors.description}
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="outflow-counterparty">
                            Comercio o proveedor{' '}
                            <span className="font-normal text-admin-text-tertiary">
                                (opcional)
                            </span>
                        </Label>
                        <Input
                            id="outflow-counterparty"
                            maxLength={160}
                            value={form.data.counterparty}
                            onChange={(event) =>
                                form.setData('counterparty', event.target.value)
                            }
                            aria-invalid={Boolean(form.errors.counterparty)}
                            aria-describedby={
                                form.errors.counterparty
                                    ? 'outflow-counterparty-error'
                                    : undefined
                            }
                            placeholder="Ej.: Distribuidora Norte"
                            className="h-11 border-admin-border bg-admin-canvas"
                        />
                        <FieldError
                            id="outflow-counterparty-error"
                            error={form.errors.counterparty}
                        />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <SelectField
                            id="outflow-wallet"
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
                        <div className="grid content-start gap-2">
                            <Label htmlFor="outflow-amount">Importe</Label>
                            <Input
                                id="outflow-amount"
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
                                        ? 'outflow-amount-error'
                                        : 'outflow-amount-help'
                                }
                                className="h-11 border-admin-border bg-admin-canvas"
                            />
                            <p
                                id="outflow-amount-help"
                                className="text-xs text-admin-text-tertiary"
                            >
                                Pesos enteros, sin signo.
                            </p>
                            <FieldError
                                id="outflow-amount-error"
                                error={form.errors.amount}
                            />
                        </div>
                    </div>

                    <div className="grid gap-2 sm:max-w-xs">
                        <Label htmlFor="outflow-effective-date">Fecha</Label>
                        <Input
                            id="outflow-effective-date"
                            required
                            type="date"
                            value={form.data.effective_date}
                            onChange={(event) =>
                                form.setData(
                                    'effective_date',
                                    event.target.value,
                                )
                            }
                            aria-invalid={Boolean(form.errors.effective_date)}
                            aria-describedby={
                                form.errors.effective_date
                                    ? 'outflow-date-error'
                                    : undefined
                            }
                            className="h-11 border-admin-border bg-admin-canvas"
                        />
                        <FieldError
                            id="outflow-date-error"
                            error={form.errors.effective_date}
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
                            disabled={
                                form.processing ||
                                wallets.length === 0 ||
                                availableCategories.length === 0
                            }
                            className="h-11"
                        >
                            {form.processing && (
                                <LoaderCircle className="animate-spin" />
                            )}
                            Registrar {typeLabel.toLowerCase()}
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
    children: React.ReactNode;
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
