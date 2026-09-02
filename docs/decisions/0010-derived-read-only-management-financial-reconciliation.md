# 0010: Reconciliación financiera derivada y de solo lectura

Status: accepted
Date: 2026-08-29

## Contexto

El ledger inmutable explica cada impacto sobre las billeteras y las entidades `ManagementOutflow`, `ManagementTransfer`, `ManagementDistribution` y `ManagementOrderPayment` explican el hecho administrativo que lo originó. PostgreSQL protege las invariantes de escritura conocidas, pero el equipo también necesita una vista operativa que responda si ambas capas cuentan la misma historia, detecte pagos pendientes y permita diagnosticar datos importados, evolución incompleta o corrupción sin modificar evidencia.

No toda diferencia observable es un error. Un pago sin regla activa puede permanecer pendiente de distribución; una reversión conserva el original; una billetera archivada puede participar correctamente en historia; y los Orders pagados antes de la integración no contienen datos suficientes para reconstruir un cobro financiero.

## Decisión

Construir la reconciliación como un read model administrativo derivado. Los balances se calculan exclusivamente desde `ledger_entries` y los findings se obtienen mediante joins, `EXISTS` y agregaciones sobre cabeceras, entries y orígenes semánticos existentes. No se crea `management_audit_findings`, no se almacena saldo mutable y no se persisten estados `open`, `acknowledged`, `resolved` o `ignored` en este slice.

El catálogo inicial es explícito: `pending_distribution`, `reversed_payment`, `reversed_distribution`, `missing_semantic_source`, `amount_mismatch`, `wallet_mismatch`, `date_mismatch`, `ledger_structure_mismatch`, `missing_active_distribution_rule`, `multiple_active_distribution_rules`, `missing_default_income_wallet`, `multiple_default_income_wallets` y `archived_default_income_wallet`. Las severidades se limitan a `info`, `warning` y `critical`. Un pendiente exige que el pago sea íntegro, no esté revertido y todavía no tenga distribución; una reversión o configuración ausente es una advertencia; una contradicción estructural conocida es crítica. Los tipos financieros desconocidos no se convierten en críticos hasta definir una regla verificable.

La reconciliación conoce la forma de cada origen implementado:

- `order_payment`: una entry positiva coincidente;
- `management_outflow`: una entry negativa coincidente;
- `transfer`: una salida y una entrada entre billeteras distintas, con suma cero;
- `distribution`: un débito de origen y N créditos coincidentes con snapshots, con suma cero;
- `reversal`: los mismos impactos del original con signos opuestos.

El total global del ledger no se exige en cero. Ingresos y cobros externos lo aumentan; egresos reales lo reducen; sólo transferencias y distribuciones son conservativas. La auditoría de configuración informa cero reglas activas o cero wallets predeterminadas como warning y trataría multiplicidad o una predeterminada archivada como critical, aunque las constraints normalmente impiden esos estados.

La pantalla permite acotar por `from`, `to`, `wallet_id`, `severity`, `status` y `type`. El filtro `type` acepta tanto un tipo de operación —`order_payment`, `management_outflow`, `transfer`, `distribution`, `reversal` o `configuration`— como cualquiera de los códigos de finding del catálogo. La consulta lo compara con el tipo del finding y con el tipo de su entidad semántica para permitir ambas lecturas sin crear dos parámetros solapados.

El resumen y los estados de billetera se derivan del período y la wallet antes de aplicar los filtros visuales de tipo, severidad o estado al listado paginado. Así, una selección visual no puede convertir una operación excluida en `ok` ni ocultar un warning o critical de una wallet impactada; los findings info no degradan su estado. Las consultas se agrupan y paginan, reutilizan índices existentes y cargan relaciones de forma anticipada; no ejecutan una consulta semántica por cada transacción. Abrir la auditoría no emite un log por finding.

La auditoría nunca repara. Un finding sólo navega al detalle responsable; `pending_distribution` puede abrir el reintento seguro ya existente. Corregir hechos financieros continúa requiriendo reversión explícita y una nueva operación correcta. Un eventual historial de revisión administrativa se modelará sólo si aparece una necesidad real, separado de la condición derivada.

Los Orders legacy pagados sin `ManagementOrderPayment` quedan fuera del universo reconciliable. No se backfillean, no se deducen desde `payment_status` y no generan críticos. Las billeteras archivadas y versiones históricas permanecen válidas para operaciones ya confirmadas y sus reversiones.

## Consecuencias

- El ledger continúa siendo la única fuente de saldo y la auditoría no introduce una segunda verdad mutable.
- Un finding desaparece cuando deja de cumplirse su condición, sin sincronizar filas de estado duplicadas.
- Los administradores pueden distinguir trabajo pendiente, historia compensada y contradicciones críticas sin recibir una acción destructiva.
- PostgreSQL conserva la autoridad de integridad; la auditoría agrega explicación y defensa diagnóstica, no reemplaza constraints.
- El alcance evita construir contabilidad de partida doble, conciliación bancaria, un Dashboard financiero completo o un motor genérico de reglas.
- Si en el futuro se necesitan notas, reconocimiento o resolución, habrá que diseñar una entidad de revisión explícita y sus eventos administrativos.

## Alternativas consideradas

### Persistir cada finding derivado

Rechazado porque duplicaría estado calculable, exigiría sincronización y permitiría que una fila abierta sobreviviera después de corregirse la condición real.

### Reparar automáticamente el ledger

Rechazado porque editaría o escondería evidencia, violaría la inmutabilidad y convertiría una consulta diagnóstica en una escritura financiera destructiva.

### Auditar Orders pagados directamente desde `payment_status`

Rechazado porque `paid` es mutable y los datos legacy no demuestran fecha, administrador, billetera ni regla. El universo financiero verificable comienza en `ManagementOrderPayment`.

### Exigir que el ledger global sume cero

Rechazado porque Te Pinta no implementa partida doble completa. Los ingresos y egresos externos cambian legítimamente el dinero total; la conservación se verifica sólo en operaciones internas.

### Crear un motor genérico de reconciliación

Rechazado porque ocultaría reglas de dominio conocidas detrás de configuración o metadata antes de existir varios consumidores. Las queries explícitas por tipo son más verificables y evitan falsos positivos.
