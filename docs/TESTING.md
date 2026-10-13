# Pruebas

Las pruebas demuestran la autoridad del backend, la estabilidad histórica y los dos flujos de creación de pedidos. La validación del frontend por sí sola nunca cubre seguridad ni precios.

## Suites específicas

| Suite                                                            | Demuestra                                                                                                                                                                                                                                                                          |
| ---------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `tests/Feature/Customers/PhoneNormalizerTest.php`                | Normalización determinista solo a dígitos y manejo de nulos                                                                                                                                                                                                                        |
| `tests/Feature/Customers/CustomerManagementTest.php`             | Límite administrativo, nombres duplicados, teléfono opcional, unicidad de teléfono activo, búsqueda, orden estable permitido, agregados con cantidad constante de queries, perfil/historial privado, hidratación del pedido propio seleccionado, eliminación lógica y logs seguros |
| `tests/Feature/Customers/CustomerTrashRestorationTest.php`       | Papelera/restauración administrativa, pedidos asociados sin cambios, restauración solo activa y rechazo sin mutaciones ante conflicto telefónico                                                                                                                                   |
| `tests/Feature/Orders/OrderPricingEngineTest.php`                | Agrupación de empanadas, snapshots de precios cocidos base y crudos, descuentos/redondeo/topes, toppings, entrega, límites y posibilidad de venta; los campos `cooking_*` se cubren solo como compatibilidad histórica heredada                                                                 |
| `tests/Feature/Products/ProductPricingFamilyTest.php`             | Productos `unit` por cantidad, sin descuento crudo ni agrupación de tiers, y cotizaciones mixtas con empanadas                                                                                                                                                                        |
| `tests/Feature/Orders/OrderCheckoutHttpTest.php`                 | Creación pública/administrativa, fecha de negocio de Argentina, descuento del backend, invariante de pago, copia de ticket, etiquetas históricas genéricas de productos, snapshots, idempotencia y throttling                                                                                                                   |
| `tests/Feature/Orders/OrderCardReadModelTest.php`                | Serialización paginada de snapshots, cantidad acotada de queries, semántica de estado amplia/exacta, orden estable permitido y contrato de query de vista                                                                                                                          |
| `tests/Feature/Orders/OrderDatabaseConstraintTest.php`           | Secuencia PostgreSQL, restricciones de snapshots/descuento/pago, verificaciones de compatibilidad heredada `cooking_*` y hash único de idempotencia                                                                                                                                |
| `tests/Feature/Orders/AdminOrderEditingTest.php`                 | Edición activa/cerrada, cantidades estructuradas, recálculo con reglas actuales, ID inmutable, auditoría, rollback, historial no disponible y concurrencia                                                                                                                         |
| `tests/Feature/Orders/OrderTrashRestorationTest.php`             | Papelera y restauración solo administrativas mediante ID público inmutable                                                                                                                                                                                                         |
| `tests/Feature/Settings/OrderingSettingsManagementTest.php`      | Configuración tipada solo administrativa y actualizaciones atómicas                                                                                                                                                                                                                |
| `tests/Unit/Core/TestingDatabaseGuardTest.php`                   | Límite del nombre de base de datos exclusivo de testing, precedencia de `DB_URL` y bypass fuera de testing                                                                                                                                                                         |
| `tests/e2e/orders.spec.ts`                                       | Bloqueo del viewport de escritorio y desplazamiento propio de resultados, flujo natural móvil, densidad grilla/lista, pestañas de estado, filtros/orden, acciones completas de primera fila, ticket/copia, estado/pago rápido y edición segura                                     |
| `tests/e2e/customers.spec.ts`                                    | Lista desktop/mobile, búsqueda, orden permitido, paginación, perfil, edición de datos privados, historial de pedidos, hoja de detalle compartida, cierre que vuelve al perfil y capturas                                                                                           |
| `tests/Feature/Orders/AdminQuickOrderHomeTest.php`               | Autorización de Quick Home, filtrado de catálogo/categorías vendibles, props y separación de Dashboard                                                                                                                                                                             |
| `tests/e2e/admin-quick-order.spec.ts`                            | Cantidades rápidas, descuento/pago del backend, copia/detalle exitosos, confirmación exactamente una vez, cancelar/descartar y capturas                                                                                                                                            |
| `tests/Feature/Orders/OrderDashboardDataTest.php`                | Agregado real de productos más pedidos, composición de docena combinada, imágenes, distribuciones de entrega y condición cruda/cocida y eliminaciones lógicas                                                                                                                      |
| `tests/e2e/public-checkout.spec.ts`                              | Persistencia previa a la navegación simulada a WhatsApp                                                                                                                                                                                                                            |
| `tests/e2e/trash-restoration.spec.ts`                            | Flujo de restauración desde papelera de Orders/Customers y feedback accionable ante conflicto de teléfono                                                                                                                                                                          |
| `tests/Feature/PwaManifestTest.php`                              | Metadatos del manifest, iconos/acceso directo, vínculo con layout y ausencia de service worker                                                                                                                                                                                     |
| `tests/Feature/Dashboard/`                                       | Autorización obligatoria de Dashboard, semántica de período/KPI, alias histórico `empanadas_count` como unidades de productos de snapshots, orden de alertas, distribuciones, buckets de evolución, hidratación del pedido seleccionado y queries acotadas                                                                           |
| `tests/e2e/dashboard.spec.ts`                                    | Período obligatorio, alerta de hoy persistente, drawer compartido, navegación, dispositivos móviles, estado vacío y flujos visuales de Dashboard                                                                                                                                   |
| `tests/Feature/Marketing/`                                       | Autorización de Featured Product, límites temporales, comportamiento de superposición/restricciones, elegibilidad/privacidad pública, resumen de Dashboard y logs seguros                                                                                                          |
| `tests/e2e/marketing.spec.ts`                                    | Creación/preview/actualización/deshabilitación/programación/superposición administrativa, Home/carrito público real, estados responsive y capturas obligatorias                                                                                                                    |
| `tests/Feature/Instagram/InstagramAutomationTest.php`            | Programación por hora/día/switch global, idempotencia, token/reclamo atómico, callbacks/reintentos/revisión manual, aislamiento/vencimiento de medios firmados y normalización real de imágenes                                                                                    |
| `tests/Feature/Instagram/InstagramStorageConfigurationTest.php`  | Aislamiento del disco local de testing y permisos privados de grupo para los medios compartidos por ambos runtimes                                                                                                                                                                 |
| `tests/Feature/Instagram/InstagramHistoryAdministrationTest.php` | Filtros/búsqueda/paginación del historial, archivado conservador, reintento/cancelación seguros, referencias de assets, días ISO, orden determinista a la misma hora y autorización                                                                                                |
| `tests/Feature/Management/`                                      | Ledger y orígenes semánticos, pagos idempotentes, distribución automática, auditoría read-only, comprobantes privados, validación real de archivos, tombstones, balances derivados, autorización, atomicidad y queries acotadas                                                    |
| `tests/e2e/management.spec.ts`                                   | Billeteras y movimientos aislados, distribución manual, pago de Order, comprobantes múltiples y descarga, reversión con evidencia preservada, auditoría, saldos e historial enlazado en desktop/mobile                                                                             |

