# 0004: Eliminación administrativa recuperable

Estado: aceptado
Fecha: 2026-08-14

## Contexto

Los administradores necesitan quitar registros de prueba o erróneos de las listas operativas, pero Orders contiene IDs públicos inmutables, snapshots, revisiones y datos de idempotencia del checkout público. Los registros de Customers pueden estar vinculados a pedidos históricos reales. La eliminación física en cascada destruiría el historial de negocio y la evidencia de auditoría.

## Decisión

La eliminación normal de Order y Customer continúa siendo soft deletion de Eloquent. Las páginas de papelera exclusivas para administradores muestran los registros eliminados y restauran las filas originales. No se expone ninguna ruta de force delete ni eliminación en cascada desde Customers hacia Orders.

Restaurar un Order preserva su ID público, snapshots, líneas, composiciones, toppings y revisiones. Eliminar o restaurar un Customer nunca modifica los Orders vinculados. Si otro cliente activo reutilizó el teléfono normalizado de un cliente eliminado, la restauración se rechaza con un error de validación que indica cómo proceder; ningún registro se modifica ni combina silenciosamente.

La eliminación permanente no es una capacidad rutinaria del producto. Cualquier purga futura por motivos legales o de retención de datos requiere un diseño separado, con confirmación firme y almacenamiento de auditoría fuera de las filas que se destruirán.

## Consecuencias

- La eliminación accidental es reversible sin reconstruir el historial.
- Las listas operativas se mantienen limpias, mientras que los reportes pueden decidir explícitamente si incluyen filas en la papelera.
- La reutilización de un teléfono después de una eliminación puede impedir la restauración hasta que se resuelva explícitamente el conflicto activo.
- Una purga única y respaldada de datos de desarrollo no establece una feature general de force delete.

## Alternativas consideradas

- **Eliminación permanente inmediata:** rechazada porque elimina revisiones, snapshots y protección de idempotencia.
- **Eliminar un cliente y todos sus pedidos en cascada:** rechazada porque el ciclo de vida del cliente no es el ciclo de vida del historial de pedidos.
- **Restaurar borrando el teléfono o combinar automáticamente:** rechazada porque modifica silenciosamente los datos de identidad.
