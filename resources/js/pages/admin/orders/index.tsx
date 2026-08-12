import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { OrderStatus, PaginatedOrders } from '@/features/orders/types';

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
    midday: 'Midday',
    afternoon: 'Afternoon',
    night: 'Night',
};
export default function Orders({
    orders,
    filters,
}: {
    orders: PaginatedOrders;
    filters: {
        search: string;
        status: OrderStatus | null;
        date: string | null;
    };
}) {
    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState(filters.status ?? 'all');
    const [date, setDate] = useState(filters.date ?? '');
    const submit = (event: FormEvent) => {
        event.preventDefault();
        router.get(
            '/admin/orders',
            {
                ...(search ? { search } : {}),
                ...(status !== 'all' ? { status } : {}),
                ...(date ? { date } : {}),
            },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Orders" />
            <section className="grid gap-6">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-xs font-semibold tracking-[0.18em] text-admin-accent uppercase">
                            Order management
                        </p>
                        <h1 className="mt-1 text-2xl font-bold">Orders</h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Review, filter, and update every order.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href="/admin/orders/create">
                            <Plus /> New order
                        </Link>
                    </Button>
                </header>
                <Card>
                    <CardHeader>
                        <form
                            onSubmit={submit}
                            role="search"
                            className="grid gap-2 md:grid-cols-[1fr_13rem_11rem_auto]"
                        >
                            <div className="relative">
                                <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    aria-label="Search orders or customers"
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Order ID or customer"
                                    className="pl-9"
                                />
                            </div>
                            <Select value={status} onValueChange={setStatus}>
                                <SelectTrigger aria-label="Filter by status">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All statuses
                                    </SelectItem>
                                    {[
                                        'pending_confirmation',
                                        'waiting',
                                        'prepared',
                                        'delivered',
                                        'cancelled',
                                    ].map((value) => (
                                        <SelectItem key={value} value={value}>
                                            {labels[value]}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Input
                                type="date"
                                aria-label="Filter by delivery date"
                                value={date}
                                onChange={(event) =>
                                    setDate(event.target.value)
                                }
                            />
                            <Button type="submit" variant="outline">
                                Filter
                            </Button>
                        </form>
                    </CardHeader>
                    <CardContent>
                        {orders.data.length === 0 ? (
                            <p
                                role="status"
                                className="py-12 text-center text-muted-foreground"
                            >
                                No orders match these filters.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead className="border-b text-muted-foreground">
                                        <tr>
                                            {[
                                                'Order ID',
                                                'Customer',
                                                'Delivery',
                                                'Total',
                                                'Status',
                                                'Payment',
                                            ].map((heading) => (
                                                <th
                                                    key={heading}
                                                    scope="col"
                                                    className="px-3 py-3"
                                                >
                                                    {heading}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {orders.data.map((order) => (
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
                                                    <Link
                                                        href={`/admin/customers/${order.customer.id}`}
                                                        className="hover:underline"
                                                    >
                                                        {order.customer.name}
                                                    </Link>
                                                </td>
                                                <td className="px-3 py-4">
                                                    {order.delivery_date} ·{' '}
                                                    {
                                                        labels[
                                                            order
                                                                .delivery_period
                                                        ]
                                                    }
                                                </td>
                                                <td className="px-3 py-4 tabular-nums">
                                                    {money.format(order.total)}
                                                </td>
                                                <td className="px-3 py-4">
                                                    <Badge variant="outline">
                                                        {labels[order.status]}
                                                    </Badge>
                                                </td>
                                                <td className="px-3 py-4">
                                                    <Badge
                                                        variant={
                                                            order.payment_status ===
                                                            'paid'
                                                                ? 'default'
                                                                : 'secondary'
                                                        }
                                                    >
                                                        {
                                                            labels[
                                                                order
                                                                    .payment_status
                                                            ]
                                                        }
                                                    </Badge>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </section>
        </>
    );
}