La matriz de precios incluye 5/6/7/12/18 unidades de un producto, 3+3, 6+3+3, 6+6 y 24 unidades variadas. Cuando cambie una regla de agrupación, actualizar juntos `docs/ORDER_PRICING.md` y sus pruebas específicas.

## Comandos

```bash
# Focused backend
make artisan ARGS="test tests/Feature/Customers tests/Feature/Dashboard tests/Feature/Instagram tests/Feature/Management tests/Feature/Marketing tests/Feature/Orders tests/Feature/Settings"

# Focused browser
make npm ARGS="run test:e2e -- tests/e2e/customers.spec.ts tests/e2e/dashboard.spec.ts tests/e2e/management.spec.ts tests/e2e/marketing.spec.ts tests/e2e/orders.spec.ts tests/e2e/public-checkout.spec.ts"

# Complete PHP gate: Pint check + PHPStan + Laravel suite
make test

# Complete CI checks, frontend build, and browser suite
make check
make e2e

# Dependency audits
make audit
```

`make check` combina `composer ci:check` con el build de producción de Vite. No ejecuta Playwright ni auditorías de dependencias; usar `make e2e` y los comandos explícitos de auditoría anteriores.

Consultar [COMMANDS.md](COMMANDS.md) para conocer los alias canónicos de comandos y la sintaxis de passthrough.

Las capturas visuales de Quick Order, tarjetas/ticket de Orders, Customers, Dashboard y Marketing se escriben en `storage/app/`. Orders genera `orders-workspace-{desktop,mobile}-{grid,list}.png`; Customers genera `customers-{desktop,mobile}.png`, `customer-profile-{desktop,mobile}.png` y `customer-profile-order-detail-{desktop,mobile}.png`. Dashboard genera `dashboard-desktop.png`, `dashboard-mobile.png` y `dashboard-order-drawer.png`. Marketing genera `marketing-admin-desktop.png`, `marketing-admin-mobile.png`, `marketing-public-home.png`, `marketing-public-mobile.png` y `dashboard-marketing-summary.png`. Son artefactos de verificación y no se versionan.

## Invariantes de regresión obligatorias

