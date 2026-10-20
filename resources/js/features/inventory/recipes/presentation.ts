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
