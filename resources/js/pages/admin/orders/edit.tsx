import { Head, router } from '@inertiajs/react';
import { ArrowLeft, LockKeyhole } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    AdminOrderEditContext,
    AdminOrderOptions,
} from '@/features/orders/admin-order-types';
import { AdminOrderDetailsFields } from '@/features/orders/components/admin-order-details-fields';
import { AdminProductSelector } from '@/features/orders/components/admin-product-selector';
import { PriceSummary } from '@/features/orders/components/price-summary';
import { useAdminOrderDraft } from '@/features/orders/hooks/use-admin-order-draft';
import { useUnsavedChangesWarning } from '@/features/orders/hooks/use-unsaved-changes-warning';
import {
    formatOrderMoney,
    orderStatusLabels,
    orderStatuses,
    paymentMethodLabels,
    paymentStatusLabels,
} from '@/features/orders/presentation';
import type { OrderStatus } from '@/features/orders/types';

interface EditOrderProps extends AdminOrderOptions {
    order: AdminOrderEditContext & { closed_edit_required?: boolean };
}

export default function EditOrder({
    order,
    products,
    customers,
    addons,
    today,
}: EditOrderProps) {
    const draft = useAdminOrderDraft(customers, today, {
        initialDraft: order.draft,
        initialQuote: order.original_quote,
        submitUrl: `/admin/orders/${order.id}`,
        submitMethod: 'put',
    });
    const allowNextVisit = useUnsavedChangesWarning(
        draft.form.isDirty && !draft.form.processing,
    );
    const cancel = () => {
        allowNextVisit();
        router.visit(`/admin/orders/${order.id}`);
    };
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        allowNextVisit();
        draft.submitFullOrder();
    };
    const updatedTotal = draft.quote?.total ?? order.total;
    const difference = updatedTotal - order.total;

    if (order.closed_edit_required) {
        return (
            <section className="mx-auto grid max-w-xl gap-6 py-12 font-orders">
                <Head title={`Edición bloqueada · ${order.id}`} />
                <Card>
                    <CardHeader>
                        <LockKeyhole className="size-8 text-warning" />
                        <CardTitle>
                            Pedido cerrado — edición bloqueada
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        <p className="text-sm text-muted-foreground">
                            Volvé al detalle y usá “Habilitar edición”. Se te
                            pedirá un motivo antes de continuar.
                        </p>
                        <Button type="button" onClick={cancel}>
                            Volver al detalle
                        </Button>
                    </CardContent>
                </Card>
            </section>
        );
    }

    return (
        <>
            <Head title={`Editar ${order.id}`} />
            <section className="grid gap-6 font-orders">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <button
                            type="button"
                            onClick={cancel}
                            className="mb-3 inline-flex items-center gap-2 text-sm text-admin-text-tertiary hover:text-admin-text-primary"
                        >
                            <ArrowLeft className="size-4" /> Volver al detalle
                        </button>
                        <p className="text-sm font-medium text-admin-accent-text">
                            Editando {order.id}
                        </p>
                        <h1 className="mt-1 text-3xl font-semibold tracking-tight text-admin-text-primary">
                            Corregir pedido
                        </h1>
                    </div>
                    <Button type="button" variant="outline" onClick={cancel}>
                        Cancelar edición
                    </Button>
                </header>

                <form
                    onSubmit={submit}
                    className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]"
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
                                    minimumDeliveryDate={null}
                                />
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>3. Estado operativo</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-2 sm:max-w-sm">
                                <Select
                                    value={
                                        draft.form.data.status ?? order.status
                                    }
                                    onValueChange={(value: OrderStatus) =>
                                        draft.form.setData('status', value)
                                    }
                                >
                                    <SelectTrigger aria-label="Estado del pedido">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {orderStatuses.map((status) => (
                                            <SelectItem
                                                key={status}
                                                value={status}
                                            >
                                                {orderStatusLabels[status]}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </CardContent>
                        </Card>
                    </div>

                    <aside className="grid content-start gap-4 xl:sticky xl:top-6">
                        <h2 className="text-lg font-semibold">
                            4. Confirmación
                        </h2>
                        <Card>
                            <CardHeader>
                                <CardTitle>Antes</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-2 text-sm">
                                <SummaryRow
                                    label="Total original"
                                    value={formatOrderMoney.format(order.total)}
                                />
                                <SummaryRow
                                    label="Estado"
                                    value={orderStatusLabels[order.status]}
                                />
                                <SummaryRow
                                    label="Pago"
                                    value={
                                        paymentStatusLabels[
                                            order.payment_status
                                        ]
                                    }
                                />
                                {order.payment_method && (
                                    <SummaryRow
                                        label="Medio de pago"
                                        value={
                                            paymentMethodLabels[
                                                order.payment_method
                                            ]
                                        }
                                    />
                                )}
                            </CardContent>
                        </Card>
                        <div>
                            <h3 className="mb-2 font-semibold">Después</h3>
                            <PriceSummary
                                quote={draft.quote}
                                loading={draft.quoteLoading}
                            />
                        </div>
                        <Card className="border-admin-accent/40">
                            <CardContent className="grid gap-2 pt-6 text-sm">
                                <SummaryRow
                                    label="Total anterior"
                                    value={formatOrderMoney.format(order.total)}
                                />
                                <SummaryRow
                                    label="Total actualizado"
                                    value={formatOrderMoney.format(
                                        updatedTotal,
                                    )}
                                />
                                <SummaryRow
                                    label="Diferencia total"
                                    value={`${difference > 0 ? '+' : difference < 0 ? '−' : ''}${formatOrderMoney.format(Math.abs(difference))}`}
                                    emphasized
                                />
                            </CardContent>
                        </Card>
                        {draft.quoteError && (
                            <p
                                role="alert"
                                className="text-sm text-destructive"
                            >
                                {draft.quoteError}
                            </p>
                        )}
                        {Object.keys(draft.form.errors).length > 0 && (
                            <div
                                role="alert"
                                className="grid gap-1 text-sm text-destructive"
                            >
                                {Object.values(draft.form.errors).map(
                                    (error) => (
                                        <p key={error}>{error}</p>
                                    ),
                                )}
                            </div>
                        )}
                        <Button
                            type="submit"
                            size="lg"
                            disabled={
                                draft.form.processing ||
                                draft.quoteLoading ||
                                !draft.quote ||
                                !draft.paymentReady
                            }
                        >
                            Guardar cambios
                        </Button>
                    </aside>
                </form>
            </section>
        </>
    );
}

function SummaryRow({
    label,
    value,
    emphasized = false,
}: {
    label: string;
    value: string;
    emphasized?: boolean;
}) {
    return (
        <div
            className={`flex justify-between gap-4 ${emphasized ? 'border-t pt-3 font-semibold text-admin-accent-text' : ''}`}
        >
            <span>{label}</span>
            <span className="text-right tabular-nums">{value}</span>
        </div>
    );
}