- Los totales, precios, envío y precio combinado del cliente no pueden modificar los valores persistidos.
- El input público no puede establecer IDs internos, estado de pago ni estado operativo.
- No se pueden vender entradas del catálogo eliminadas o no disponibles.
- Los reintentos públicos exactos crean un solo pedido; se rechaza reutilizar una clave con un payload diferente.
- Los cambios de Product, dirección del cliente, costo de envío y topping no modifican los snapshots.
- Las tarjetas de Orders serializan snapshots de ítems/composición/toppings con una cantidad acotada de queries y nunca sustituyen nombres ni imágenes históricos por los actuales de Product.
- Las pruebas de queries de Orders rechazan valores desconocidos de orden, grupo, dirección o vista. Playwright demuestra pestañas, filtros exactos, densidad y acciones persistidas en la URL; en escritorio la altura del documento coincide con el viewport y la región de resultados nombrada controla el overflow y contiene por completo la fila inicial de tarjetas, mientras que en dispositivos móviles se conserva el flujo natural.
- Los pedidos nuevos guardan por línea snapshots del precio base cocido y del precio crudo alternativo; los pedidos históricos nunca los derivan de la configuración actual. Los campos técnicos `cooking_*` se validan solo como compatibilidad histórica heredada y no como recargo vigente.
- El desglose de compatibilidad nunca modifica el total final; en una docena combinada la diferencia sigue siendo precio cocido menos precio crudo (`17500 - 16000 = 1500`).
- Los descuentos porcentuales o fijos se calculan en el backend sobre los ítems y toppings, excluyen el envío, redondean y limitan correctamente y guardan un snapshot del resultado aplicado.
- Los pedidos pagados requieren un método válido; los no pagados no persisten ninguno; el checkout público no puede establecer método de pago ni descuento.
- El mínimo de fecha del checkout público proviene de `OrderBusinessDate` en la zona horaria de Argentina configurada y su error se presenta como `dd/mm/yyyy`, no desde el UTC del navegador. La creación administrativa permite fechas pasadas para registrar pedidos históricos; los timestamps permanecen almacenados en UTC.
- Las notas privadas del cliente nunca aparecen públicamente ni en WhatsApp.
- Los agregados de la lista de clientes usan una cantidad acotada de queries, recuento de pedidos activos/fecha más reciente y la misma regla de gasto efectivo que el perfil.
- La selección de pedidos de un cliente solo puede hidratar un pedido activo perteneciente a ese perfil; se rechazan identificadores inválidos, eliminados o ajenos.
- Los pedidos `cancelled` permanecen en el historial, pero no contribuyen al gasto efectivo.
- Los invitados y no administradores no pueden acceder a operaciones administrativas.
- Wallet no contiene un saldo mutable: cada saldo y el total operativo se derivan de entries; los saldos negativos permanecen registrables y visibles.
- El importe visual de Gestión es positivo y el backend determina el signo según el tipo. Cero y fracciones se rechazan también mediante constraints PostgreSQL.
- Cada cabecera financiera se confirma con sus entries o revierte por completo. Las cabeceras y entries existentes rechazan actualización y eliminación directa.
- Archivar una billetera conserva historia e impide movimientos manuales ordinarios nuevos. Una reversión puede compensar la misma billetera archivada sin reabrirla.
- Una reversión crea entries exactamente opuestas, conserva intacto el original y sólo puede ocurrir una vez, incluso bajo escrituras concurrentes. Una falla revierte toda la corrección.
- Los filtros de Management sólo aceptan billetera existente, tipo cerrado, categoría existente y período válido; la cantidad de consultas permanece acotada al crecer billeteras, entries, egresos, transferencias, categorías y administradores.
- Cada compra, gasto o retiro recibe un importe positivo y crea atómicamente un `ManagementOutflow`, una cabecera `management_outflow` y exactamente una entry negativa coincidente; una falla intermedia revierte las tres filas.
- Una categoría inactiva o de otro tipo se rechaza para operaciones nuevas. Su tipo/código no puede mutar y una categoría utilizada no puede eliminarse; desactivarla conserva historia.
- Los egresos confirmados no se actualizan ni eliminan. Su reversión conserva el origen, crea la entry opuesta una sola vez y continúa permitida si la billetera fue archivada después.
- El historial distingue compra, gasto y retiro, filtra por semántica/categoría y las métricas separan compras/gastos operativos de retiros personales usando las entries y fechas efectivas del ledger.
- Cada transferencia recibe un importe positivo, exige billeteras activas distintas y crea atómicamente un `ManagementTransfer`, una cabecera `transfer` y exactamente dos entries: `-amount` en origen y `+amount` en destino.
- Las entries de una transferencia suman cero: disminuye el saldo de origen, aumenta el de destino y conserva el saldo global sin contribuir a ingresos, egresos operativos ni retiros personales. El saldo insuficiente no impide registrar el hecho.
- Origen y destino se bloquean por ID ascendente para que transferencias opuestas compartan el mismo orden. La reversión conserva el original, crea dos entries contrarias una sola vez y funciona después de archivar una o ambas billeteras.
- El historial presenta cada transferencia como un único hecho semántico; filtrar por billetera incluye origen o destino y el período usa `effective_date`.
- Sólo un administrador crea reglas/versiones o cambia su estado. Cada versión usa allocations positivas hacia billeteras activas, sin duplicados, y suma exactamente `10.000` bps; una versión nueva no modifica las anteriores.
- Una distribución recalcula en backend `floor(amount * percentage_bps / 10.000)`, asigna todo el remainder a la última allocation ordenada por `(sort_order, wallet_id)` y rechaza la operación completa si cualquier resultado es cero.
- Aplicar una versión crea atómicamente un `ManagementDistribution`, una cabecera `distribution`, una entry negativa de origen, N entries positivas y N snapshots. Los créditos suman el importe y la operación completa suma cero.
- Las distribuciones no alteran ingresos, egresos operativos, retiros, transferencias ni saldo global; sólo cambia la composición entre billeteras.
- Una distribución confirmada no se edita ni elimina. La única reversión conserva el original y crea todas las entries opuestas, incluso con destinos archivados; una segunda reversión y las fallas intermedias no dejan cambios parciales.
- El historial colapsa cada distribución en un único hecho con regla y versión; el detalle expone allocations. Los filtros y el overview mantienen una cantidad acotada de queries al crecer reglas, versiones, snapshots y entries.
- Los tres caminos administrativos hacia `paid` —creación, edición completa y control rápido— crean exactamente un `ManagementOrderPayment`, una cabecera `order_payment` y una entry positiva por el total final en la billetera predeterminada.
- Un Order no pagado no crea filas financieras. Repetir el evento o volver a `paid` no duplica el ingreso porque `order_id` es único; una falla en el listener revierte también la transición operativa.
- Un cobro incrementa ingresos y saldo global por su importe sin modificar egresos, retiros, transferencias ni distribuciones. Historial y filtro por billetera preservan Order, cliente, destino y fecha comercial de confirmación.
- La configuración rechaza más de una billetera predeterminada y una predeterminada archivada. Los pagos ya existentes antes de la migración no reciben backfill.
- Sólo una regla puede estar activa globalmente. Cada pago con regla activa usa su versión más reciente, conserva esa versión/snapshot y crea como máximo una distribución vinculada aun ante eventos repetidos o concurrentes.
- La distribución automática usa exactamente la billetera, importe y fecha efectiva del pago; su débito y créditos suman cero, de modo que el ingreso cuenta una vez y el saldo global sólo aumenta por `order_payment`.
- Sin regla activa, el pago persiste como pendiente sin filas parciales y puede reintentarse cuando exista configuración. Una falla técnica después de seleccionar regla revierte también pago y transición `paid` porque el listener es síncrono.
- Cancelar o volver a no pagado no revierte automáticamente el cobro. Una distribución aplicada debe revertirse antes del pago; ambas compensaciones conservan los originales y una segunda reversión falla.
- Una edición económica posterior no muta el importe financiero original; revertir y volver a `paid` tampoco crea un segundo origen ni una segunda distribución. La suite debe hacer visible esta limitación hasta que exista un modelo de pagos múltiples.
- Sólo administradores pueden consultar la auditoría. Cada balance se deriva de `ledger_entries`; abrir o filtrar la pantalla no modifica cabeceras, entries ni entidades semánticas.
- Un pago consistente, una distribución conservativa, una transferencia neutral, un egreso coincidente y una reversión exactamente opuesta no generan falsos críticos. Ingresos y egresos reales tampoco se interpretan como desbalance global.
- Sólo un pago íntegro, no revertido y sin distribución produce `pending_distribution`; una inconsistencia del propio pago se clasifica por su finding estructural y no se presenta como un pendiente sano. El pendiente deja de aparecer al aplicar el reintento existente. Reversiones de pagos o distribuciones permanecen visibles con severidad no crítica.
- Diferencias de importe, billetera o fecha y estructuras inválidas de pago, distribución, transferencia, egreso o reversión producen el finding crítico correspondiente. Las pruebas de corrupción controlada deben restaurar siempre la integridad y no depender de que la aplicación permita crear ese estado.
- Cero reglas activas o cero wallets predeterminadas producen warning. Más de una sería critical sólo si puede demostrarse bajo un escenario PostgreSQL controlado; no se deshabilitan constraints únicamente para fabricar cobertura superficial.
- Una wallet archivada utilizada sólo por historia y los Orders legacy pagados sin `ManagementOrderPayment` no generan findings. Los filtros de auditoría por fecha, billetera, severidad y estado permanecen autorizados y acotados.
- `type` acepta tanto tipos de operación/configuración como códigos concretos de finding. Las pruebas demuestran ambas dimensiones: filtrar `distribution` selecciona findings por su entidad y filtrar `amount_mismatch` selecciona esa inconsistencia entre operaciones.
- El resumen y los estados de billetera se calculan y presentan dentro del mismo alcance filtrado. Un filtro de finding no puede hacer pasar como salud global un `ok` que sólo describe filas excluidas; `info` no degrada una billetera, mientras que `warning` y `critical` usan la mayor severidad vinculada visible.
- La cantidad de queries de auditoría permanece acotada al crecer wallets y transacciones; no existe una consulta de origen semántico por fila ni un recálculo completo fuera del período solicitado.
- Sólo un administrador puede adjuntar, consultar, previsualizar, descargar o crear el tombstone de un comprobante. Invitados, usuarios no administradores e IDs manipulados no reciben metadata ni bytes.
- Cada comprobante referencia una `FinancialTransaction` existente. Adjuntar uno o varios archivos no crea cabeceras financieras ni entries, no cambia balances o importes y no agrega movimientos al historial.
- PDF, JPEG, PNG y WEBP reales de hasta `10 MB` se aceptan. MIME fuera de la allowlist, contenido ejecutable, HTML/SVG/ZIP, extensión engañosa, nombre malicioso y exceso de tamaño se rechazan en backend sin persistir filas ni archivos.
- El nombre original se conserva sólo como metadata; el path usa UUID bajo el prefijo privado de Management. Las pruebas incluyen `../../etc/passwd` y variantes y demuestran que ningún nombre controlado por el usuario decide una ubicación o encabezado inseguro.
- MIME detectado, tamaño, `checksum_sha256`, `uploaded_by` y timestamps coinciden con los bytes confirmados. Repetir contenido puede producir attachments distintos: el checksum no implementa deduplicación.
- Si falla storage no se crea metadata; si falla PostgreSQL después del write se elimina el archivo recién creado. Las pruebas no borran evidencia confirmada para simular cleanup.
- El disco privado no expone URLs directas. Preview y descarga vuelven a autorizar, no serializan `disk` ni `path`, sirven sólo el registro solicitado y responden de forma controlada ante blob ausente, ubicación fuera del prefijo o MIME inesperado.
- El contenido de un attachment confirmado no se reemplaza. Eliminar exige un motivo de 3 a 240 caracteres y crea un único tombstone con administrador y fecha; deja de listar/servir el documento, conserva metadata y blob físico y PostgreSQL rechaza mutaciones o `DELETE` directos. El evento correlacionado no contiene nombre, path, checksum, motivo ni bytes.
- Una reversión conserva todos los comprobantes del original y puede recibir los propios sin moverlos ni copiarlos. La ausencia de comprobante sigue siendo válida y no genera warning o critical en la auditoría financiera.
- El overview no carga metadata completa ni bytes. `attachments_count` y la cantidad de queries permanecen acotados al crecer transacciones y comprobantes; abrir un detalle carga únicamente los attachments activos de esa cabecera.
- Cargas múltiples y simultáneas generan paths únicos y filas independientes. Ningún upload parcial puede sobrescribir otro archivo ni dejar una fila apuntando fuera del storage permitido.
- Se rechaza una edición con `expected_version` obsoleta, incluso cuando otra superficie cambió el estado o el pago.
- Editar pedidos `delivered` o `cancelled` requiere un permiso con motivo vinculado a la versión; una actualización exitosa lo consume sin cambiar implícitamente el estado.
- Las ediciones económicas recalculan el pedido completo con las reglas actuales y reemplazan únicamente sus snapshots; las ediciones no económicas conservan la cotización persistida.
- Las selecciones históricas no disponibles siguen visibles y sin cambios durante correcciones no económicas, pero bloquean el recálculo con reglas actuales hasta que se eliminen o reemplacen.
- Una falla de revisión revierte en conjunto campos, totales, reemplazo de ítems/composición/toppings, versión y auditoría.
- Solo un administrador puede restaurar Orders/Customers eliminados lógicamente; no se expone eliminación forzada y el ciclo de vida del cliente nunca se propaga a pedidos.
- Un conflicto de teléfono normalizado durante la restauración deja ambos registros de cliente sin cambios.
- El manifest instalable comienza en `/admin`, vincula iconos/acceso directo válidos y no registra service worker.
- Las ejecuciones repetidas del scheduler de Instagram crean una fila por programación/instante debido; dos reclamos no pueden devolver la misma fila.
- Solo los switches activos de despliegue/operador, el día de programación y el asset generan trabajo. El medio firmado sirve únicamente el JPEG privado vinculado y vence.
- Las fallas explícitamente reintentables previas a la publicación respetan el límite de intentos. Los resultados ambiguos y el procesamiento obsoleto requieren conciliación manual y nunca vuelven automáticamente a `pending`.
- El archivado de Instagram conserva filas; la limpieza masiva excluye resultados `pending`, `processing` y de revisión manual. Reintentar/cancelar vuelve a validar la fila bloqueada y nunca encola un resultado ambiguo ni una fila con ID de medio de Meta conocido.
- Los días de Instagram usan ISO `1..7` (lunes a domingo). Las programaciones simultáneas guardan un snapshot del orden explícito y conservan la generación única `(schedule_id, scheduled_for)`.
- El estado de Featured Product se deriva en los límites exactos de inicio/fin a partir de instantes UTC interpretados para mostrar/ingresar en la zona horaria de negocio de Orders; la zona del navegador nunca decide la actividad.
- Las ventanas habilitadas de Featured Product usan `[starts_at, ends_at)`: se permiten rangos adyacentes, se rechazan superposiciones y conflictos abiertos, las filas deshabilitadas no bloquean y la violación de exclusión PostgreSQL `23P01` se traduce a un error de validación claro.
- La visibilidad pública de Featured Product también requiere un Product disponible y no eliminado dentro de una Category activa y no eliminada. Un catálogo no disponible/eliminado conserva el historial de Marketing, pero elimina el bloque público.
- Se comprueba que la respuesta pública de Featured Product tenga una forma segura exacta, identidad de Product basada en slug y ningún ID interno de Featured/Product/administrador, ruta de almacenamiento ni metadato privado.
- El preview administrativo y Home pública renderizan el mismo componente de presentación; agregar desde Home usa el carrito existente y se ve en `/cart` sin un store de carrito de Marketing.
- El resumen de Marketing en Dashboard cubre los estados activo, próximo, vacío y temporalmente activo pero no disponible públicamente sin reproducir reglas de Marketing.

