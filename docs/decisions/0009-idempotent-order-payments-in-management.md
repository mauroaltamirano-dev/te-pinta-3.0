# 0009: Pagos de Orders como orígenes financieros idempotentes

Status: accepted
Date: 2026-08-28
Updated: 2026-08-29

## Contexto

Orders ya permite marcar un pedido como `paid` desde la creación administrativa, la edición completa o el control rápido. Ese estado es operativo y mutable: puede volver a `unpaid`, coexistir con una cancelación y no conserva por sí mismo la fecha ni el destino financiero. Usarlo como única evidencia obligaría a deducir historia desde estado actual y permitiría duplicar ingresos ante reintentos o eventos repetidos.

Management necesita responder qué Order originó el dinero sin agregar una FK específica a toda cabecera financiera ni introducir un sistema polimórfico general. También debe impedir que un fallo entre el cambio operativo y el ledger deje ambos módulos inconsistentes.

## Decisión

Modelar el cobro como `ManagementOrderPayment`, una entidad financiera inmutable con `order_id` único, billetera destino, importe, fecha efectiva, administrador y relación 1:1 con una `FinancialTransaction` de tipo `order_payment`. Su ledger contiene exactamente una entry positiva por el total final del Order.

Orders emite `OrderPaymentConfirmed(orderId, amount, confirmedOn, adminId)` solamente al transicionar de un estado no pagado a `paid`. Los tres caminos administrativos emiten el mismo contrato. Un listener síncrono de Management invoca `CreateManagementOrderPayment` dentro de la misma transacción; si falla el registro financiero, también falla la confirmación operativa.

Management elige una única billetera activa marcada `is_order_payment_default`. Un índice único parcial impide dos predeterminadas y un check impide archivar la seleccionada. La fecha efectiva es el día calendario del negocio al confirmar, no la creación ni la entrega del pedido.

La idempotencia se protege con `UNIQUE(order_id)`, no con texto, payload ni confianza en que el evento llegará una sola vez. Repetir la integración devuelve el origen existente sin crear otra cabecera o entry.

Cuando existe una regla de distribución activa, Management aplica automáticamente su versión más reciente mediante `ApplyDistributionToOrderPayment`. La billetera receptora del pago es obligatoriamente la billetera origen del reparto; el importe y la fecha efectiva también se conservan. `created_by` identifica al administrador que aplicó la distribución y puede diferir del creador del pago cuando se resuelve un pendiente mediante reintento. La distribución reutiliza `CreateManagementDistribution`, por lo que crea una entry negativa por el total, N créditos cuyo total coincide, snapshots relacionales y una cabecera `distribution` con neto cero.

El estado activo de la regla es la única fuente de selección y es globalmente exclusivo. La distribución conserva una FK a la versión concreta: activar o versionar otra regla no recalcula pagos históricos.

`management_distributions.management_order_payment_id` es anulable para preservar distribuciones manuales y único para imponer una relación pago → distribución de `0..1`. El bloqueo de la fila del pago y esa restricción estructural hacen idempotente el reparto ante reintentos y concurrencia; no se confía sólo en una consulta previa.

La ausencia de regla activa deja el pago como **Pendiente de distribución**. El ingreso externo es un hecho válido por sí mismo, queda visible y puede reintentarse desde Management después de configurar una regla. No se eligen porcentajes ni reglas por orden o nombre. Una falla técnica tras seleccionar una regla sí se propaga: como el listener es síncrono, la transición `paid`, el ingreso y la distribución revierten juntos.

Cancelar o volver a `unpaid` no revierte automáticamente el hecho. Las correcciones usan `ReverseFinancialTransaction` y preservan el original. Si el pago tiene una distribución aplicada, se adopta la estrategia C: primero se revierte explícitamente la distribución y después el pago; el orden contrario se rechaza. Esto mantiene la trazabilidad y evita retirar el ingreso mientras su asignación continúa vigente sin introducir otro mecanismo de reversión.

No se hace backfill de pedidos previamente pagados porque no hay datos suficientes para reconstruir responsable, fecha, destino o regla sin inventarlos. La FK de Slice 5.1 se agrega anulable y tampoco intenta asociar distribuciones históricas.

Una edición económica posterior tampoco actualiza el cobro. Revertir no libera la unicidad del Order ni la relación automática, y una nueva transición a `paid` no crea otro pago o distribución. Un repago real deberá modelarse en el futuro como otro hecho de pago, no mediante `DELETE` o relajando ingenuamente las restricciones únicas.

## Consecuencias

- Orders conserva su responsabilidad operativa y no conoce internals de Management.
- Management obtiene trazabilidad estructurada Order → origen financiero → ledger.
- La transición a `paid` y el ingreso son atómicos dentro del monolito modular.
- Un pedido sólo puede aumentar el ledger una vez, aun con reintentos o concurrencia.
- El cobro aumenta ingresos y saldo global; la distribución asociada suma cero y sólo cambia la composición entre billeteras.
- Una versión concreta y sus snapshots explican cómo se repartió cada pago sin depender de la configuración actual.
- Un pago sin regla permanece visible y reintentable; no se oculta ni se descarta.
- Las fallas técnicas del reparto no dejan una confirmación financiera parcial.
- Confirmar un pago requiere que Management tenga una billetera predeterminada activa.
- Las cancelaciones y correcciones exigen una decisión financiera explícita; no se infieren desde un estado operativo mutable.
- La reversión coordinada se expresa como dos compensaciones existentes, obligatoriamente distribución antes que pago.
- Corregir estructuralmente un cobro después de editar el total requiere un slice posterior de reconciliación; la reversión manual actual no habilita un segundo origen.

## Alternativas consideradas

### Usar `payment_status = paid` como evidencia financiera

Rechazado porque es mutable, no conserva la referencia financiera y no ofrece idempotencia estructural.

### Agregar `order_id` directamente a `financial_transactions`

Rechazado porque mezcla un origen específico con la cabecera genérica y dificulta agregar reglas propias sin contaminar otros tipos financieros.

### Llamar al ledger directamente desde Orders

Rechazado porque invierte el límite modular: Orders pasaría a conocer billeteras, tipos y persistencia interna de Management.

### Procesar el evento después del commit o en una cola

Rechazado para este slice porque introduciría una ventana con Order pagado sin ingreso y exigiría outbox, reintentos y conciliación que todavía no existen.

### Elegir la primera billetera activa o buscarla por nombre

Rechazado porque el orden y el texto no son configuración financiera. La intención se persiste explícitamente en Management.

### Revertir automáticamente al cancelar o volver a no pagado

Rechazado porque una transición operativa no demuestra que el dinero fue devuelto. La reversión financiera requiere una acción explícita y auditable.

### Fallar el pago cuando no existe una regla activa

Rechazado porque el cobro externo y su asignación interna son hechos distintos. La ausencia de configuración no invalida dinero ya confirmado; se conserva un estado pendiente visible y reintentable.

### Elegir cualquier regla activa o permitir varias activas

Rechazado porque haría ambigua la distribución automática. Una única regla activa global conserva una fuente de verdad explícita; la versión aplicada queda fijada en la distribución.

### Revertir automáticamente distribución y pago en un único mecanismo nuevo

Rechazado porque duplicaría `ReverseFinancialTransaction` y ocultaría dos hechos financieros. Exigir primero la compensación de la distribución y luego la del pago conserva simplicidad, trazabilidad y control administrativo.
