import { router } from '@inertiajs/react';
import { Archive, Pencil, RotateCcw } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import { formatManagementMoney } from '../presentation';
import type { ManagementWallet } from '../types';

export function WalletGrid({
    wallets,
    onEdit,
}: {
    wallets: ManagementWallet[];
    onEdit: (wallet: ManagementWallet) => void;
}) {
    const [archiving, setArchiving] = useState<ManagementWallet | null>(null);
    const [processingId, setProcessingId] = useState<number | null>(null);
    const activeWallets = wallets.filter((wallet) => !wallet.archived_at);
    const archivedWallets = wallets.filter((wallet) => wallet.archived_at);
    const restore = (wallet: ManagementWallet) => {
        setProcessingId(wallet.id);
        router.post(
            `/admin/management/wallets/${wallet.id}/restore`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setProcessingId(null),
            },
        );
    };
    const archive = () => {
        if (!archiving) {
            return;
        }

        setProcessingId(archiving.id);
        router.post(
            `/admin/management/wallets/${archiving.id}/archive`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => setArchiving(null),
                onFinish: () => setProcessingId(null),
            },
        );
    };

    return (
        <>
            {activeWallets.length === 0 ? (
                <p
                    role="status"
                    className="rounded-lg border border-dashed border-admin-border px-5 py-10 text-center text-sm text-admin-text-tertiary"
                >
                    Todavía no hay billeteras activas. Creá una para registrar
                    el primer movimiento.
                </p>
            ) : (
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    {activeWallets.map((wallet) => (
                        <WalletCard
                            key={wallet.id}
                            wallet={wallet}
                            onEdit={() => onEdit(wallet)}
                            onArchive={() => setArchiving(wallet)}
                        />
                    ))}
                </div>
            )}

            {archivedWallets.length > 0 && (
                <section className="mt-6 border-t border-admin-border pt-5">
                    <h3 className="text-sm font-semibold text-admin-text-secondary">
                        Billeteras archivadas
                    </h3>
                    <p className="mt-1 text-xs text-admin-text-tertiary">
                        Permanecen visibles para preservar la lectura histórica.
                    </p>
                    <div className="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        {archivedWallets.map((wallet) => (
                            <article
                                key={wallet.id}
                                className="rounded-lg border border-admin-border bg-admin-canvas/55 p-4"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <p className="truncate font-medium text-admin-text-secondary">
                                            {wallet.name}
                                        </p>
                                        <p className="mt-2 text-xl font-semibold text-admin-text-primary tabular-nums">
                                            {formatManagementMoney.format(
                                                wallet.balance,
                                            )}
                                        </p>
                                    </div>
                                    <span className="rounded-full border border-admin-border px-2 py-1 text-xs text-admin-text-tertiary">
                                        Archivada
                                    </span>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => restore(wallet)}
                                    disabled={processingId === wallet.id}
                                    className="mt-4 h-11 w-full"
                                >
                                    <RotateCcw /> Reactivar
                                </Button>
                            </article>
                        ))}
                    </div>
                </section>
            )}

            <Dialog
                open={Boolean(archiving)}
                onOpenChange={(open) => !open && setArchiving(null)}
            >
                <DialogContent className="admin-shell border-admin-border bg-admin-surface sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Archivar billetera</DialogTitle>
                        <DialogDescription>
                            {archiving?.name} dejará de aceptar movimientos
                            nuevos. Su saldo e historial no se modificarán.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setArchiving(null)}
                            className="h-11"
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={archive}
                            disabled={processingId === archiving?.id}
                            className="h-11"
                        >
                            Archivar billetera
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

function WalletCard({
    wallet,
    onEdit,
    onArchive,
}: {
    wallet: ManagementWallet;
    onEdit: () => void;
    onArchive: () => void;
}) {
    return (
        <article className="min-w-0 overflow-hidden rounded-lg border border-admin-border bg-admin-surface-raised p-4">
            <div className="flex min-w-0 items-center gap-2">
                <p className="min-w-0 truncate text-sm font-medium text-admin-text-secondary">
                    {wallet.name}
                </p>
                {wallet.is_order_payment_default && (
                    <Badge className="shrink-0 bg-admin-accent/15 text-admin-accent-text hover:bg-admin-accent/15">
                        Recibe cobros
                    </Badge>
                )}
            </div>
            <p
                className={cn(
                    'mt-2 text-2xl font-semibold text-admin-text-primary tabular-nums',
                    wallet.balance < 0 && 'text-destructive',
                )}
            >
                {formatManagementMoney.format(wallet.balance)}
            </p>
            <p className="mt-1 text-xs text-admin-text-tertiary">
                Saldo derivado del ledger
            </p>
            <div className="mt-4 grid grid-cols-2 gap-2">
                <Button
                    type="button"
                    variant="outline"
                    onClick={onEdit}
                    className="h-11"
                >
                    <Pencil /> Editar
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    onClick={onArchive}
                    className="h-11 text-admin-text-secondary"
                >
                    <Archive /> Archivar
                </Button>
            </div>
        </article>
    );
}
