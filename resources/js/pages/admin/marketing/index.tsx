import { Head } from '@inertiajs/react';
import {
    BadgePercent,
    Boxes,
    Megaphone,
    Plus,
    TicketPercent,
} from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { CurrentFeaturedProduct } from '@/features/marketing/components/current-featured-product';
import { DisableFeaturedProductDialog } from '@/features/marketing/components/disable-featured-product-dialog';
import { FeaturedScheduleList } from '@/features/marketing/components/featured-schedule-list';
import { MarketingEditorDialog } from '@/features/marketing/components/marketing-editor-dialog';
import { MarketingPreview } from '@/features/marketing/components/marketing-preview';
import type {
    AdminFeaturedProduct,
    MarketingPageProps,
} from '@/features/marketing/types';

export default function Marketing({
    current,
    upcoming,
    history,
    selectable_products: selectableProducts,
    form_defaults: formDefaults,
    business_timezone: businessTimezone,
}: MarketingPageProps) {
    const [editorOpen, setEditorOpen] = useState(false);
    const [editing, setEditing] = useState<AdminFeaturedProduct | null>(null);
    const [disabling, setDisabling] = useState<AdminFeaturedProduct | null>(
        null,
    );
    const previewRecord = current ?? upcoming[0] ?? null;

    const create = () => {
        setEditing(null);
        setEditorOpen(true);
    };
    const edit = (featured: AdminFeaturedProduct) => {
        setEditing(featured);
        setEditorOpen(true);
    };

    return (
        <>
            <Head title="Marketing" />
            <section className="grid min-w-0 gap-5 font-orders sm:gap-6">
                <header className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p className="text-xs font-semibold tracking-[0.16em] text-admin-accent-text uppercase">
                            Producto destacado
                        </p>
                        <h1 className="mt-2 text-3xl font-semibold tracking-tight text-admin-text-primary">
                            Marketing
                        </h1>
                        <p className="mt-1 max-w-2xl text-sm text-admin-text-tertiary">
                            Gestioná qué productos destacás en la tienda, sin
                            modificar precios ni disponibilidad.
                        </p>
                    </div>
                    <Button
                        type="button"
                        className="h-11 self-start rounded-xl px-5 lg:self-auto"
                        onClick={create}
                    >
                        <Plus aria-hidden="true" /> Nueva recomendación
                    </Button>
                </header>

                <div className="grid min-w-0 gap-4 xl:grid-cols-12">
                    <div className="min-w-0 xl:col-span-7">
                        <CurrentFeaturedProduct
                            featured={current}
                            timezone={businessTimezone}
                            onCreate={create}
                            onEdit={edit}
                            onDisable={setDisabling}
                        />
                    </div>
                    <div className="min-w-0 xl:col-span-5">
                        <MarketingPreview featured={previewRecord} />
                    </div>

                    <div className="min-w-0 xl:col-span-7">
                        <FeaturedScheduleList
                            title="Programación"
                            description="Próximas recomendaciones, ordenadas por inicio."
                            records={upcoming}
                            timezone={businessTimezone}
                            empty="No hay próximas recomendaciones programadas."
                            testId="marketing-schedule"
                            onEdit={edit}
                            onDisable={setDisabling}
                        />
                    </div>
                    <div className="min-w-0 xl:col-span-5">
                        <FeaturedScheduleList
                            title="Anteriores"
                            description="Finalizadas o desactivadas, sin eliminar el historial."
                            records={history}
                            timezone={businessTimezone}
                            empty="Todavía no hay recomendaciones anteriores."
                            testId="marketing-history"
                            onEdit={edit}
                        />
                    </div>

                    <ComingSoon />
                </div>
            </section>

            {editorOpen && (
                <MarketingEditorDialog
                    open
                    featured={editing}
                    products={selectableProducts}
                    defaults={formDefaults}
                    timezone={businessTimezone}
                    onOpenChange={setEditorOpen}
                />
            )}
            <DisableFeaturedProductDialog
                featured={disabling}
                onOpenChange={(open) => {
                    if (!open) {
                        setDisabling(null);
                    }
                }}
            />
        </>
    );
}

function ComingSoon() {
    const items = [
        { label: 'Descuentos', icon: BadgePercent },
        { label: 'Promociones', icon: Megaphone },
        { label: 'Combos', icon: Boxes },
        { label: 'Códigos promocionales', icon: TicketPercent },
    ];

    return (
        <section className="min-w-0 rounded-xl border border-admin-border bg-admin-surface p-4 sm:p-5 xl:col-span-12">
            <h2 className="text-lg font-semibold text-admin-text-primary">
                Próximamente
            </h2>
            <p className="mt-1 text-sm text-admin-text-tertiary">
                Estas herramientas todavía no forman parte de Marketing v1.
            </p>
            <ul className="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                {items.map(({ label, icon: Icon }) => (
                    <li
                        key={label}
                        className="flex min-h-12 items-center gap-3 rounded-lg border border-admin-border bg-admin-canvas px-3 text-sm text-admin-text-secondary"
                    >
                        <Icon
                            className="size-4 text-admin-text-tertiary"
                            aria-hidden="true"
                        />
                        {label}
                    </li>
                ))}
            </ul>
        </section>
    );
}
