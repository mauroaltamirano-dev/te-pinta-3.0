import { Head, Link, router, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { FormField } from '@/features/inventory/components/form-field';
import type { Purchase, PurchaseOptions } from '@/features/purchases/types';
import { formatMoney as money } from '@/lib/format';
interface Props extends PurchaseOptions {
    filters: {
        from?: string;
        to?: string;
        wallet_id?: number;
        search?: string;
        status?: string;
    };
    purchases: {
        data: Purchase[];
        current_page: number;
        last_page: number;
        total: number;
    };
}
export default function Purchases({ purchases, filters, wallets }: Props) {
    const form = useForm({
        from: filters.from ?? '',
        to: filters.to ?? '',
        wallet_id: String(filters.wallet_id ?? ''),
        search: filters.search ?? '',
        status: filters.status ?? '',
    });
    const visit = (page = 1) =>
        router.get(
            '/admin/management/purchases',
            { ...form.data, page },
            { preserveScroll: true },
        );

    return (
        <section className="grid gap-6 text-admin-text-primary">
            <Head title="Compras" />
            <Link href="/admin/management">Volver a Gestión</Link>
            <header className="flex flex-wrap justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-semibold text-balance">
                        Compras
                    </h1>
                    <p>
                        Compras detalladas con impacto financiero y, cuando
                        corresponde, físico.
                    </p>
                </div>
                <Button asChild>
                    <Link href="/admin/management/purchases/create">
                        Registrar compra
                    </Link>
                </Button>
            </header>
            <p>
                Las compras históricas simples siguen en Movimientos de Gestión.
                No se reconstruyen líneas que nunca se registraron.
            </p>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    visit();
                }}
                className="flex flex-wrap items-end gap-3"
            >
                <FormField id="from" label="Desde">
                    <Input
                        id="from"
                        type="date"
                        value={form.data.from}
                        onChange={(e) => form.setData('from', e.target.value)}
                    />
                </FormField>
                <FormField id="to" label="Hasta">
                    <Input
                        id="to"
                        type="date"
                        value={form.data.to}
                        onChange={(e) => form.setData('to', e.target.value)}
                    />
                </FormField>
                <FormField id="wallet" label="Billetera">
                    <select
                        id="wallet"
                        className="h-10 rounded-md border bg-admin-canvas px-3"
                        value={form.data.wallet_id}
                        onChange={(e) =>
                            form.setData('wallet_id', e.target.value)
                        }
                    >
                        <option value="">Todas</option>
                        {wallets.map((w) => (
                            <option key={w.id} value={w.id}>
                                {w.name}
                            </option>
                        ))}
                    </select>
                </FormField>
                <FormField id="search" label="Buscar">
                    <Input
                        id="search"
                        value={form.data.search}
                        onChange={(e) => form.setData('search', e.target.value)}
                    />
                </FormField>
                <FormField id="status" label="Estado">
                    <select
                        id="status"
                        className="h-10 rounded-md border bg-admin-canvas px-3"
                        value={form.data.status}
                        onChange={(e) => form.setData('status', e.target.value)}
                    >
                        <option value="">Todos</option>
                        <option value="confirmed">Confirmadas</option>
                        <option value="reversed">Revertidas</option>
                    </select>
                </FormField>
                <Button type="submit">Filtrar</Button>
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => router.get('/admin/management/purchases')}
                >
                    Limpiar
                </Button>
            </form>
            {purchases.data.length ? (
                <ul className="grid gap-3">
                    {purchases.data.map((p) => (
                        <li
                            key={p.id}
                            className="rounded-xl border border-admin-border bg-admin-surface p-4"
                        >
                            <Link
                                href={`/admin/management/purchases/${p.id}`}
                                className="font-semibold"
                            >
                                Compra #{p.id} ·{' '}
                                {p.supplier_name ?? 'Sin proveedor'}
                            </Link>
                            <p>
                                {p.effective_date} · {p.wallet.name} ·{' '}
                                {p.status === 'reversed'
                                    ? 'Revertida'
                                    : 'Confirmada'}
                            </p>
                            <p className="tabular-nums">{money(p.total)}</p>
                        </li>
                    ))}
                </ul>
            ) : (
                <p>
                    No hay compras detalladas para estos filtros. Registrá una
                    compra o revisá el período.
                </p>
            )}
            {purchases.last_page > 1 && (
                <nav aria-label="Páginas de compras" className="flex gap-3">
                    <Button
                        variant="outline"
                        disabled={purchases.current_page <= 1}
                        onClick={() => visit(purchases.current_page - 1)}
                    >
                        Anterior
                    </Button>
                    <span>
                        {purchases.current_page} / {purchases.last_page}
                    </span>
                    <Button
                        variant="outline"
                        disabled={purchases.current_page >= purchases.last_page}
                        onClick={() => visit(purchases.current_page + 1)}
                    >
                        Siguiente
                    </Button>
                </nav>
            )}
        </section>
    );
}
