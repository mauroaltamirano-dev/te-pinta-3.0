# Pedidos y confirmación financiera

`Orders` administra el pedido y su estado operativo. Cuando un administrador confirma `paid`, emite un contrato acotado para que `Management` registre el dinero. Orders nunca escribe el ledger ni elige directamente una billetera.

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
