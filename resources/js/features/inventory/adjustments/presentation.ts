export const adjustmentTypes: Record<string, string> = {
    count_correction: 'Corrección de conteo',
    waste: 'Merma',
    damage: 'Rotura / daño',
    expiry: 'Vencimiento',
    manual_adjustment: 'Ajuste manual',
    reversal: 'Reversión',
};
export const adjustmentWarnings: Record<string, string> = {
    no_change:
        'El conteo coincide con el stock actual; no se registrará un movimiento.',
    unknown_cost:
        'No existe un costo actual confiable para valorar el ingreso.',
    no_cost_history:
        'No existe historial de costo; el impacto económico es desconocido.',
    partial_valuation: 'La cobertura de costo del stock es parcial.',
    negative_stock: 'El stock resultante es negativo.',
    negative_stock_latest_basis:
        'Stock insuficiente: costo tomado de la última reposición positiva.',
    insufficient_stock:
        'Stock físico insuficiente; se conserva la base de costo ponderada.',
    unreliable_input:
        'El stock incluye costo no confiable de operaciones anteriores.',
};

export const adjustmentCoverageLabels: Record<string, string> = {
    zero: 'Sin stock',
    fully_valued: 'Valoración completa',
    partial: 'Valoración parcial',
    unknown: 'Valoración desconocida',
    negative: 'Stock negativo',
    unreliable: 'Valoración no confiable',
};
export const adjustmentCostLabels: Record<string, string> = {
    explicit: 'Costo explícito',
    estimated: 'Estimación ponderada al registrar',
    allocated: 'Costo ponderado consumido',
    unknown: 'Costo desconocido',
    reversal: 'Compensación histórica',
};
