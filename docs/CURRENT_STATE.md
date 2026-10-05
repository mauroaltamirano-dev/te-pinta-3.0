# Estado actual de la implementación

Te Pinta 3.0 cuenta actualmente con un slice vertical completo de pedidos: el catálogo y la configuración alimentan un único motor de precios cuya autoridad reside en el backend, y tanto el POS administrativo como el checkout público conservan los mismos snapshots históricos de los pedidos.

## Disponible actualmente

| Capacidad               | Comportamiento actual                                                                                                                                                                                            |
| ----------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Productos               | Categorías, CRUD, disponibilidad, imágenes y familia de precio explícita: empanadas conservan tiers cocinados/crudos; productos unitarios se venden sólo por cantidad y precio unitario |
| Configuración           | Precios de docena combinada cocinada/cruda, costo de entrega, número de WhatsApp y toppings                                                                                                                      |
| Clientes                | Lista y perfil de gestión responsive en español, teléfono opcional normalizado, notas privadas, agregados e historial de pedidos eficientes, borrado lógico, papelera y restauración segura                      |
| Pedidos administrativos | Lista de tarjetas basada primero en snapshots, creación, edición segura, búsqueda y filtrado, controles rápidos de estado y pago, vista previa y copia del ticket, descuentos, papelera y restauración           |
| Inicio administrativo   | POS de Quick Order centrado en productos, con catálogo móvil de dos columnas, borrador local, vista previa del backend, panel de continuación y confirmación                                                     |
| Dashboard               | Períodos operativos de solo lectura, cola prioritaria de hoy, ventas y cobros confirmados, unidades de productos según snapshots, distribuciones de entrega y condición, y evolución de ventas                   |
| Gestión                 | Billeteras, ledger inmutable, egresos, transferencias y distribuciones conservativas, cobros idempotentes, comprobantes privados, saldos, historial, reversiones y auditoría financiera derivada de solo lectura |
| Marketing               | Historial de Featured Product, programación sin superposiciones, vista previa fiel en administración y sitio público, ubicación en el Inicio público, CTA del carrito y resumen compacto para Dashboard          |
| Checkout público        | Menú, carrito de sesión, vista previa del backend, asociación segura de clientes, persistencia idempotente y derivación a WhatsApp                                                                               |
| Precios                 | Unidad, media docena, docena, docena combinada, toppings, envío, ahorro, descuentos y desglose entre el precio base cocinado y el descuento por elegir productos crudos                                          |
| Seguridad histórica     | Se conservan snapshots de los valores de producto, cliente, entrega, toppings, precio base cocinado y descuento por productos crudos; los snapshots históricos nunca se recalculan con la configuración vigente  |
| Acceso móvil            | Manifiesto e iconos instalables para administración, sin service worker; runtime aislado similar a producción preparado en el loopback `8082`                                                                    |

## Dashboard operativo

- `/admin/dashboard` es una superficie operativa secundaria de solo lectura; `/admin` continúa siendo el flujo de creación de Quick Order.
- El día actual, la semana ISO y el mes calendario se resuelven mediante la zona horaria comercial de Orders y filtran por `delivery_date`, nunca por `created_at`. Cambiar el período no modifica la cola urgente exclusiva del día actual.
- Las ventas suman los totales persistidos de los pedidos con estado `waiting`, `prepared` y `delivered`. Los ingresos cobrados aplican la misma regla de estados confirmados y además exigen `paid`. Los pedidos incluyen los estados pendientes de confirmación y confirmados; las filas canceladas y con borrado lógico se excluyen en todos los casos.
- Los pedidos urgentes contienen únicamente el trabajo pendiente de confirmación, en espera y preparado para hoy. Se ordenan por prioridad de estado, período de entrega, hora exacta —con las horas faltantes al final— y el ID como desempate estable. La lista general de hoy se ordena cronológicamente.
- El ranking de productos y las distribuciones de producción utilizan snapshots persistidos de ítems y composiciones. Las cantidades directas de media docena y docena aportan 6 y 12 unidades; las docenas combinadas distribuyen la composición guardada; cocinadas/crudas mide unidades de productos y entrega/retiro mide pedidos.
- La evolución utiliza intervalos del backend completados con ceros y una presentación SVG liviana y accesible; React no recalcula los agregados del período.
- Los identificadores TP abren el `OrderDetailSheet` perteneciente a Orders y preservan su autorización, acciones, resumen y navegación al detalle completo.
- Dashboard sigue el flujo natural de la página. En dispositivos móviles, muestra el trabajo urgente y los pedidos de hoy antes que los KPI y los indicadores, y evita las tablas anchas y el desplazamiento horizontal de toda la página.
- Dashboard no es Analytics, Inventory, un libro contable ni el responsable de Marketing. Su resumen compacto de recomendaciones consume el modelo de lectura de Marketing. Las definiciones y exclusiones exactas de las métricas operativas se encuentran en [DASHBOARD.md](DASHBOARD.md).
- La verificación requerida del backend de Dashboard, Playwright y la presentación visual se registra en [TESTING.md](TESTING.md); los resultados solo deben informarse después de ejecutar esos comandos.

