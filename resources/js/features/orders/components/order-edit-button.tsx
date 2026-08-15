import { Link, router } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

export function OrderEditButton({
    orderId,
    version,
    closed,
    compactLabel = false,
    className,
}: {
    orderId: string;
    version?: number;
    closed: boolean;
    compactLabel?: boolean;
    className?: string;
}) {
    const [open, setOpen] = useState(false);
    const [reason, setReason] = useState('');
    const [processing, setProcessing] = useState(false);

    if (!closed) {
        return (
            <Button asChild className={className}>
                <Link href={`/admin/orders/${orderId}/edit`}>
                    <Pencil /> {compactLabel ? 'Editar' : 'Editar pedido'}
                </Link>
            </Button>
        );
    }

    return (
        <>
            <Button
                type="button"
                variant="outline"
                className={className}
                onClick={() => setOpen(true)}
            >
                <Pencil /> {compactLabel ? 'Editar' : 'Habilitar edición'}
            </Button>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Habilitar edición de {orderId}
                        </DialogTitle>
                        <DialogDescription>
                            Vas a modificar un pedido cerrado. Su estado no
                            cambiará automáticamente y la acción quedará
                            registrada.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor={`closed-edit-reason-${orderId}`}>
                            Motivo breve
                        </Label>
                        <Textarea
                            id={`closed-edit-reason-${orderId}`}
                            value={reason}
                            maxLength={240}
                            onChange={(event) => setReason(event.target.value)}
                            placeholder="Indicá por qué necesitás corregirlo"
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Volver
                        </Button>
                        <Button
                            type="button"
                            disabled={
                                processing ||
                                reason.trim().length < 3 ||
                                version === undefined
                            }
                            onClick={() => {
                                setProcessing(true);
                                router.post(
                                    `/admin/orders/${orderId}/enable-edit`,
                                    {
                                        reason: reason.trim(),
                                        expected_version: version,
                                    },
                                    {
                                        onFinish: () => setProcessing(false),
                                    },
                                );
                            }}
                        >
                            Confirmar y editar
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
