# 0003: Descuentos y medios de pago de pedidos en el backend

Estado: aceptado
Fecha: 2026-08-14

## Contexto

Los administradores necesitan aplicar descuentos porcentuales o fijos en pesos y deben registrar si un pedido pagado se abonó en efectivo, por transferencia bancaria o con tarjeta de débito. La creación, Edit, Quick Order y la vista previa de precios ya comparten un único límite de precios en el backend. El ahorro de la docena combinada es metadata de agrupación comercial, no un descuento administrativo.

## Decisión

`OrderPricingEngine` recibe únicamente un `DiscountType` opcional y un valor entero. La base elegible son los items después de la agrupación comercial: los precios de productos cocinados son la base y, para los productos crudos, se resta el descuento correspondiente; también se incluyen los toppings y se excluye el envío. Los resultados porcentuales usan redondeo half-up a pesos enteros y ambos tipos de descuento tienen como límite la base elegible. La quote devuelve `discountable_subtotal` y el `discount_amount` aplicado; los clientes no pueden enviar ni el importe ni un total.

Orders guarda snapshots de `discount_type`, `discount_value` y `discount_amount`. PostgreSQL exige la fórmula del descuento y la invariante final:

```text
total = items_subtotal + toppings_subtotal + shipping_fee_snapshot - discount_amount
```

`PaymentMethod` puede ser `cash`, `bank_transfer` o `debit_card`. Un pedido paid exige un medio; uno unpaid no almacena ninguno. Volver a unpaid elimina el medio de forma atómica. El checkout público permanece unpaid y no puede enviar descuentos ni medios de pago.

## Consecuencias

- La vista previa y la persistencia de Create/Edit/Quick Order no pueden divergir en los descuentos.
- El envío aparece en el total, pero nunca se reduce mediante un descuento del pedido.
- No se asigna un medio de pago inventado al historial pagado existente; la restricción de la base de datos exige la invariante en cada fila nueva o actualizada posteriormente.
- Modificar un descuento es un cambio económico y, durante la edición, provoca un nuevo cálculo completo con las reglas actuales. Modificar únicamente el estado o el medio de pago no recalcula precios.
- Los snapshots históricos no se recalculan fuera de una edición económica explícita.

## Alternativas consideradas

- **Calcular los descuentos en React:** rechazado porque los clientes no son responsables de los resultados monetarios.
- **Reutilizar el ahorro de la docena combinada:** rechazado porque ese valor explica un cierre comercial y tiene una semántica diferente.
- **Aplicar descuentos al envío:** rechazado por la regla de negocio confirmada para Te Pinta.
- **Guardar un medio de pago en pedidos unpaid:** rechazado porque confunde un medio previsto con un pago completado.
