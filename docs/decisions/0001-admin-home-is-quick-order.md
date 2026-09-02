# 0001: El Inicio administrativo es el pedido rápido

Estado: aceptado
Fecha: 2026-08-13

## Contexto

La raíz administrativa actualmente apunta a un Dashboard provisional. La principal tarea cotidiana es crear un pedido con rapidez, mientras que las métricas ya están disponibles en Orders y son menos valiosas como destino inicial.

## Decisión

`/admin` se convertirá en un POS de pedidos rápidos centrado en productos. Usará el sidebar existente, un catálogo de cards de productos, una columna para el pedido actual y un panel derecho de segunda etapa para los datos del cliente y el cumplimiento. Los analytics del Dashboard pasarán a ser una sección secundaria.

La feature reutilizará los límites existentes de precios, vista previa, validación, creación y snapshots de Orders. No se implementará la UI de pagos de la plantilla visual.

## Consecuencias

- Los administradores acceden de inmediato a la tarea más frecuente.
- El formulario administrativo actual de creación debe descomponerse en contenido reutilizable de borrador y formulario, en lugar de duplicarse.
- `/admin/orders/create` continúa disponible como alternativa de página completa y enlace directo.
- El nuevo Inicio es un slice importante de UI y navegación, y debe implementarse después de consolidar el worktree de Orders.

## Alternativas consideradas

- **Dashboard como Inicio:** rechazado porque prioriza la observación por sobre la principal tarea operativa.
- **Persistir un pedido con cada clic en un producto:** rechazado porque los borradores incompletos no deben convertirse en registros de negocio.
- **Copiar el popup de pagos de Figma:** rechazado porque Te Pinta no procesa pagos en línea y ya tiene un flujo completo de cliente y cumplimiento.
