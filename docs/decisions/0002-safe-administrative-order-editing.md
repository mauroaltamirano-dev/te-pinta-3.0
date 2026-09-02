# 0002: Edición administrativa segura de pedidos

Estado: aceptado
Fecha: 2026-08-13

## Contexto

Los administradores necesitan corregir pedidos completos sin crear otro formulario ni otro flujo de precios. Los pedidos son snapshots históricos, los pedidos cerrados requieren una intención más firme, las reglas actuales del catálogo pueden diferir de las de la venta original y las superficies administrativas concurrentes no deben sobrescribirse silenciosamente.

## Decisión

`/admin/orders/{public_id}/edit` hidrata el borrador administrativo compartido a partir de los items estructurados del pedido, las composiciones de docena combinada, los toppings y los snapshots del pedido. La creación y la edición comparten `OrderCheckoutData`, la resolución del cliente, `OrderPricingEngine`, `PersistOrderQuote`, la vista previa, los fields y el estado del borrador. `UpdateOrder` permanece como un caso de uso transaccional separado.

Una edición económica vuelve a calcular el precio del pedido completo con las reglas actuales y reemplaza atómicamente los snapshots únicamente de ese pedido. Una edición no económica conserva la quote persistida. Las selecciones históricas no disponibles solo pueden mantenerse cuando la selección económica completa no cambia; para volver a calcular con las reglas actuales es necesario quitarlas o reemplazarlas.

Cada mutación incrementa `orders.edit_version`. La edición completa bloquea la fila y exige la versión observada durante la hidratación. Los pedidos delivered/cancelled también exigen un motivo breve y un permiso de sesión autenticada de un solo uso, vinculado al pedido y a la versión; conceder el permiso no cambia el estado.

Cada edición completa exitosa escribe un registro específico de Orders en `order_revisions` que contiene la identidad, el administrador, el timestamp, el estado, los totales anteriores y posteriores, los nombres de los fields modificados, un indicador de cambio económico y, cuando corresponda, el motivo de edición del pedido cerrado. No almacena valores completos de teléfono, dirección ni nota.

## Consecuencias

- El backend continúa siendo la única autoridad sobre precios y disponibilidad para la venta; React solo muestra vistas previas y compara totales.
- El comportamiento existente de creación y Quick Order permanece dentro de los mismos límites.
- Las correcciones económicas adoptan intencionalmente la configuración actual para todo el pedido editado, en lugar de mezclar reglas históricas y actuales.
- El permiso para pedidos cerrados no puede reutilizarse después de una edición exitosa ni después de que cambie la versión del pedido.
- La persistencia de revisiones y el reemplazo de snapshots se confirman o revierten en conjunto.
- Las selecciones históricas no disponibles no pueden formar parte de una quote calculada nuevamente.
- Fuera de una edición económica explícita, los snapshots históricos son inmutables y nunca se recalculan.

## Alternativas consideradas

- **Un motor de precios o formulario separado para la edición:** rechazado porque duplicaría la validación, la agrupación y el comportamiento de la UI.
- **Conservar los precios originales de las líneas sin cambios durante las ediciones económicas:** rechazado porque mezcla generaciones de reglas dentro de un mismo cierre comercial.
- **Volver a calcular productos históricos no disponibles a partir de snapshots:** rechazado porque los snapshots no demuestran la disponibilidad actual para la venta ni constituyen una quote completa con las reglas actuales.
- **La última escritura prevalece:** rechazado porque, de otro modo, las superficies de estado, pago y edición completa podrían perder cambios administrativos aceptados.
- **Auditoría genérica para toda la aplicación:** rechazado porque Orders necesita un registro de revisión estructurado y acotado, y ningún otro módulo demuestra actualmente la necesidad de esa abstracción.
- **Reabrir pedidos cerrados cambiando el estado:** rechazado porque el permiso para corregir datos no es una transición de estado operativo.
