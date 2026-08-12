import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Clipboard, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
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
    AdminOrder,
    OrderStatus,
    PaymentStatus,
} from '@/features/orders/types';

const money = new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    maximumFractionDigits: 0,
});
const labels: Record<string, string> = {
    pending_confirmation: 'Pending confirmation',
    waiting: 'Waiting',
    prepared: 'Prepared',
    delivered: 'Delivered',
    cancelled: 'Cancelled',
    paid: 'Paid',
    unpaid: 'Unpaid',
    raw: 'Raw',
    cooked: 'Cooked',
    delivery: 'Delivery',
    pickup: 'Pickup',
    midday: 'Midday',
    afternoon: 'Afternoon',
    night: 'Night',
    unit: 'Unit',
    half_dozen: 'Half dozen',
    dozen: 'Dozen',
    combined_dozen: 'Combined dozen',
};
export default function OrderDetail({
    order,
    summary,
}: {
    order: AdminOrder;
    summary: string;
}) {
    const [copied, setCopied] = useState(false);
    const changeStatus = (status: string) =>
        router.patch(`/admin/orders/${order.id}/status`, { status });
    const changePayment = (payment_status: string) =>
        router.patch(`/admin/orders/${order.id}/payment-status`, {
            payment_status,
        });
    const copy = async () => {
        await navigator.clipboard.writeText(summary);
        setCopied(true);
        window.setTimeout(() => setCopied(false), 2000);
    };
    const remove = () => {
        if (
            window.confirm(
                `Remove ${order.id}? The record will be soft-deleted.`,
            )
        ) {
            router.delete(`/admin/orders/${order.id}`);
        }
    };

    return (
        <>
            <Head title={order.id} />
            <section className="grid gap-6">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <Link
                            href="/admin/orders"
                            className="mb-3 inline-flex items-center gap-2 text-sm text-muted-foreground"
                        >
                            <ArrowLeft className="size-4" /> Orders
                        </Link>
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-2xl font-bold">{order.id}</h1>
                            <Badge variant="outline">
                                {labels[order.status]}
                            </Badge>
                            <Badge
                                variant={
                                    order.payment_status === 'paid'
                                        ? 'default'
                                        : 'secondary'
                                }
                            >
                                {labels[order.payment_status]}
                            </Badge>
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Historical values below come from saved snapshots.
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button type="button" variant="outline" onClick={copy}>
                            <Clipboard />
                            {copied ? 'Copied' : 'Copy detail'}
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={remove}
                        >
                            <Trash2 />
                            Remove
                        </Button>
                    </div>
                </header>
                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                    <div className="grid gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Commercial lines</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                {order.items?.map((item, index) => (
                                    <div
                                        key={`${item.pricing_mode}-${index}`}
                                        className="flex items-start justify-between gap-4 border-b pb-4"
                                    >
                                        <div>
                                            <p className="font-medium">
                                                {item.quantity} ×{' '}
                                                {labels[item.pricing_mode]}{' '}
                                                {item.product_name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {item.units_count} empanadas ·{' '}
                                                {money.format(item.unit_price)}{' '}
                                                applied price
                                            </p>
                                            {item.composition.length > 0 && (
                                                <ul className="mt-2 ml-3 text-sm text-muted-foreground">
                                                    {item.composition.map(
                                                        (part) => (
                                                            <li
                                                                key={`${part.product_name}-${part.quantity}`}
                                                            >
                                                                {
                                                                    part.product_name
                                                                }{' '}
                                                                ×{part.quantity}
                                                            </li>
                                                        ),
                                                    )}
                                                </ul>
                                            )}
                                            {item.savings > 0 && (
                                                <p className="mt-1 text-xs text-emerald-500">
                                                    Savings:{' '}
                                                    {money.format(item.savings)}
                                                </p>
                                            )}
                                        </div>
                                        <span className="tabular-nums">
                                            {money.format(item.subtotal)}
                                        </span>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                        {Boolean(order.toppings?.length) && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Toppings</CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-3">
                                    {order.toppings?.map((item) => (
                                        <div
                                            key={item.name}
                                            className="flex justify-between text-sm"
                                        >
                                            <span>
                                                {item.name} ×{item.quantity}
                                            </span>
                                            <span>
                                                {money.format(item.subtotal)}
                                            </span>
                                        </div>
                                    ))}
                                </CardContent>
                            </Card>
                        )}
                        <Card>
                            <CardHeader>
                                <CardTitle>Backend summary</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <pre className="font-sans text-sm whitespace-pre-wrap">
                                    {summary}
                                </pre>
                            </CardContent>
                        </Card>
                    </div>
                    <aside className="grid content-start gap-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Customer</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Link
                                    href={`/admin/customers/${order.customer.id}`}
                                    className="font-medium hover:underline"
                                >
                                    {order.customer.name}
                                </Link>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>Operations</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <div className="grid gap-2">
                                    <span className="text-sm text-muted-foreground">
                                        Status
                                    </span>
                                    <Select
                                        value={order.status}
                                        onValueChange={(value) =>
                                            changeStatus(value as OrderStatus)
                                        }
                                    >
                                        <SelectTrigger aria-label="Order status">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {[
                                                'pending_confirmation',
                                                'waiting',
                                                'prepared',
                                                'delivered',
                                                'cancelled',
                                            ].map((value) => (
                                                <SelectItem
                                                    key={value}
                                                    value={value}
                                                >
                                                    {labels[value]}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="grid gap-2">
                                    <span className="text-sm text-muted-foreground">
                                        Payment
                                    </span>
                                    <Select
                                        value={order.payment_status}
                                        onValueChange={(value) =>
                                            changePayment(
                                                value as PaymentStatus,
                                            )
                                        }
                                    >
                                        <SelectTrigger aria-label="Payment status">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="unpaid">
                                                Unpaid
                                            </SelectItem>
                                            <SelectItem value="paid">
                                                Paid
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>Delivery</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-2 text-sm">
                                <p>{labels[order.delivery_type]}</p>
                                <p>
                                    {order.delivery_date} ·{' '}
                                    {labels[order.delivery_period]}
                                    {order.delivery_time
                                        ? ` · ${order.delivery_time}`
                                        : ''}
                                </p>
                                {order.delivery_address && (
                                    <p className="text-muted-foreground">
                                        {order.delivery_address}
                                    </p>
                                )}
                                <p>{labels[order.condition]}</p>
                                {order.notes && (
                                    <p className="border-t pt-2">
                                        Note: {order.notes}
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="grid gap-2 pt-6 text-sm">
                                <div className="flex justify-between">
                                    <span>Empanadas</span>
                                    <span>
                                        {money.format(order.items_subtotal)}
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span>Toppings</span>
                                    <span>
                                        {money.format(order.toppings_subtotal)}
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span>Shipping</span>
                                    <span>
                                        {money.format(order.shipping_fee)}
                                    </span>
                                </div>
                                {order.combined_dozen_savings > 0 && (
                                    <div className="flex justify-between text-emerald-500">
                                        <span>Savings</span>
                                        <span>
                                            {money.format(
                                                order.combined_dozen_savings,
                                            )}
                                        </span>
                                    </div>
                                )}
                                <div className="flex justify-between border-t pt-3 text-lg font-bold">
                                    <span>Total</span>
                                    <span>{money.format(order.total)}</span>
                                </div>
                            </CardContent>
                        </Card>
                    </aside>
                </div>
            </section>
        </>
    );
}