## Marketing v1

- `/admin/marketing` gestiona una programación de Featured Product y conserva los registros pasados, activos y futuros. Las acciones administrativas permiten crear, editar, previsualizar y deshabilitar; v1 no expone una ruta de borrado físico.
- Las ventanas habilitadas utilizan el intervalo semiabierto `[starts_at, ends_at)`. PostgreSQL rechaza las superposiciones mediante una restricción de exclusión GiST parcial, mientras que la validación de la aplicación devuelve un error de programación comprensible.
- Los instantes se almacenan en UTC y se interpretan y muestran en la zona horaria comercial configurada para Orders. El estado se deriva como inactivo, programado, activo o finalizado; ningún cron avanza los registros.
- Marketing referencia los Products actuales en lugar de copiar nombres, imágenes, disponibilidad, categorías o precios. Las nuevas selecciones deben poder venderse públicamente.
- Una recomendación temporalmente activa aparece en el Inicio público solo mientras su Product y Category continúen siendo aptos para el público. Un Product no disponible o con borrado lógico conserva su historial de Marketing, pero oculta la tarjeta pública.
- La vista previa administrativa y el Inicio público reutilizan el mismo componente de presentación seguro para el público y el mismo modelo de vista. El CTA reutiliza el carrito público existente y agrega una unidad mediante el slug del Product.
- Dashboard consume un resumen compacto de Marketing con los estados activo, siguiente, no disponible o vacío, y enlaza nuevamente a `/admin/marketing`; no es responsable de los comandos de Marketing.
- Marketing v1 no implementa descuentos, promociones, combos, cupones, precios personalizados, carga de banners ni integración con Instagram. Consulte [MARKETING.md](MARKETING.md) y [ADR 0005](decisions/0005-marketing-and-instagram-are-separate-domains.md).

## Automatización de Instagram Stories

Instagram gestiona de forma independiente el interruptor operativo consciente del despliegue, las programaciones locales, los assets privados de Story validados, el encolado manual confirmado y el historial de publicaciones. Laravel es responsable de la generación idempotente de vencimientos y de las transiciones atómicas de la cola; un despliegue independiente de n8n Community Edition en `ops/n8n/` ejecuta únicamente el flujo oficial de creación de contenedores y publicación de Meta, e informa los resultados mediante la API protegida. No es un submódulo de Marketing y Marketing v1 no encola Stories.

Los resultados ambiguos de Meta no se reintentan automáticamente de manera intencional. Permanecen visibles como fallas que requieren revisión manual para que un operador pueda conciliar Instagram y n8n antes de decidir si encola otra Story.

## UI de Orders

