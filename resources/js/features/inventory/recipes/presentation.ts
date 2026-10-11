/** Inventory amounts are integer pesos, not cents. Null is not a free recipe. */
export function formatRecipeMoney(value: number | null): string {
    return value === null
        ? 'Sin costo conocido'
        : new Intl.NumberFormat('es-AR', {
              style: 'currency',
              currency: 'ARS',
              maximumFractionDigits: 0,
          }).format(value);
}
export const costCoverageLabels: Record<string, string> = {
    complete: 'Costo completo',
    partial: 'Costo incompleto',
    unknown: 'Sin costo conocido',
    unreliable: 'Valoración no confiable',
};
export const productionWarningLabels: Record<string, string> = {
    insufficient_stock:
        'Stock físico insuficiente; el costo conserva la base ponderada disponible.',
    negative_stock_latest_basis:
        'Stock insuficiente: se utilizó la última reposición positiva.',
    no_cost_history: 'No existe historial de costo para este insumo.',
    partial_valuation: 'El stock tiene cobertura de costo parcial.',
    unreliable_input:
        'El insumo contiene costos no confiables de una producción anterior.',
};
