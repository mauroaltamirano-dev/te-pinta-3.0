import { useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import type { ManagementWallet } from '../types';

export function WalletFormDialog({
    open,
    onOpenChange,
    wallet,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    wallet?: ManagementWallet;
}) {
    const form = useForm({
        name: wallet?.name ?? '',
        is_order_payment_default: wallet?.is_order_payment_default ?? false,
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };

        if (wallet) {
            form.patch(`/admin/management/wallets/${wallet.id}`, options);

            return;
        }

        form.post('/admin/management/wallets', options);
    };
    const errorId = form.errors.name ? 'wallet-name-error' : undefined;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="admin-shell dark border-admin-border bg-admin-surface sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {wallet ? 'Editar billetera' : 'Nueva billetera'}
                    </DialogTitle>
                    <DialogDescription>
                        El nombre identifica la billetera en saldos e
                        históricos. El saldo nunca se edita desde acá.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="wallet-name">Nombre</Label>
                        <Input
                            id="wallet-name"
                            autoFocus
                            required
                            maxLength={80}
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            aria-invalid={Boolean(form.errors.name)}
                            aria-describedby={errorId}
                            className="h-11 border-admin-border bg-admin-canvas"
                        />
                        {form.errors.name && (
                            <p
                                id={errorId}
                                role="alert"
                                className="text-sm text-destructive"
                            >
                                {form.errors.name}
                            </p>
                        )}
                    </div>
                    <div className="flex items-start gap-3 rounded-lg border border-admin-border bg-admin-canvas p-3">
                        <Checkbox
                            id="wallet-order-payment-default"
                            checked={form.data.is_order_payment_default}
                            disabled={wallet?.is_order_payment_default}
                            onCheckedChange={(checked) =>
                                form.setData(
                                    'is_order_payment_default',
                                    checked === true,
                                )
                            }
                            aria-describedby="wallet-order-payment-default-help"
                            className="mt-0.5 border-admin-border data-[state=checked]:border-admin-accent data-[state=checked]:bg-admin-accent"
                        />
                        <div className="grid gap-1">
                            <Label htmlFor="wallet-order-payment-default">
                                Recibir cobros de pedidos
                            </Label>
                            <p
                                id="wallet-order-payment-default-help"
                                className="text-xs text-admin-text-tertiary"
                            >
                                {wallet?.is_order_payment_default
                                    ? 'Para cambiarla, marcá otra billetera como receptora.'
                                    : 'Será la única billetera receptora cuando un pedido se confirme como pagado.'}
                            </p>
                        </div>
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
                            disabled={form.processing}
                            className="h-11"
                        >
                            {form.processing && (
                                <LoaderCircle className="animate-spin" />
                            )}
                            {wallet ? 'Guardar cambios' : 'Crear billetera'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
