import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { CustomerFormDialog } from '@/features/customers/components/customer-form-dialog';
import type { Customer } from '@/features/customers/types';

const money = new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    maximumFractionDigits: 0,
});
const statusLabels: Record<string, string> = {
    pending_confirmation: 'Pending confirmation',
    waiting: 'Waiting',
    prepared: 'Prepared',
    delivered: 'Delivered',
    cancelled: 'Cancelled',
};

export default function CustomerDetail({ customer }: { customer: Customer }) {
    const [editing, setEditing] = useState(false);
    const remove = () => {
        if (
            window.confirm(
                `Remove ${customer.name}? Historical orders will be preserved.`,
            )
        ) {
            router.delete(`/admin/customers/${customer.id}`);
        }
    };

    return (
        <>
            <Head title={customer.name} />
            <section className="grid gap-6">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <Link
                            href="/admin/customers"
                            className="mb-3 inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
                        >
                            <ArrowLeft className="size-4" /> Customers
                        </Link>
                        <h1 className="text-2xl font-bold">{customer.name}</h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Customer profile and historical orders.
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setEditing(true)}
                        >
                            <Pencil /> Edit
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={remove}
                        >
                            <Trash2 /> Remove
                        </Button>
                    </div>
                </header>
                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,0.55fr)]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Order history</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {!customer.orders?.length ? (
                                <p
                                    role="status"
                                    className="py-10 text-center text-sm text-muted-foreground"
                                >
                                    This customer has no orders yet.
                                </p>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-sm">
                                        <thead className="border-b text-muted-foreground">
                                            <tr>
                                                <th
                                                    className="px-3 py-3"
                                                    scope="col"
                                                >
                                                    Order ID
                                                </th>
                                                <th
                                                    className="px-3 py-3"
                                                    scope="col"
                                                >
                                                    Date
                                                </th>
                                                <th
                                                    className="px-3 py-3"
                                                    scope="col"
                                                >
                                                    Status
                                                </th>
                                                <th
                                                    className="px-3 py-3"
                                                    scope="col"
                                                >
                                                    Total
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {customer.orders.map((order) => (
                                                <tr key={order.id}>
                                                    <td className="px-3 py-4 font-medium">
                                                        <Link
                                                            href={`/admin/orders/${order.id}`}
                                                            className="hover:underline"
                                                        >
                                                            {order.id}
                                                        </Link>
                                                    </td>
                                                    <td className="px-3 py-4">
                                                        {order.date}
                                                    </td>
                                                    <td className="px-3 py-4">
                                                        <Badge variant="outline">
                                                            {statusLabels[
                                                                order.status
                                                            ] ?? order.status}
                                                        </Badge>
                                                    </td>
                                                    <td className="px-3 py-4 tabular-nums">
                                                        {money.format(
                                                            order.total,
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                    <div className="grid content-start gap-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Contact</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4 text-sm">
                                <div>
                                    <p className="text-muted-foreground">
                                        Phone
                                    </p>
                                    <p>{customer.phone ?? 'Not provided'}</p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground">
                                        Address
                                    </p>
                                    <p className="whitespace-pre-wrap">
                                        {customer.address ?? 'Not provided'}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>Private notes</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm whitespace-pre-wrap">
                                    {customer.notes ?? 'No private notes.'}
                                </p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    Effective historical spend
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-2xl font-bold tabular-nums">
                                    {money.format(customer.historical_spend)}
                                </p>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Cancelled orders are excluded.
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </section>
            {editing && (
                <CustomerFormDialog
                    open={editing}
                    onOpenChange={setEditing}
                    customer={customer}
                />
            )}
        </>
    );
}
