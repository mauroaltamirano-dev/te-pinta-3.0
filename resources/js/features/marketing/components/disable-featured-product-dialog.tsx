import { router } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
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
import type { AdminFeaturedProduct } from '../types';

export function DisableFeaturedProductDialog({
    featured,
    onOpenChange,
    onDisabled,
}: {
    featured: AdminFeaturedProduct | null;
    onOpenChange: (open: boolean) => void;
    onDisabled?: () => void;
}) {
    const [processing, setProcessing] = useState(false);

    const disable = () => {
        if (!featured) {
            return;
        }

        setProcessing(true);
        router.patch(
            `/admin/marketing/featured-products/${featured.id}/disable`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    onOpenChange(false);
                    onDisabled?.();
                },
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <Dialog open={Boolean(featured)} onOpenChange={onOpenChange}>
            <DialogContent className="admin-shell dark border-admin-border bg-admin-surface sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Desactivar recomendación</DialogTitle>
                    <DialogDescription>
                        La recomendación de {featured?.public_view.product.name}{' '}
                        dejará de mostrarse, pero seguirá disponible en el
                        historial.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        disabled={processing}
                        onClick={disable}
                    >
                        {processing && (
                            <LoaderCircle
                                className="animate-spin"
                                aria-hidden="true"
                            />
                        )}
                        Desactivar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
