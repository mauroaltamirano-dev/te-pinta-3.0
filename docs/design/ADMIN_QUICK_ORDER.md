# Implementación de pedidos rápidos en el Inicio administrativo

El Inicio administrativo en `/admin` es el camino más rápido para crear un pedido. El Dashboard permanece disponible en `/admin/dashboard`; el Inicio es el POS centrado en productos.

## Experiencia objetivo

```text
Sidebar | Product catalog | Current order
                         └─ Continue → customer and fulfillment panel
```

1. El Inicio muestra las variedades reales de productos disponibles con accesos rápidos de 12, 6 y 1 unidad.
2. Cada acceso rápido incrementa ese producto en un pedido administrativo en curso.
3. La columna derecha fija muestra líneas, cantidades, precios aplicados en la previsualización, ahorros y subtotal.
4. **Continuar** abre el panel de la segunda etapa sobre un catálogo oscurecido.
5. Esa etapa captura los campos administrativos existentes: cliente, adicionales, condición cruda o cocinada, envío o retiro, fecha, período, hora, dirección, notas, descuento opcional y estado o método de pago.
6. La confirmación llama al límite existente de creación administrativa de pedidos; cancelar regresa sin persistir un pedido.

## Referencia visual

| Elemento | Decisión de referencia |
| --- | --- |
| Marco | 1194 × 834, lienzo `#252836` |
| Barra lateral | Riel global existente de 104 px; no duplicarlo dentro de la página |
| Columna de catálogo | Ancho de referencia de 633 px después del riel, ritmo superior y lateral de 24 px |
| Columna del pedido | 409 px de ancho, 24 px de relleno, `#1F1D2B`, radio de 8 px |
| Tarjeta de producto | 192 × 260; el cuerpo comienza a 34 px desde arriba; `#1F1D2B`; radio de 16 px |
| Imagen del producto | Imagen circular de 132 × 132, centrada en la parte superior de la tarjeta |
| Filas de productos | Tres tarjetas por cada fila de referencia de 632 px, con separaciones de 28 px |
| Acción del pedido | Botón coral de 361 × 48 con la sombra de acento existente |
| Superposición de la segunda etapa | `rgba(0,0,0,.7)` sobre el catálogo; el panel ingresa desde la derecha |
| Panel de la segunda etapa | La ventana emergente de referencia tiene 814 px de ancho y se divide en dos columnas de contenido de 361 px |

Capturas principales:

- `docs/design/reference/Captura de pantalla 2026-08-13 131709.png` — Inicio o selección de productos.
- `docs/design/reference/Captura de pantalla 2026-08-13 132047.png` — superposición de la segunda etapa.

El CSS exportado por el usuario constituye evidencia de medidas, no CSS de producción. La implementación debe usar un diseño adaptable y tokens semánticos en lugar de posicionar toda la página de forma absoluta.

## Restricciones de arquitectura

- Reutilizar `CreateOrder`, el endpoint administrativo de previsualización de precios, la validación, los snapshots y los DTO de precios.
- Extraer estado y contenido reutilizables del formulario de la página administrativa de creación actual en lugar de construir un segundo formulario de pedidos.
- Las tarjetas de productos muestran como referencia el precio actual de la docena cocinada; la agrupación de líneas seleccionadas y los totales deben provenir de la previsualización del backend.
- El precio base es el cocinado. La alternativa cruda aplica los descuentos positivos de `raw_price_discounts`; los campos `cooking_*` sólo se conservan como compatibilidad explícita con snapshots históricos y nunca representan la regla económica actual.
- No crear un pedido al seleccionar un producto; la persistencia ocurre únicamente después de la confirmación final.
- La creación administrativa permite registrar una fecha de entrega pasada para cargar pedidos históricos; el mínimo de fecha comercial permanece restringido al checkout público.
- Los controles de método de pago y descuento reutilizan el mismo borrador tipado, la validación y la previsualización del backend que Create/Edit completos; la plantilla visual nunca define valores de dominio ni totales.
- Conservar `/admin/orders/create` como ruta directa o alternativa de creación en página completa.
- Usar categorías, imágenes, nombres, disponibilidad y precios reales de productos; nunca copiar platos de una plantilla.
- Mantener el estado del borrador del Inicio dentro del flujo administrativo, salvo que se agregue un requisito explícito de persistencia.