- `/admin` es el Inicio operativo: las tarjetas del catálogo incrementan cantidades reales de productos, mientras que el panel fijo del pedido muestra la vista previa autoritativa del backend.
- **Continuar** abre el panel de cliente, condición, complementos, entrega y pago sin persistir. Solo **Confirmar pedido** invoca el almacenamiento existente de pedidos administrativos y el límite `CreateOrder`.
- El mismo hook de borrador y los mismos componentes de campos sustentan `/admin/orders/create`, que continúa siendo el flujo alternativo completo. Dashboard está disponible en `/admin/dashboard`.
- `/admin/orders` utiliza un espacio de trabajo de escritorio limitado por el viewport, con métricas compactas, búsqueda en el encabezado, pestañas de grupos de estados, un panel avanzado de filtrado y ordenamiento, y densidad de grilla o lista persistida en la URL. Ambas vistas exponen entrega, condición, cada variedad de ítem y composición, toppings, total, estado, pago y acceso al comprobante.
- El contenido de las tarjetas proviene únicamente de snapshots persistidos de ítems, composiciones y toppings del pedido. La lista no incorpora intencionalmente imágenes actuales de los productos, porque un asset de catálogo editado o eliminado no constituye evidencia histórica del pedido. El modelo de lectura paginado carga anticipadamente todas las filas anidadas con una cantidad acotada de consultas.
- Seleccionar un pedido abre un diálogo compacto de comprobante mediante `/admin/orders?order=TP-*` sin abandonar la lista filtrada. Solo expone Copiar, Editar y cerrar; la ruta directa `/admin/orders/{public_id}` continúa siendo la página de detalle completo.
- La lista cambia el estado operativo mediante el endpoint tipado existente. El pago es un interruptor binario para totales positivos: activarlo exige un método de pago y desactivarlo elimina el método. Un pedido con total cero se muestra como **No requiere pago** en lugar de registrar un pago inexistente.
- El detalle completo conserva `OrderDetailContent`; el diálogo compacto y el resultado exitoso de Quick Order reutilizan el mismo resumen `formatAdmin` del backend y la misma acción de portapapeles, en lugar de reconstruir el texto de copia en React.
- Ambas superficies de detalle enlazan a `/admin/orders/{public_id}/edit`. Los pedidos activos abren directamente el formulario completo compartido; los pedidos entregados o cancelados requieren una autorización explícita, justificada, vinculada a la versión y válida para una única edición, y conservan su estado existente.
- La hidratación de la edición reconstruye cantidades reales de productos y complementos a partir de las filas estructuradas de ítems, composiciones y toppings. Nunca interpreta etiquetas de presentación como “combined dozen”. La página muestra el total persistido y la vista previa actual del backend con su diferencia antes de confirmar.
- Las ediciones económicas vuelven a ejecutar el motor de precios existente con el catálogo y la configuración actuales, y reemplazan atómicamente los snapshots solo para ese pedido. Las correcciones no económicas conservan la cotización histórica sin recalcular precios innecesariamente.
- La columna derecha muestra los productos reales más pedidos y distribuciones separadas por entrega y por condición cocinada/cruda.
- El texto visible de Orders y las etiquetas de estado centralizadas están en español; los valores internos de enum permanecen en inglés.
- En escritorio `xl`, el documento de Orders no se desplaza: el encabezado, las métricas, las pestañas, los controles y el recuento permanecen fijos, mientras que la región de resultados identificada controla el desbordamiento vertical y contiene tarjetas completas junto con la paginación. Su altura inicial permite ver una primera fila típica de la grilla hasta los controles de pago y comprobante. La columna derecha de indicadores se limita de manera independiente al mismo viewport. En dispositivos móviles se recupera el flujo natural del documento con una columna, pestañas de estado desplazables horizontalmente, controles de 44px y presentaciones compactas diferenciadas para grilla y lista.
- Las pestañas de grupo corresponden a `all`, `in_progress`, `delivered` o `cancelled`; un estado exacto tiene precedencia y normaliza su grupo. El ordenamiento del backend solo permite fecha de entrega, snapshot del cliente, total o ID interno del pedido, más el ID como desempate estable, con dirección ascendente o descendente validada.
- En dispositivos móviles se utiliza una navegación inferior al alcance del pulgar con destinos principales y un panel “Más”; Quick Order muestra dos tarjetas de producto por fila con objetivos de cantidad de 44px.

## UI de Customers

