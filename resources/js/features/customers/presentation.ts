export function formatCustomerDate(value: string | null): string {
    if (!value) {
        return 'Sin pedidos';
    }

    const dateOnly = /^(\d{4})-(\d{2})-(\d{2})/.exec(value);

    if (dateOnly) {
        return `${dateOnly[3]}/${dateOnly[2]}/${dateOnly[1].slice(-2)}`;
    }

    return new Intl.DateTimeFormat('es-AR', {
        day: '2-digit',
        month: '2-digit',
        year: '2-digit',
    }).format(new Date(value));
}

export function formatCustomerCount(count: number): string {
    return `${count} ${count === 1 ? 'pedido' : 'pedidos'}`;
}
