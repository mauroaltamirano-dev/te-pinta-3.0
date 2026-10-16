# Pedidos y confirmación financiera

`Orders` administra el pedido y su estado operativo. Cuando un administrador confirma `paid`, emite un contrato acotado para que `Management` registre el dinero. Orders nunca escribe el ledger ni elige directamente una billetera.

## Catálogo y snapshots de precio

Orders recibe una familia de precio ya validada desde Products. Una línea `unit` usa únicamente su precio unitario y no se incorpora a bloques de empanadas ni a descuentos crudos. La familia sólo afecta cotizaciones nuevas o una edición económica confirmada del pedido seleccionado; las filas y totales ya persistidos continúan siendo snapshots y nunca se reinterpretan por cambios posteriores de familia o catálogo. Las vistas y resúmenes históricos usan etiquetas genéricas de productos y unidades; no deducen una familia desde el Product actual.

## Regla principal

```text
Order payment_status -> paid
  -> OrderPaymentConfirmed
  -> ManagementOrderPayment
  -> FinancialTransaction(order_payment)
  -> LedgerEntry positiva
  -> ManagementDistribution, si existe regla activa
  -> FinancialTransaction(distribution)
  -> débito del destino del pago + créditos versionados
```

`payment_status = paid` indica que el equipo confirmó operativamente el pago. Puede cambiar y no constituye por sí solo evidencia financiera. El hecho durable es `ManagementOrderPayment`, único por Order.

## Caminos que confirman un pago

| Camino                       | Cuándo emite el evento                                           |
| ---------------------------- | ---------------------------------------------------------------- |
| `CreateOrder` administrativo | El pedido nuevo queda `paid` con total positivo y método válido. |
| `UpdateOrder`                | El pedido bloqueado transiciona de un estado no pagado a `paid`. |
| `UpdateOrderPaymentStatus`   | El control rápido transiciona de un estado no pagado a `paid`.   |

El checkout público no puede establecer `paid`, descuentos ni método de pago. Un total cero se normaliza como `not_required` y no representa un cobro.

## Listado administrativo

El grupo predeterminado **Todos** muestra pedidos activos y entregados, pero excluye los cancelados. Los pedidos cancelados permanecen disponibles mediante el grupo **Cancelados** o el filtro explícito `status=cancelled`.

## Contrato entre módulos

Orders emite síncronamente:

```php
OrderPaymentConfirmed(
    orderId: int,
    amount: int,
    confirmedOn: CarbonImmutable,
    adminId: int,
)
```

El evento lleva datos mínimos y estables. No expone el modelo Order ni conoce `Wallet`, `FinancialTransaction` o `LedgerEntry`. `confirmedOn` es la fecha calendario del negocio al confirmar el pago; no se reutilizan `created_at`, `delivery_date` ni la fecha original del pedido.

El listener de Management es síncrono y participa en la misma transacción PostgreSQL. Si Management no puede registrar el ingreso —por ejemplo, porque falta una billetera predeterminada activa— tampoco se confirma la transición a `paid`. Después del ingreso, Management intenta distribuirlo desde esa misma billetera mediante la versión concreta de la única regla activa. Una falla técnica del reparto revierte también el pago y la transición operativa.

No tener una regla activa es una condición de configuración, no una falla del cobro: el `ManagementOrderPayment` se conserva como **Pendiente de distribución** y puede reintentarse desde Gestión. Orders no consulta ese estado ni conoce reglas o billeteras.

## Idempotencia e historia

`management_order_payments.order_id` es único. Repetir el evento, reintentar un request o competir bajo concurrencia no crea dos ingresos. La creación del origen semántico, la cabecera `order_payment` y su única entry positiva se confirma o revierte completa.

Cambiar después a `unpaid` o cancelar el Order no borra ni revierte el ledger automáticamente. Una corrección financiera usa `ReverseFinancialTransaction` y conserva el original. Si el cobro tiene una distribución aplicada, se revierte primero el reparto y después el pago; intentar el orden inverso se rechaza. Volver luego a `paid` tampoco crea un segundo cobro ni otra distribución para el mismo Order.

Editar económicamente un Order ya cobrado tampoco modifica el importe financiero original. Slice 5 permite revertirlo manualmente, pero no recrear un segundo `ManagementOrderPayment` para el mismo pedido. La corrección estructurada y la conciliación contra el nuevo total quedan diferidas para preservar primero la invariante `un Order = un ingreso original`.

La auditoría de Management puede mostrar esa diferencia para revisión administrativa, pero no modifica el total del Order, el cobro ni el ledger. Sólo reconcilia Orders que poseen `ManagementOrderPayment`: los pedidos legacy pagados antes de la integración continúan fuera del universo financiero verificable y no se convierten en findings por inferencia.