- `/admin/customers` es un espacio de trabajo administrativo denso, con presentación en español, búsqueda por nombre o teléfono, recuento total y paginación del servidor. El backend restringe el ordenamiento a nombre, cantidad de pedidos activos o gasto histórico efectivo, con el ID como desempate estable; la búsqueda, el orden y la dirección se conservan durante la paginación. En escritorio se exponen encabezados ordenables accesibles, mientras que en dispositivos móviles se utiliza un control compacto equivalente y filas pulsables para evitar forzar las columnas de la tabla dentro del viewport.
- Cada resultado expone la cantidad de pedidos activos, la fecha del último pedido activo y el gasto histórico efectivo existente. Los clientes sin pedidos muestran **Sin compras** en lugar de destacar `$0` como una métrica útil.
- `/admin/customers/{customer}` es el perfil operativo del cliente: resumen de contacto, cantidad de pedidos, gasto efectivo, pedido más reciente, notas privadas e historial de pedidos activos desde el más reciente. El historial incluye entrega, estado operativo, pago y total.
- Seleccionar un identificador TP mantiene el perfil del cliente en su lugar y abre el `OrderDetailSheet` perteneciente a Orders. El panel es solo un contenedor responsive alrededor del `OrderDetailContent` canónico, por lo que la copia, la edición, la navegación al detalle completo y las acciones autorizadas de estado y pago no se vuelven a implementar en Customers.
- La creación y la edición conservan las solicitudes y acciones existentes de Customers y la normalización centralizada del teléfono. El borrado continúa siendo un traslado a la papelera exclusiva de administradores, protegido por una confirmación; la papelera utiliza presentaciones compactas para escritorio y dispositivos móviles, y muestra la cantidad de pedidos activos asociados antes de restaurar.
- El modelo de lectura de la lista utiliza carga anticipada del último pedido junto con consultas agregadas de conteo y suma, por lo que la cantidad de consultas no crece por cliente. `orders_customer_history_index` permite el historial activo por cliente y las lecturas del último pedido.
- Las capturas comparativas de Playwright cubren la lista y el perfil en escritorio y dispositivos móviles, además del perfil con el panel de detalle del pedido, dentro de `storage/app/`; estos archivos generados no se versionan.

## Ciclo de vida administrativo

- Orders y Customers utilizan borrado lógico recuperable. Sus páginas de papelera son exclusivas para administradores y permiten restaurar, no eliminar de forma permanente.
- Restaurar un pedido conserva su `public_id` y sus snapshots. Eliminar o restaurar un cliente nunca propaga la operación a los pedidos.
- La restauración de un cliente se rechaza sin mutaciones cuando otro cliente activo ya posee el mismo teléfono normalizado; el administrador debe resolver ese registro activo y volver a intentarlo. Los registros nunca se fusionan silenciosamente ni pierden los datos telefónicos.
- Los métodos de pago son `cash`, `bank_transfer` y `debit_card`. Un pedido pagado requiere uno; un pedido no pagado no almacena ninguno. Los totales cero calculados por el backend utilizan `not_required` sin método. El checkout público no puede enviar descuentos ni métodos de pago.
- La limpieza de clientes del entorno de desarrollo siguió el control de aprobación: la auditoría inicial solo ejecutó SELECT con teléfonos enmascarados; luego, los IDs de cliente `1`, `3`, `4`, `5`, `10`, `11`, `12`, `13`, `14` y `15`, aprobados explícitamente, se enviaron a la papelera recuperable mediante `DeleteCustomer`. Los IDs `16` y `19` se conservaron explícitamente como clientes reales; no se ejecutaron eliminaciones físicas ni limpiezas basadas en nombres inferidos.

## Gestión financiera