## Responsabilidades implementadas

| Responsabilidad | Responsable |
| --- | --- |
| Borrador en memoria, mutaciones de cantidades o adicionales, secuenciación de previsualizaciones y bloqueo de envío | `features/orders/hooks/use-admin-order-draft.ts` |
| Campos compartidos de cliente, condición, adicionales, entrega, notas y pago | `features/orders/components/admin-order-details-fields.tsx` |
| Desglose económico del backend | `PriceSummary` compartido que llama a `POST /admin/orders/price-preview` |
| Persistencia final | `POST /admin/orders` existente → `StoreAdminOrderRequest` → `CreateOrder` |
| Presentación rápida | `pages/admin/home.tsx` junto con los componentes del catálogo y pedido actual de Quick Order |
| Presentación alternativa completa | `pages/admin/orders/create.tsx` con el mismo hook y los campos compartidos |

El almacenamiento negocia una confirmación JSON para Quick Order y conserva la redirección de Inertia para la creación completa. Ambas respuestas ejecutan la misma transacción validada. La confirmación JSON incluye el comprobante administrativo generado por el backend para que el estado de éxito pueda copiarlo y abrir el ID del Order persistido sin reconstruir el detalle en React. Cerrar o cancelar sólo cambia el estado de React; el descarte explícito restablece ese estado; ninguna de estas acciones realiza una solicitud de persistencia.

## Navegación y estado

- `/admin` es el punto de entrada canónico de Quick Order.
- La primera y la segunda etapa son estados del mismo borrador, no pedidos persistidos separados.
- Volver o cerrar desde el navegador debe descartar la segunda etapa sin perder las líneas seleccionadas; abandonar el flujo puede solicitar confirmación cuando el borrador no está vacío.
- En escritorio y tablet se conserva la división completa del POS. En dispositivos móviles, el catálogo usa dos columnas, la acción del pedido permanece sobre la navegación inferior y los paneles del pedido o de detalles pueden ocupar el viewport como paneles deslizables.

## Lista de verificación de aceptación

- [x] Los accesos rápidos de productos agregan 12, 6 o 1 unidad y actualizan la previsualización del backend.
- [x] Cada tarjeta contiene sus accesos rápidos sobre `admin-action` y muestra el precio de la docena cocinada sin desbordar los controles.
- [x] Las coincidencias de clientes aparecen mientras se escribe y pueden seleccionarse de inmediato.
- [x] El envío reutiliza la dirección del cliente seleccionado sin reemplazar una edición específica del pedido.
- [x] Las cantidades pueden modificarse y las líneas pueden quitarse desde el panel del pedido.
- [x] Continuar no está disponible cuando el borrador está vacío.
- [x] La etapa de detalles reutiliza todos los campos y reglas de la creación administrativa de pedidos.
- [x] El precio base cocinado y los descuentos positivos de `raw_price_discounts` para la alternativa cruda permanecen explícitos; los campos heredados `cooking_*` no recalculan pedidos históricos.
- [x] Los descuentos porcentuales o fijos se previsualizan mediante el backend, excluyen el envío y permanecen limitados al subtotal elegible.
- [x] El estado pagado exige efectivo, transferencia o débito; volver al estado sin pagar limpia el método.
- [x] Cancelar nunca persiste un pedido; confirmar persiste exactamente uno.
- [x] El estado de éxito puede copiar el comprobante del backend o abrir el pedido persistido.
- [x] La ruta directa `/admin/orders/create` continúa funcionando.
- [x] Quick Order y la creación administrativa directa permiten registrar pedidos con fecha de entrega pasada.
- [x] Los estados de teclado, tablet, móvil, nombre largo, imagen faltante y pedido extenso están cubiertos por el comportamiento adaptable y accesible y por las pruebas de navegador.
- [x] En dispositivos móviles se muestran dos tarjetas de producto por fila y la navegación principal permanece en la zona de alcance del pulgar.
- [x] Playwright captura la primera y la segunda etapa para compararlas con las capturas provistas.
