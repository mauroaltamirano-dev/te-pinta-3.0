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
import { managementBusinessToday } from '../presentation';
import type { ManagementTransaction } from '../types';

export function ReversalDialog({
    businessToday,
    transaction,
    onOpenChange,
}: {
    businessToday?: string;
    transaction: ManagementTransaction | null;
    onOpenChange: (open: boolean) => void;
}) {
    const form = useForm({
        effective_date: businessToday ?? managementBusinessToday(),
        description: '',
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!transaction) {
            return;
        }

        form.post(`/admin/management/transactions/${transaction.id}/reverse`, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={Boolean(transaction)} onOpenChange={onOpenChange}>
            <DialogContent className="admin-shell border-admin-border bg-admin-surface sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {transaction?.management_distribution
                            ? 'Revertir distribución'
                            : transaction?.management_transfer
                              ? 'Revertir transferencia'
                              : 'Revertir movimiento'}
                    </DialogTitle>
                    <DialogDescription>
                        Se creará un nuevo movimiento con impactos opuestos. El
                        original permanecerá intacto en el historial.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="reversal-effective-date">Fecha</Label>
                        <Input
                            id="reversal-effective-date"
                            type="date"
                            required
                            autoFocus
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
                                    ? 'reversal-date-error'
                                    : undefined
                            }
                            className="h-11 border-admin-border bg-admin-canvas"
                        />
                        {form.errors.effective_date && (
                            <p
                                id="reversal-date-error"
                                role="alert"
                                className="text-sm text-destructive"
                            >
                                {form.errors.effective_date}
                            </p>
                        )}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="reversal-description">Concepto</Label>
                        <Input
                            id="reversal-description"
                            maxLength={180}
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                            aria-invalid={Boolean(form.errors.description)}
                            aria-describedby={
                                form.errors.description
                                    ? 'reversal-description-error'
                                    : undefined
                            }
                            placeholder="Motivo de la corrección"
                            className="h-11 border-admin-border bg-admin-canvas"
                        />
                        {form.errors.description && (
                            <p
                                id="reversal-description-error"
                                role="alert"
                                className="text-sm text-destructive"
                            >
                                {form.errors.description}
                            </p>
                        )}
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
                            variant="destructive"
                            disabled={form.processing}
                            className="h-11"
                        >
                            {form.processing && (
                                <LoaderCircle className="animate-spin" />
                            )}
                            Crear reversión
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
