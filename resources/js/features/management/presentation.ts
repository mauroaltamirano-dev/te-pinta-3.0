import type {
    ManagementOutflowType,
    ManagementTransaction,
    ManagementTransactionType,
} from './types';

export const formatManagementMoney = new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    maximumFractionDigits: 0,
});

export const formatManagementDate = (value: string) =>
    new Intl.DateTimeFormat('es-AR', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(`${value.slice(0, 10)}T12:00:00`));

export const transactionTypeLabels: Record<ManagementTransactionType, string> =
    {
        opening: 'Apertura',
        adjustment_increase: 'Ajuste positivo',
        adjustment_decrease: 'Ajuste negativo',
        manual_income: 'Ingreso manual',
        manual_expense: 'Egreso manual',
        management_outflow: 'Egreso de gestión',
        transfer: 'Transferencia',
        distribution: 'Distribución',
        order_payment: 'Pago de pedido',
        reversal: 'Reversión',
    };

export const outflowTypeLabels: Record<ManagementOutflowType, string> = {
    purchase: 'Compra',
    expense: 'Gasto',
    personal_withdrawal: 'Retiro personal',
};

export const transactionLabel = (transaction: ManagementTransaction) => {
    const outflowLabel = transaction.management_outflow
        ? (transaction.management_outflow.type_label ??
          outflowTypeLabels[transaction.management_outflow.type])
        : null;

    if (transaction.type === 'reversal' && outflowLabel) {
        return `Reversión de ${outflowLabel.toLowerCase()}`;
    }

    if (transaction.type === 'reversal' && transaction.management_transfer) {
        return 'Reversión de transferencia';
    }

    if (
        transaction.type === 'reversal' &&
        transaction.management_distribution
    ) {
        return 'Reversión de distribución';
    }

    if (
        transaction.type === 'reversal' &&
        transaction.management_order_payment
    ) {
        return 'Reversión de pago de pedido';
    }

    return outflowLabel ?? transactionTypeLabels[transaction.type];
};

export const administratorName = (administrator: { name: string } | string) =>
    typeof administrator === 'string' ? administrator : administrator.name;