- `/admin/management` reemplaza el placeholder `/admin/inventory`. El módulo técnico es `Management`: Gestión crecerá hacia operaciones financieras, mientras que Inventory queda reservado para stock.
- Las billeteras no almacenan un saldo editable. Cada saldo y el total de billeteras activas se derivan de la suma de entries firmadas del ledger.
- Aperturas, ajustes e ingresos/egresos manuales crean una cabecera financiera y su entry dentro de una transacción PostgreSQL. El usuario ingresa un valor positivo y el backend determina el signo.
- Compras, gastos y retiros personales se registran como `ManagementOutflow` inmutables con categorías persistidas por tipo. Cada origen crea exactamente una cabecera `management_outflow` y una entry negativa coincidente dentro de la misma transacción PostgreSQL.
- Las transferencias se registran como `ManagementTransfer` inmutables. Cada una crea una cabecera `transfer` y exactamente dos entries opuestas —salida de origen y entrada de destino— cuya suma es cero; no altera ingresos, egresos operativos, retiros ni el saldo global.
- Las transferencias bloquean ambas billeteras en orden ascendente de ID para evitar deadlocks entre sentidos opuestos. Origen y destino deben ser distintos y estar activos; los saldos negativos continúan permitidos.
- Las reglas de distribución separan una identidad activable de versiones inmutables. Una sola regla puede estar activa globalmente; sus allocations usan basis points enteros, suman exactamente `10.000`, no repiten billeteras y sólo aceptan destinos activos para versiones nuevas.
- Una distribución manual guarda versión y snapshots relacionales, debita una billetera origen y acredita N destinos. El remainder se asigna a la última allocation canónica; origen y destinos conservan exactamente el importe y la suma completa del ledger es cero.
- Cuando un Order administrativo transiciona a `paid`, Management registra síncronamente el ingreso y aplica la versión actual de la regla activa desde la billetera receptora. La distribución vinculada debita el importe completo, acredita los destinos y suma cero.
- Cada pago tiene cero o una distribución mediante una referencia anulable y única. Sin regla activa queda visible como pendiente y admite reintento; una falla técnica del reparto revierte la transacción síncrona completa.
- `paid` permanece como estado operativo mutable; la fila financiera única por `order_id` es la evidencia durable. Cancelar o volver a no pagado no revierte automáticamente el ledger y los pedidos pagados anteriores al slice no reciben un backfill inferido.
- Una edición económica posterior no muta el cobro original. La distribución debe revertirse antes que el pago; volver a `paid` no recrea ninguno de los dos hechos.
- Las billeteras se archivan, no se eliminan. Los movimientos nuevos rechazan billeteras archivadas; el historial conserva su nombre y una reversión puede agregar el impacto opuesto aun después del archivado.
- Las transacciones, entries, egresos, transferencias, versiones y distribuciones confirmados son inmutables. Cada hecho financiero admite una única reversión enlazada que agrega entries opuestas; el original nunca se edita ni elimina.
- Los saldos negativos están permitidos. Las métricas de ingreso incluyen cobros confirmados de Orders; egresos operativos, retiros, transferencias y distribuciones permanecen separados. No calculan ganancia ni rentabilidad.
- `/admin/management/audit` reconcilia el ledger con pagos, egresos, transferencias y distribuciones mediante findings derivados. No persiste errores, no almacena saldos y nunca corrige datos automáticamente.
- La auditoría distingue pendientes válidos, reversiones e históricos archivados de inconsistencias de importe, billetera, fecha, relación o estructura. Los Orders legacy sin `ManagementOrderPayment` no se infieren ni bloquean el estado financiero.
- Cada `FinancialTransaction` puede tener varios comprobantes privados opcionales. El archivo documenta el hecho sin modificar tipo, importe, fecha, entries, saldo ni historial financiero; las operaciones existentes sin comprobante siguen siendo válidas.
- Los comprobantes admiten PDF, JPEG, PNG y WEBP de hasta `10 MB`, se validan por MIME real y se almacenan en el disco privado `local` bajo paths UUID sin PII. La descarga y el preview requieren autorización administrativa y nunca exponen disk o path internos.
- Los bytes y su metadata son inmutables. Quitar un comprobante crea un tombstone con administrador, fecha y motivo, deja de servirlo y conserva el blob físico hasta que exista una política explícita de retención. Una reversión conserva la evidencia del original y puede recibir documentación propia.
- El historial sólo obtiene `attachments_count`; la metadata se carga al abrir el detalle y los bytes nunca participan del overview ni de la auditoría. No tener comprobante no es un finding de Slice 6.
- La arquitectura completa y los límites futuros se encuentran en [MANAGEMENT.md](MANAGEMENT.md), [ORDERS.md](ORDERS.md) y [ADR 0009](decisions/0009-idempotent-order-payments-in-management.md).

