# Dashboard operativo

`/admin/dashboard` es una superficie de lectura exclusiva para administradores que permite responder qué debe atender Te Pinta ahora y cómo se está desempeñando el calendario de entregas actual. Lee pedidos (`Orders`) persistidos y sus snapshots; no es responsable de precios, transiciones de pedidos, clientes, productos, inventario, Marketing ni Analytics.

## Calendario y períodos

La pertenencia a cada período siempre usa `orders.delivery_date` en la zona horaria provista por `OrderBusinessDate` (`America/Argentina/Buenos_Aires` de forma predeterminada). `created_at` nunca asigna un pedido a un período operativo.

| Selector      | Rango de calendario inclusivo                                       | Intervalos de evolución |
| ------------- | ------------------------------------------------------------------- | ----------------------- |
| Hoy           | Hoy según el calendario comercial                                   | Mediodía, tarde, noche  |
| Semana        | De lunes a domingo de la semana ISO actual                          | Un intervalo por día    |
| Mes           | Del primer al último día del mes calendario actual                  | Un intervalo por día    |
| Desde siempre | Desde la primera hasta la última fecha de entrega activa persistida | Un intervalo por mes    |

Cambiar el selector actualiza las métricas del período, los productos, las distribuciones y la evolución. **Los pedidos urgentes de hoy siempre permanecen limitados al día actual del calendario comercial**, incluso cuando se selecciona Semana o Mes.

## Contrato de métricas

Los pedidos eliminados de forma lógica y los pedidos `cancelled` se excluyen de todas las métricas normales del Dashboard.

| Métrica                             | Incluye                                                                                                                          | Excluye                                                                                                 |
| ----------------------------------- | -------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------- |
| Ventas                              | Pedidos `waiting`, `prepared` y `delivered` dentro del rango de `delivery_date` seleccionado; suma de `orders.total` persistidos | `pending_confirmation`, `cancelled`, eliminados de forma lógica; el estado de pago no altera las ventas |
| Ingresos cobrados                   | Pedidos `paid` del rango cuyo estado operativo sea `waiting`, `prepared` o `delivered`; suma de `orders.total` persistidos       | `unpaid`, `not_required`, `pending_confirmation`, `cancelled`, eliminados de forma lógica               |
| Pedidos                             | Pedidos `pending_confirmation`, `waiting`, `prepared` y `delivered` del rango seleccionado; cantidad de pedidos                  | `cancelled`, eliminados de forma lógica                                                                 |
| Pendientes de confirmación          | Pedidos `pending_confirmation` del día actual del calendario comercial                                                           | Cualquier otro estado, otra fecha y eliminados de forma lógica                                          |
| En espera / Preparados / Entregados | Pedidos del día actual del calendario comercial en el estado indicado                                                            | Cualquier otro estado o fecha y eliminados de forma lógica                                              |
| Sin cobrar                          | Pedidos confirmados con total positivo del rango seleccionado que no estén pagados                                               | Pendientes de confirmación, cancelados y eliminados de forma lógica                                     |

Las ventas representan valor comprometido o confirmado; los ingresos cobrados son la porción pagada del valor confirmado. Por lo tanto, un período normal puede mostrar ventas superiores a los ingresos cobrados. Un total de cero no se presenta como ingreso cobrado sólo porque su estado de pago en el backend sea `not_required`.

## Pedidos urgentes de hoy

Los pedidos urgentes son la cola operativa activa de hoy, no una alerta basada en un umbral de tiempo. El bloque incluye únicamente `pending_confirmation`, `waiting` y `prepared`; excluye `delivered`, `cancelled` y los pedidos eliminados de forma lógica.

El orden es determinista:

1. prioridad de estado: pendiente de confirmación, en espera, preparado;
2. período de entrega: mediodía, tarde, noche;
3. dentro del mismo período, `delivery_time` exacto en orden ascendente, con los horarios faltantes al final;
4. ID interno del pedido como desempate final estable.

No existe una regla como «urgente dentro de los próximos 30 minutos». El bloque más amplio **Pedidos de hoy** se ordena cronológicamente: período de entrega, hora exacta con valores nulos al final, prioridad de estado y, por último, ID. Incluye los pedidos pendientes de confirmación y todos los pedidos confirmados y no cancelados de hoy.

Seleccionar un identificador TP hidrata el recurso y el resumen existentes de Orders, y abre el `OrderDetailSheet` propiedad de Orders. Dashboard no duplica el comportamiento de detalle, copia, edición, estado, pago, autorización ni detalle completo. Cerrar el panel conserva `/admin/dashboard` y el período seleccionado.

## Productos y unidades de producción

Las métricas de productos usan snapshots persistidos de ítems y composiciones de pedidos confirmados únicamente. Nunca interpretan texto de presentación ni reconstruyen el historial a partir de precios o nombres actuales de productos.

