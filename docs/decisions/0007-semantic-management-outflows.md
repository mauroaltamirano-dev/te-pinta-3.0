# 0007: Egresos semánticos separados del ledger de Gestión

Status: accepted
Date: 2026-08-27

## Context

El ledger inmutable de Gestión registra correctamente el impacto monetario, pero una entry negativa no explica si ocurrió una compra, un gasto operativo o un retiro personal. Agregar categorías, contraparte y otros datos administrativos a `financial_transactions` mezclaría la evidencia financiera general con campos propios de un único origen.

El dominio todavía presenta poco comportamiento diferenciado entre compras, gastos y retiros. Crear tres dominios completos o una infraestructura polimórfica genérica aumentaría complejidad antes de que existan necesidades reales.

## Decision

Usar una entidad única e inmutable `ManagementOutflow` para los tipos `purchase`, `expense` y `personal_withdrawal`. La tabla `management_outflows` conserva tipo, categoría, billetera, concepto, contraparte opcional, importe positivo, fecha efectiva y administrador.

Las categorías se persisten en `management_outflow_categories`, pertenecen a un tipo, pueden desactivarse y conservan historia. El slice inicial carga un catálogo mínimo; su administración queda diferida.

Cada egreso posee una clave foránea única y restrictiva hacia una `FinancialTransaction` de tipo interno `management_outflow`. La transacción financiera posee exactamente una entry negativa coincidente. El egreso, la cabecera y la entry se crean en una única transacción PostgreSQL y las restricciones diferibles verifican el vínculo completo al confirmar.

Las correcciones reutilizan la reversión del ledger. No se actualiza ni elimina el egreso confirmado; su estado revertido se deriva de la transacción financiera original.

## Consequences

- El ledger continúa siendo la única fuente de verdad del saldo y no conoce campos de compras o proveedores.
- El historial puede presentar semántica administrativa sin interpretar descripciones o metadata JSON.
- Compra y gasto permanecen distinguibles de retiro personal para métricas futuras.
- Cada origen adicional puede definir su propia relación 1:1 sin depender de un sistema polimórfico genérico; `ManagementTransfer` aplica este patrón mediante una cabecera `transfer` y dos entries opuestas.
- Las categorías usadas se preservan; desactivarlas sólo impide operaciones nuevas.
- Cambiar datos de un egreso confirmado exige reversión y un nuevo registro correcto.
- Comprobantes, proveedores, distribución, Orders e Inventory permanecen fuera de alcance de esta decisión.

## Alternatives considered

### Guardar todo en `financial_transactions`

Rechazado porque convertiría la cabecera financiera en una unión creciente de campos opcionales específicos de cada origen.

### Usar metadata JSON o una referencia polimórfica genérica

Rechazado porque debilita la integridad referencial o introduce una abstracción amplia antes de tener varios orígenes implementados.

### Crear dominios separados para Purchase, Expense y PersonalWithdrawal

Rechazado por ahora: los tres casos comparten los mismos datos, validación y flujo financiero. Podrán separarse cuando aparezca comportamiento propio suficiente.

### Usar un enum cerrado de categorías

Rechazado porque la taxonomía operativa probablemente evolucione. Un catálogo persistido permite agregar o desactivar categorías sin reescribir la historia.