Los pedidos que ya estaban pagados antes de Slice 5 no reciben backfill. La migración posterior tampoco les inventa una distribución ni una relación: inferir fecha de confirmación, administrador, billetera o regla fabricaría historia financiera.

## Responsabilidades

| Orders                                    | Management                                       |
| ----------------------------------------- | ------------------------------------------------ |
| Valida totales, estado y método de pago.  | Configura la billetera predeterminada activa.    |
| Detecta la transición real a `paid`.      | Crea `ManagementOrderPayment` y el ledger.       |
| Emite ID, importe, fecha y administrador. | Protege idempotencia, inmutabilidad y reversión. |
| No consulta ni distribuye billeteras.     | Aplica la regla activa y conserva su versión.    |
| No decide reintentos financieros.         | Expone pagos pendientes y su reintento seguro.   |

## Fuera de alcance

- gateway o conciliación de pagos;
- pagos parciales o múltiples por pedido;
- backfill de pedidos pagados existentes;
- reversión automática al cancelar o volver a no pagado;
- recreación/corrección estructurada después de editar el total de un Order cobrado;
- comprobantes e Inventory.

La integración completa consume el ingreso trazable y aplica una regla sin volver a crear dinero:

```text
Order Payment
  -> billetera predeterminada
  -> Apply Distribution Rule
  -> allocations conservativas
```

El pago aumenta el ledger por el importe externo. La distribución posterior suma cero, guarda la versión usada y queda vinculada 0..1 con el pago mediante una restricción única.

Consulte [MANAGEMENT.md](MANAGEMENT.md) para el ledger y [ADR 0009](decisions/0009-idempotent-order-payments-in-management.md) para las compensaciones.

## POS-1: origen y fulfillment independientes

`source` es el snapshot del origen (`admin`, `public_checkout`, `pos`), establecido por el comando de creación y no inferido desde cliente/mesa. `delivery_type` conserva su nombre por compatibilidad y representa `delivery`, `pickup` o `dine_in`. Su eventual rename queda fuera de este slice. Un pedido público puede ser delivery o pickup; `public_web` no es una modalidad.

| Contexto | Cliente/contacto | Dirección | Programación | Envío |
| --- | --- | --- | --- | --- |
| Public delivery | Nombre y teléfono requeridos; asociación real existente | Requerida | Fecha/período requeridos, fecha pública no pasada | Configuración existente |
| Public pickup | Nombre y teléfono requeridos | No requerida | Contrato público existente | 0 |
| Admin delivery | Cliente real seleccionado o nombre nuevo; teléfono opcional como antes | Requerida | Fecha/período existentes | Configuración existente |
| Admin pickup | Cliente real seleccionado o nombre nuevo; teléfono opcional | No requerida | Fecha/período existentes | 0 |
| POS dine-in inmediato | Customer, nombre y teléfono opcionales; no se crea perfil sin cliente | No aplicable | Fecha/período/hora nulos | 0 |

El checkout público y las altas administrativas no aceptan `dine_in` ni un `source` enviado por el cliente. POS-1 prepara el comando de dominio, no un endpoint o formulario POS. Las claves de idempotencia pública no se aceptan en otros orígenes; no se comparte ese namespace con POS. Una edición administrativa de un POS dine-in existente conserva el contexto inmediato; puede modificar contacto opcional sin crear Customer y registra sólo el nombre del campo cambiado en auditoría. Un Customer real opcional conserva su perfil, sin copiar su dirección al salón.

La condición `raw`/`cooked` es comercial y necesaria para cotizar, no programación; se mantiene. El motor existente sólo cobra shipping para delivery. Los pedidos no dine-in mantienen su asociación real al cliente para conservar la semántica administrativa existente. No se quitan columnas ni se backfillean clientes, fuentes o snapshots históricos.

Los recursos devuelven `customer = null` cuando no hay FK y exponen `customer_name` como snapshot opcional independiente. No usan nombres ficticios ni convierten una fecha nula en hoy. Las vistas admin muestran el contexto de salón y no generan enlaces a clientes inexistentes. La distribución existente mantiene delivery/pickup y agrega el bucket dine-in sólo si hay pedidos de ese tipo; el Dashboard diario basado en fecha programada no incorpora automáticamente los dine-in inmediatos. Una cola POS en tiempo real pertenece a un slice futuro.

### Operaciones diferidas

No implementar confirmación pública, submissions, fulfillment físico, cierre ni rondas con asignaciones arbitrarias de estado. Deben contar con comandos, autorización y efectos definidos en sus slices. `delivered` y el futuro consumo Inventory no cambian aquí. La confirmación administrativa de pago existente sigue funcionando; POS-1 no abre cobros para staff ni agrega pagos POS. `paid → unpaid` no revierte Management: la corrección financiera sigue diferida a POS-6B y debe conservar/revertir el ledger por sus comandos, nunca por shortcuts.