## Invariantes de precios

- El backend es la única autoridad sobre los totales.
- `items_subtotal` conserva el total de mercadería cocinada o cruda aplicado.
- En pedidos nuevos, el catálogo y la configuración expresan valores cocinados como precio base; el descuento positivo por elegir productos crudos se resta para obtener `items_subtotal`.
- La docena combinada cuesta `$17,500` cocinada por defecto y `$16,000` cruda como alternativa de menor precio, por lo que la elección cruda descuenta `$1,500`.
- Agregar el desglose nunca modifica el total final.
- Los descuentos administrativos porcentuales o de importe fijo se aplican a los ítems —ya con la alternativa cocinada o cruda elegida— más los toppings, nunca al envío. El backend los limita a ese subtotal descontable.
- Una edición económica confirmada reemplaza los snapshots de cotización del pedido editado con las reglas actuales. Abrir un pedido, cancelarlo o editar únicamente campos no económicos no reescribe el historial de precios; los snapshots históricos nunca se recalculan.

## Estado del runtime

- La identidad visual aprobada de Te Pinta está activa en el lateral administrativo de escritorio y en el layout de inicio de sesión mediante un componente de imagen dedicado y un asset cuadrado optimizado. Las superficies públicas, de comprobantes, WhatsApp, favicon, Apple touch e iconos PWA permanecen sin cambios hasta contar con derivados específicos para cada formato.
- La superficie administrativa puede instalarse desde su origen HTTPS estable con `start_url: /admin`, visualización standalone, iconos y un acceso directo para crear pedidos. Intencionalmente no existe un service worker ni una caché de pedidos sin conexión.
- `deploy/tailscale` puede construir y preparar una imagen similar a producción en `127.0.0.1:8082`, verificar los assets compilados, los medios públicos de productos y el estado de salud, y realizar un cambio o una reversión sujetos a confirmación.
- Tailscale Serve expone actualmente el runtime verificado de Te Pinta 3.0 en `https://tuki-notebook.tail2b1906.ts.net` mediante `127.0.0.1:8082`. Te Pinta 2.0 continúa disponible localmente en `127.0.0.1:8080` como destino de reversión probado.
- La validación del calendario de pedidos utiliza la zona horaria comercial configurada para Argentina (`America/Argentina/Buenos_Aires` de forma predeterminada); los timestamps de la aplicación y el almacenamiento de la base de datos permanecen en UTC.

## Pospuesto intencionalmente

- Analytics e Inventory/stock continúan fuera de alcance. Gestión no implementa inventario.
- El CRUD de categorías, el Dashboard financiero avanzado y la reparación/reversión automática quedan para slices posteriores. Las compras todavía no modifican Inventory/stock.
- El pago en línea no forma parte del producto; la confirmación del checkout utiliza WhatsApp.

## Ruta de revisión

1. Lea [ARCHITECTURE.md](ARCHITECTURE.md) para conocer los límites entre módulos.
2. Lea [ORDER_PRICING.md](ORDER_PRICING.md) antes de modificar la agrupación o los totales.
3. Utilice [PROJECT_MAP.md](PROJECT_MAP.md) para encontrar al responsable de cada regla.
4. Ejecute las verificaciones documentadas en [TESTING.md](TESTING.md).

## POS-1 — Order Context & Access

Se prepara el dominio para source `pos` y fulfillment `dine_in` inmediato: Customer/contacto/programación pueden estar ausentes, dirección no aplica y shipping es cero. Source y fulfillment son conceptos independientes; el checkout público y el alta admin mantienen sus contratos. Las migraciones no reescriben historia y los consumidores existentes toleran Customer null. Identity ofrece perfiles fijos waiter/cashier y capacidades backend sin ampliar el límite administrativo.

Este slice no implementa mesas, sesiones, plano, submissions, comandas, impresión, packaging, fulfillment físico, cierre, pagos POS ni deployment LAN. Tampoco recupera Quick Order ni integra el rediseño público congelado. POS-2 puede consumir estos contratos una vez cerrada la verificación/revisión del candidato; evidencia y pendientes en `odd/tasks/pos-1-order-context-access.md`.