- Los ítems directos aportan su `units_count` persistido; media docena equivale a 6 y una docena a 12.
- Una docena combinada aporta a cada variedad su cantidad persistida en la composición. Su línea principal no vuelve a contarse en la clasificación por variedad.
- Los nombres históricos de productos siguen siendo legibles desde los snapshots cuando el producto actual cambia de nombre o se elimina de forma lógica.
- **Más vendidos** clasifica todas las variedades por unidades de producto reales de snapshots, con un desempate estable por etiqueta o identificador. La interfaz muestra siempre la cantidad literal de unidades, sin convertir productos históricos a docenas.
- La clave de compatibilidad `empanadas_count` conserva su nombre de API, pero representa la suma de unidades de producto de snapshots del período seleccionado; no prueba que esas unidades sean empanadas.
- **Crudas / Cocinadas** distribuye esas unidades según la condición persistida del pedido. No es un gráfico de cantidad de pedidos ni una clasificación de familia actual.
- **Envío / Retiro** distribuye pedidos según el tipo de entrega persistido. No es un gráfico de unidades de producto.

Los pedidos pendientes de confirmación, cancelados y eliminados de forma lógica no aportan ventas, ingresos cobrados, productos ni unidades de producto.

## Evolución de ventas

El gráfico de evolución usa la misma regla de ventas confirmadas y los mismos totales persistidos de pedidos que Ventas. Es una presentación SVG/CSS pequeña y accesible, no una segunda capa de cálculo. El backend devuelve todos los intervalos esperados en orden de calendario y completa con cero los intervalos sin ventas para mantener estables los ejes y las etiquetas. Un período completamente en cero muestra un estado vacío claro en lugar de `NaN`, `undefined` o una tendencia engañosa.

## Límite del modelo de lectura

Dashboard ejecuta consultas agregadas o agrupadas y acotadas sobre Orders y las tablas de snapshots. Puede hidratar de forma anticipada las listas operativas breves de pedidos, pero no debe cargar todos los pedidos para sumarlos en React ni ejecutar una consulta por tarjeta. El frontend da formato y presenta datos tipados del modelo de lectura; no decide qué estados cuentan.

Dashboard no es Analytics. Provee el resumen del período calendario actual seleccionado y la cola operativa de hoy; no infiere ganancias, gastos, saldo, crecimiento interanual ni inventario. Tampoco es responsable de comandos de pedidos: todas las acciones de los paneles siguen pasando por las políticas, solicitudes y acciones existentes de Orders.

## Composición adaptable

En escritorio y tablet se usan tarjetas KPI compactas, seguidas de una columna operativa más ancha y otra de información más angosta. La página usa el flujo natural del documento; cada tarjeta controla únicamente el desbordamiento que realmente necesita. En dispositivos móviles se preserva este orden de lectura:

1. pedidos urgentes de hoy;
2. pedidos de hoy;
3. KPI;
4. productos, distribuciones y evolución.

En dispositivos móviles, las tablas se recomponen como filas o tarjetas compactas y no deben introducir desplazamiento horizontal en toda la página.

## Referencias de diseño

El repositorio contiene actualmente estas referencias del Dashboard:

- `docs/design/reference/dashboard/Captura de pantalla 2026-08-16 004332.png`
- `docs/design/reference/dashboard/figma.txt`

Los nombres solicitados `dashboard-manager-reference.png`, `dashboard-orders-reference.png` y `dashboard-figma-spec.txt` no están presentes actualmente. Los archivos disponibles sólo aportan orientación sobre jerarquía y grilla; la implementación conserva las superficies oscuras, el acento coral, la tipografía, el espaciado y los tokens semánticos de estado de Te Pinta.

## Resumen de Marketing

Dashboard consume un resumen compacto y de sólo lectura del módulo separado Marketing. Muestra la recomendación activa visible públicamente; una recomendación temporalmente activa pero no disponible junto con su advertencia; la primera recomendación futura habilitada cuando no hay ninguna activa; o un estado vacío explícito. La tarjeta enlaza a `/admin/marketing`.

Dashboard no consulta por sí mismo las ventanas de Featured Product ni la elegibilidad de Product, y no es responsable de tablas, modelos, comandos, descuentos, códigos, ofertas temporales ni datos ficticios de respaldo de Marketing. Marketing sigue siendo responsable del resumen canónico; Dashboard sólo lo compone junto a los datos operativos de Orders. Esta integración no convierte Featured Product en un KPI operativo ni cambia el período seleccionado del Dashboard.

## Verificación requerida

La cobertura del backend debe demostrar los límites de períodos, la precedencia de `delivery_date`, la inclusión según estado y pago, el orden de urgencia, las cantidades de snapshots, la distribución de docenas combinadas, las unidades crudas o cocinadas, las cantidades de pedidos con envío o retiro, el completado con ceros de la evolución, la autorización, la hidratación del pedido seleccionado y una cantidad acotada de consultas.

La cobertura de Playwright debe ejercitar los períodos sin ocultar las alertas de hoy, la apertura y el cierre del panel compartido, la navegación a Orders y Quick Order, el diseño adaptable y los estados vacíos. La verificación visual escribe artefactos no versionados en:

- `storage/app/dashboard-desktop.png`
- `storage/app/dashboard-mobile.png`
- `storage/app/dashboard-order-drawer.png`

La verificación es obligatoria para la entrega; este documento no afirma que haya pasado hasta que el informe final de pruebas registre los comandos ejecutados y sus resultados.