## Contrato de verificación de Dashboard

- La pertenencia al calendario usa `delivery_date` en la zona horaria comercial configurada de Orders. Las pruebas deben hacer que `created_at` difiera de `delivery_date` y demostrar que nunca cambia el resultado.
- Hoy, la semana ISO de lunes a domingo y el mes calendario usan límites inclusivos de fechas comerciales. La evolución devuelve cada intervalo esperado en orden y completa con cero los intervalos sin datos.
- Las ventas incluyen `waiting`, `prepared` y `delivered`; el estado de pago no las modifica. Los ingresos cobrados usan esos mismos estados confirmados y además requieren `paid`.
- El recuento de pedidos del período incluye los estados pendientes de confirmación y confirmados. Los pendientes de confirmación se cuentan por separado para hoy y no contribuyen a ventas, ingresos cobrados, productos, unidades de producto ni evolución.
- Los pedidos cancelados y eliminados lógicamente no contribuyen a ningún agregado normal del Dashboard.
- Los pedidos urgentes permanecen limitados a hoy para cualquier período seleccionado e incluyen sólo pendientes de confirmación, en espera y preparados. Se ordenan por prioridad de estado, período de entrega, hora exacta con valores nulos al final e ID. La lista general de hoy se ordena por período, hora exacta con valores nulos al final, prioridad de estado e ID.
- Los snapshots directos de media docena y docena cuentan 6 y 12 unidades reales. Las cantidades de composición de docenas combinadas se distribuyen entre variedades sin volver a contar la línea padre. Renombrar o eliminar el Product actual no puede sustituir las etiquetas de snapshots históricos.
- Crudas/cocinadas usa unidades de producto de snapshots; envío/retiro usa cantidad de pedidos. Las pruebas deben mantener separados estos denominadores y cubrir distribuciones vacías de total cero sin `NaN` ni `undefined`.
- Una selección TP del Dashboard sólo puede hidratar un pedido administrativo activo y debe abrir/cerrar el panel compartido de Orders sin abandonar el período seleccionado del Dashboard.
- La cantidad de consultas del modelo de lectura permanece acotada cuando crece el volumen de fixtures; React recibe agregados completos en lugar de recalcular la pertenencia al negocio.
- Playwright debe cubrir el cambio de período mientras permanecen visibles las alertas de hoy, apertura/cierre del panel, **Ver todos los pedidos**, **Nuevo pedido**, prioridad de lectura móvil, un estado vacío real y las tres capturas requeridas.

