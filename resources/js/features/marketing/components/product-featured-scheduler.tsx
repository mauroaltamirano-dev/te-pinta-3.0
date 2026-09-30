import { LoaderCircle, Plus, RefreshCw, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import type { Product } from '@/features/products/types';
import type { AdminFeaturedProduct, ProductFeaturedSchedule } from '../types';
import { DisableFeaturedProductDialog } from './disable-featured-product-dialog';
import { FeaturedScheduleList } from './featured-schedule-list';
import { MarketingEditorDialog } from './marketing-editor-dialog';

export function ProductFeaturedScheduler({
    product,
    onClose,
}: {
    product: Product;
    onClose: () => void;
}) {
    const panelRef = useRef<HTMLElement>(null);
    const [schedule, setSchedule] = useState<ProductFeaturedSchedule | null>(
        null,
    );
    const [error, setError] = useState(false);
    const [refresh, setRefresh] = useState(0);
    const [editorOpen, setEditorOpen] = useState(false);
    const [editing, setEditing] = useState<AdminFeaturedProduct | null>(null);
    const [disabling, setDisabling] = useState<AdminFeaturedProduct | null>(
        null,
    );

    useEffect(() => {
        panelRef.current?.scrollIntoView({ block: 'start' });
        const controller = new AbortController();
        fetch(`/admin/marketing/products/${product.id}/featured`, {
            signal: controller.signal,
            headers: { Accept: 'application/json' },
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Schedule unavailable');
                }

                return response.json() as Promise<ProductFeaturedSchedule>;
            })
            .then((result) => {
                setSchedule(result);
                setError(false);
            })
            .catch(() => {
                if (!controller.signal.aborted) {
                    setError(true);
                }
            });

        return () => controller.abort();
    }, [product, refresh]);

    const reload = () => setRefresh((current) => current + 1);
    const edit = (featured: AdminFeaturedProduct) => {
        setEditing(featured);
        setEditorOpen(true);
    };

    return (
        <section
            ref={panelRef}
            aria-label={`Programación destacada de ${product.name}`}
            data-testid="product-featured-scheduler"
            className="mb-6 max-w-full min-w-0 overflow-x-clip rounded-xl border border-admin-border bg-admin-surface p-4 sm:p-5"
        >
            <header className="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0">
                    <p className="text-xs font-semibold tracking-[0.14em] text-admin-accent uppercase">
                        Inicio público
                    </p>
                    <h2 className="mt-1 text-xl font-semibold text-white">
                        Destacar {product.name}
                    </h2>
                    <p className="mt-1 max-w-2xl text-sm text-admin-text-tertiary">
                        Programá cuándo aparece en el inicio. Sólo puede haber
                        una recomendación vigente a la vez; Marketing conserva
                        las reglas y el historial.
                    </p>
                </div>
                <div className="flex w-full min-w-0 flex-wrap gap-2 sm:w-auto sm:flex-nowrap">
                    {schedule?.selectable_product && (
                        <Button
                            type="button"
                            onClick={() => {
                                setEditing(null);
                                setEditorOpen(true);
                            }}
                        >
                            <Plus aria-hidden="true" /> Nueva recomendación
                        </Button>
                    )}
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label="Cerrar programación destacada"
                        onClick={onClose}
                    >
                        <X aria-hidden="true" />
                    </Button>
                </div>
            </header>

            {error ? (
                <div
                    role="alert"
                    className="mt-5 text-sm text-admin-text-secondary"
                >
                    No se pudo cargar la programación.
                    <Button
                        type="button"
                        variant="outline"
                        className="ml-3"
                        onClick={reload}
                    >
                        <RefreshCw aria-hidden="true" /> Reintentar
                    </Button>
                </div>
            ) : !schedule ? (
                <p className="mt-5 flex items-center gap-2 text-sm text-admin-text-secondary">
                    <LoaderCircle
                        className="size-4 animate-spin"
                        aria-hidden="true"
                    />
                    Cargando programación…
                </p>
            ) : (
                <div className="mt-5 grid min-w-0 gap-4 lg:grid-cols-2">
                    {!schedule.selectable_product && (
                        <p className="text-sm text-admin-text-secondary lg:col-span-2">
                            Este producto no está disponible para nuevas
                            recomendaciones. Su historial sigue visible.
                        </p>
                    )}
                    <FeaturedScheduleList
                        title="Vigente y próximas"
                        description="Programaciones de este producto."
                        records={[
                            ...(schedule.current ? [schedule.current] : []),
                            ...schedule.upcoming,
                        ]}
                        timezone={schedule.business_timezone}
                        empty="No hay recomendaciones vigentes o futuras."
                        testId="product-featured-current"
                        onEdit={edit}
                        onDisable={setDisabling}
                    />
                    <FeaturedScheduleList
                        title="Historial"
                        description="Finalizadas o desactivadas, sin eliminar registros."
                        records={schedule.history}
                        timezone={schedule.business_timezone}
                        empty="Todavía no hay historial."
                        testId="product-featured-history"
                        onEdit={edit}
                        onDisable={setDisabling}
                    />
                </div>
            )}

            {schedule && editorOpen && (
                <MarketingEditorDialog
                    open={editorOpen}
                    onOpenChange={setEditorOpen}
                    onSaved={reload}
                    featured={editing}
                    products={
                        schedule.selectable_product
                            ? [schedule.selectable_product]
                            : []
                    }
                    lockedProductId={product.id}
                    defaults={schedule.form_defaults}
                    timezone={schedule.business_timezone}
                />
            )}
            <DisableFeaturedProductDialog
                featured={disabling}
                onOpenChange={(open) => {
                    if (!open) {
                        setDisabling(null);
                    }
                }}
                onDisabled={reload}
            />
        </section>
    );
}
