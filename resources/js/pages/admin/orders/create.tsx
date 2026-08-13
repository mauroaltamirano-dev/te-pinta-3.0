import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { AdminOrderOptions } from '@/features/orders/admin-order-types';
import { AdminOrderDetailsFields } from '@/features/orders/components/admin-order-details-fields';
import { AdminProductSelector } from '@/features/orders/components/admin-product-selector';
import { PriceSummary } from '@/features/orders/components/price-summary';
import { useAdminOrderDraft } from '@/features/orders/hooks/use-admin-order-draft';

export default function CreateOrder({
    products,
    customers,
    addons,
    today,
}: AdminOrderOptions) {
    const draft = useAdminOrderDraft(customers, today);
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        draft.submitFullOrder();
    };

    return (
        <>
            <Head title="Nuevo pedido" />
            <section className="grid gap-6 font-orders">
                <header>
                    <Link
                        href="/admin/orders"
                        className="mb-3 inline-flex items-center gap-2 text-sm text-muted-foreground"
                    >
                        <ArrowLeft className="size-4" /> Pedidos
                    </Link>
                    <h1 className="text-2xl font-bold">Crear pedido</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Flujo completo de respaldo. El precio final siempre lo
                        determina el sistema.
                    </p>
                </header>

                <form
                    onSubmit={submit}
                    className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]"
                >
                    <div className="grid gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>1. Productos</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <AdminProductSelector
                                    products={products}
                                    draft={draft}
                                />
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>2. Datos del pedido</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <AdminOrderDetailsFields
                                    draft={draft}
                                    customers={customers}
                                    addons={addons}
                                />
                            </CardContent>
                        </Card>
                    </div>

                    <aside className="grid content-start gap-4 xl:sticky xl:top-6">
                        <h2 className="text-lg font-semibold">3. Resumen</h2>
                        <PriceSummary
                            quote={draft.quote}
                            loading={draft.quoteLoading}
                        />
                        {draft.quoteError && (
                            <p
                                role="alert"
                                className="text-sm text-destructive"
                            >
                                No pudimos calcular el precio. Revisá los
                                productos e intentá nuevamente.
                            </p>
                        )}
                        {Object.keys(draft.form.errors).length > 0 && (
                            <p
                                role="alert"
                                className="text-sm text-destructive"
                            >
                                Revisá los datos del pedido e intentá
                                nuevamente.
                            </p>
                        )}
                        <Button
                            type="submit"
                            size="lg"
                            disabled={
                                draft.form.processing ||
                                draft.quoteLoading ||
                                !draft.quote
                            }
                        >
                            Crear pedido
                        </Button>
                    </aside>
                </form>
            </section>
        </>
    );
}
