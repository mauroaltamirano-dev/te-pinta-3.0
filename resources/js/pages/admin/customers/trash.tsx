import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, RotateCcw } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface DeletedCustomer {
    id: number;
    name: string;
    orders_count: number;
    deleted_at: string;
}

interface DeletedCustomersPage {
    data: DeletedCustomer[];
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
}

export default function CustomerTrash({
    customers,
}: {
    customers: DeletedCustomersPage;
}) {
    const [restoreError, setRestoreError] = useState<string | null>(null);
    const restore = (customer: DeletedCustomer) => {
        setRestoreError(null);
        router.patch(
            `/admin/customers/trash/${customer.id}/restore`,
            {},
            {
                onError: (errors) => {
                    if (typeof errors.restore === 'string') {
                        setRestoreError(errors.restore);
                    }
                },
            },
        );
    };

    return (
        <>
            <Head title="Customer trash" />
            <section className="mx-auto grid max-w-4xl gap-6">
                <header>
                    <Link
                        href="/admin/customers"
                        className="mb-3 inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft className="size-4" /> Back to customers
                    </Link>
                    <p className="text-xs font-semibold tracking-[0.18em] text-admin-accent uppercase">
                        Deleted records
                    </p>
                    <h1 className="mt-1 text-2xl font-bold">Customer trash</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Restoring a customer preserves every linked order and
                        its historical snapshots.
                    </p>
                </header>

                {restoreError && (
                    <p
                        role="alert"
                        className="rounded-md border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive"
                    >
                        {restoreError}
                    </p>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>
                            {customers.total} deleted customers
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {customers.data.length === 0 ? (
                            <p
                                role="status"
                                className="py-12 text-center text-sm text-muted-foreground"
                            >
                                Customer trash is empty.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead className="border-b text-muted-foreground">
                                        <tr>
                                            <th
                                                scope="col"
                                                className="px-3 py-3 font-medium"
                                            >
                                                Name
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-3 py-3 font-medium"
                                            >
                                                Linked orders
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-3 py-3 font-medium"
                                            >
                                                Action
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {customers.data.map((customer) => (
                                            <tr key={customer.id}>
                                                <td className="px-3 py-4 font-medium">
                                                    {customer.name}
                                                </td>
                                                <td className="px-3 py-4 tabular-nums">
                                                    {customer.orders_count}
                                                </td>
                                                <td className="px-3 py-4">
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            restore(customer)
                                                        }
                                                    >
                                                        <RotateCcw /> Restore{' '}
                                                        {customer.name}
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                        <nav
                            aria-label="Customer trash pages"
                            className="mt-4 flex items-center justify-between border-t pt-4"
                        >
                            <p className="text-xs text-muted-foreground">
                                {customers.total} customers
                            </p>
                            <div className="flex gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={!customers.prev_page_url}
                                    asChild={Boolean(customers.prev_page_url)}
                                >
                                    {customers.prev_page_url ? (
                                        <Link href={customers.prev_page_url}>
                                            Previous
                                        </Link>
                                    ) : (
                                        <span>Previous</span>
                                    )}
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={!customers.next_page_url}
                                    asChild={Boolean(customers.next_page_url)}
                                >
                                    {customers.next_page_url ? (
                                        <Link href={customers.next_page_url}>
                                            Next
                                        </Link>
                                    ) : (
                                        <span>Next</span>
                                    )}
                                </Button>
                            </div>
                        </nav>
                    </CardContent>
                </Card>
            </section>
        </>
    );
}