Estos puntos son cobertura obligatoria, no una afirmación de que el worktree actual los haya superado. El informe de entrega debe indicar los comandos específicos y completos ejecutados, cantidad de resultados, omisiones y cualquier captura visual bloqueada.

## Datos de prueba

Las pruebas de persistencia usan `RefreshDatabase` y la conexión de prueba aislada configurada desde `.env.testing.example`. `TestingDatabaseGuard` rechaza `APP_ENV=testing` cuando el nombre efectivo de la base de datos, incluso mediante un override de `DB_URL`, no contiene un segmento independiente `test` o `testing`. La configuración global de Playwright carga datos deterministas de catálogo, administrador y paginación de clientes mediante `tests/Support/PlaywrightDatabaseSeeder.php` en ese entorno protegido; los fixtures del Dashboard también deben usar `OrderBusinessDate` y snapshots persistidos de ítems/composiciones para que sus valores de período sean deterministas. Nunca debe cargar datos en la conexión de desarrollo. No se debe usar `migrate:fresh` sobre la base de datos de desarrollo existente para demostrar migraciones limpias; se debe seguir [DEVELOPMENT.md](DEVELOPMENT.md#migration-safety).

Para una suite PHP aislada de POS-0, usar una instancia PostgreSQL privada con `APP_ENV=testing`, `APP_DEBUG=false`, `DB_URL=''`, `CACHE_STORE=array` y `SESSION_DRIVER=array`; el nombre de base debe ser explícitamente de testing (por ejemplo, `testing_pos0`). Playwright usa su propia base privada (por ejemplo, `testing_pos0_browser`) y `SESSION_DRIVER=database` porque la aplicación de navegador requiere sesiones persistidas. No reutilizar esa configuración de sesión del navegador para la suite PHP.

Los datos cargados para Playwright se tratan como una línea base inmutable: el administrador y el catálogo son datos de referencia compartidos, `Cliente paginado NN` es la cohorte con namespace para paginación de Customers y `TP-009001` a `TP-009007` son fixtures identificados del modelo de lectura del Dashboard. Las pruebas de colecciones deben filtrar su cohorte en la consulta antes de afirmar totales o paginación. Los escenarios que mutan datos crean identidades propias, incluidos el proyecto, worker y reintento de Playwright cuando pueda haber colisiones, y luego localizan el cliente o ID de Order exacto devuelto; no deben seleccionar una primera fila global ni depender de que otra prueba se haya ejecutado. `playwright.config.ts` y la configuración global rechazan cualquier nombre de base de datos sin un segmento independiente `test` o `testing` antes de que pueda ejecutarse `migrate:fresh`. Con `APP_ENV=testing`, el disco local también se aísla bajo `storage/framework/testing/disks/local`; el guard del fixture de Instagram rechaza cualquier configuración que intente escribir medios E2E en `storage/app/private`.

Los fixtures de Marketing para Playwright conservan una recomendación estable, activa y vendible para lecturas públicas/móviles, además de datos de programación sin superposición para los recorridos administrativos. Como los proyectos de escritorio y móvil comparten la base `testing_playwright`, las mutaciones sensibles a superposición no deben ejecutarse en simultáneo en ambos proyectos ni alterar el fixture actual estable. Los estados visuales vacíos/no disponibles que sólo varían la presentación pueden interceptar un modelo de lectura tipado de Inertia; las pruebas feature del backend siguen siendo responsables de demostrar la consulta real y el comportamiento de la base de datos.

Los escenarios mutables de Management crean billeteras y conceptos con namespace por proyecto, worker y reintento, y luego localizan exactamente esas identidades para registrar y verificar movimientos, compra, gasto, retiro, transferencia y reversión. Cada escenario de transferencia usa origen y destino propios; nunca depende de la primera billetera global, de una primera categoría sin tipo ni del orden de otra prueba. La misma semántica debe ejecutarse en desktop y mobile sin compartir el recurso mutable entre ambos.

Los tests de comprobantes usan `Storage::fake('local')` y fixtures sintéticos mínimos con contenido PDF o imagen real. No usan documentos comerciales ni datos personales. Playwright carga archivos namespaced por proyecto, worker y reintento, espera explícitamente la descarga autorizada y verifica que desktop y mobile no compartan la operación mutable ni dependan de una primera fila global.

La limpieza de clientes de desarrollo está separada intencionalmente de la configuración de pruebas. Su primera fase usó sólo SELECT y teléfonos enmascarados; tras la aprobación de registros exactos, los IDs de cliente `1`, `3`, `4`, `5`, `10`, `11`, `12`, `13`, `14` y `15` pasaron por el flujo administrativo de borrado lógico, mientras que los IDs `16` y `19` se conservaron. Las pruebas no se usaron como mecanismo de limpieza y cualquier limpieza futura requiere la misma aprobación explícita.

## Informe de entrega

Informar los comandos exactos ejecutados, sus resultados y cualquier control omitido o bloqueado por el entorno. Nunca describir como aprobado un control que no se ejecutó.

## POS-1: contexto, acceso e historia

- `tests/Feature/Orders/OrderContextTest.php`: dine-in inmediato sin Customer/PII/programación, dirección prohibida, shipping cero, precios unitarios POS-0, fuente/modo separados, requisitos públicos/admin, serialización sin queries adicionales, edición/contacto opcional, integración administrativa de pago existente, autorización de creación POS, CHECK PostgreSQL y preservación exacta de filas históricas por up/down.
- `tests/Feature/Identity/StaffAccessTest.php`: administradores existentes, perfiles/capacidades fijos, middleware backend `can:`, rechazo de rutas sensibles, privilegios no mass-assignable, CHECK y conservación/rollback seguro.
- `tests/e2e/order-context.spec.ts`: listado, detalle y edición admin desktop/mobile de un pedido inmediato sin cliente; no hay enlaces falsos, programación artificial ni errores de página. No crea UI POS.
- Strict TDD y las cifras observadas se registran en el task del slice. Todas las suites se ejecutan secuencialmente en `testing_pos1`, dentro del contenedor PostgreSQL privado de POS-1. No ejecutar suites concurrentes sobre esa DB ni usar development para `migrate:fresh`.
- La regresión incluye Orders, Products/POS-0, Auth/Identity y Management. Inventory no tiene suite/comportamiento de stock implementado en este baseline; POS-1 no cambia `delivered`. Los checks de PHP, TypeScript, lint, formatting y build son independientes de una aprobación de revisión nativa.

## POS-2 verification

`tests/Feature/Restaurant/FloorLayoutTest.php` covers admin HTTP/domain behavior,
exact coordinates, shapes/rotation/capacity, inactivity, identifiers, obstacle
schema, optimistic conflict, rollback and table identity preservation.
`FloorDatabaseTest.php` exercises PostgreSQL checks, FK/unique constraints and
final rotated-footprint triggers using direct SQL and SET CONSTRAINTS IMMEDIATE.
It also checks the internal active-only read query.

Strict backend TDD includes observed RED before Restaurant implementation and a
separate RED for numeric-string ID aliases. Run Restaurant plus Identity and
`tests/Feature/Orders/OrderContextTest.php` sequentially against a private DB.

`tests/e2e/restaurant-floor.spec.ts` proves six tables, two circular obstacles,
pointer movement/resizing without per-move writes, save/reload persistence,
mobile selection, read-only rendering, inactivity and two-tab conflicts.
Viewport matrix: 320/360/390/393/Pixel 7/1280/1920. Coordinate persistence and
area containment are semantic assertions, not pixel screenshot baselines.
Screenshots in storage/app/ are visual-review artifacts only.

POS-2 verification uses a dedicated PostgreSQL instance (loopback 55439), backend
DB `testing` and separate browser DB `testing_pos2_browser`, with one Playwright
worker. No development DB is touched. If native browser libraries are missing,
use the local verification container with the worktree and cached browsers
mounted; do not change application dependencies or system configuration.


## AOB-5 consumption reconstruction

Focused suites: `OrderConsumptionRecoveryTest`, `OrderConsumptionConstraintTest` and
`OrderConsumptionConcurrencyTest` under `tests/Feature/Inventory`. The live schedule
uses separate fresh PHP subprocesses, never inherited PDO/forked Laravel connections;
both contenders must be observed in PostgreSQL Lock wait before releasing the holder.
Scenarios include duplicate deliveries/listener retries, distinct Orders sharing weighted
layers, duplicate cancellations/reversal retries and committed-history append rejection.
SQL probes additionally verify economic locks (including no_inventory), missing delivery/
reversal evidence, immutable formula/mode and retired formula capture rejection.

Run Inventory AOB-1–5 plus Purchases/Production/Adjustments, POS-0 pricing and POS-1
context/access, then full backend sequentially on the private `aob_testing` DB.
The migration up/tests/empty rollback/up harness uses `aob5_migration_testing` and
compares restored AOB-4 validator definitions byte-for-byte. Populated rollback must
refuse before dropping history. No committed migration is edited.

`tests/e2e/order-consumption-recovery.spec.ts` uses the separate private
`aob5_playwright_testing` database, one worker, desktop Chromium and Pixel 7.
It follows canonical Products links, versions 55→60 g, distinguishes Actual/Retiradas,
creates an actual commercial Order, checks repeat delivery only removes 180 g,
repeat cancellation returns exactly 180 g and reversed redelivery is rejected.
Build assets must finish before HTTP/backend/browser tests; never overlap Vite writes
with requests. Global lint is compared with an actual pre-AOB-5 JSON baseline rather
than cleaning inherited errors. Ignore runtime logs/artifacts, not source failures.
