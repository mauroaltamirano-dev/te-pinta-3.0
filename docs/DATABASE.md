# Base de datos

PostgreSQL es la base de datos de la aplicación y el límite final de integridad. Las migraciones están versionadas; los datos de desarrollo existentes deben preservarse.

## Dinero e identificadores

- El dinero se almacena como valores exactos en pesos enteros (columnas decimales convertidas a enteros por los modelos). Los precios y totales comerciales son no negativos; los impactos del ledger de Management son firmados.
- Las claves primarias internas nunca son identificadores visibles para los clientes.
- `orders.public_id` obtiene de forma predeterminada un valor de la secuencia de PostgreSQL `order_public_id_seq`, compuesto por `TP-` más seis dígitos con relleno.
- La asignación de la secuencia es segura ante concurrencia. Los valores son estables y únicos, pero la reversión de transacciones y la actualización de la secuencia pueden crear espacios. Estos espacios son válidos y nunca deben “repararse” con `max(id) + 1`.

## Productos y familia de precio

`products.pricing_family` es un enum físico restringido a `empanada` o `unit`, con valor predeterminado `empanada`. La migración clasifica exclusivamente los registros legacy con las tres columnas de precio estructuralmente presentes como `empanada`; aborta si encuentra precios legacy incompletos. No infiere semántica desde texto. Las columnas de media docena y docena se mantienen no nulas para compatibilidad de esquema: en un producto `unit` almacenan el mismo valor que `unit_price` y son inertes para pricing y recursos públicos. Los catálogos legacy que debían ser unit requieren revisión y reclasificación administrativa explícita después de la migración.

## Customers

| Columna/regla      | Propósito                                                 |
| ------------------ | --------------------------------------------------------- |
| `id`               | Identidad interna estable                                 |
| `name`             | Obligatorio, indexado, admite duplicados                  |
| `phone`            | Valor de presentación opcional                            |
| `phone_normalized` | Valor opcional de solo dígitos para asociación y búsqueda |
| `address`          | Una dirección editable opcional                           |
| `notes`            | Notas administrativas privadas opcionales                 |
| `deleted_at`       | Borrado lógico                                            |

`customers_active_phone_normalized_unique` es un índice único parcial de PostgreSQL para los valores de teléfono no nulos donde `deleted_at IS NULL`. Esto hace determinista la asociación pública entre perfiles activos y permite reutilizar un teléfono de un perfil con borrado lógico. La validación de la aplicación refleja la restricción; la base de datos conserva la autoridad ante concurrencia.

## Orders y snapshots

`orders` almacena la referencia al cliente junto con snapshots de su nombre y teléfono; origen; estado operativo y de pago, con método opcional; condición cruda o cocinada; tipo, fecha, período y hora de entrega; snapshot de la dirección de entrega; notas del pedido; valores de subtotal, ahorro, envío, descuento y total; hash y fingerprint opcionales de idempotencia pública; y `edit_version` para concurrencia optimista. Los pedidos nuevos también conservan el subtotal de productos cocinados y el descuento por elegir productos crudos. Los campos heredados `items_base_subtotal` y `cooking_surcharge` permanecen únicamente para compatibilidad con snapshots históricos y nunca se recalculan.

| Tabla                     | Responsabilidad histórica                                                                                                                                                                                                                                  |
| ------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `order_items`             | Referencia al producto cuando corresponde, nombre vendido, modalidad comercial, cantidad de bloques y unidades, precio y subtotal de referencia cocinados, descuento por condición cruda, precio aplicado, precio normal de comparación, ahorro y subtotal |
| `order_item_compositions` | Referencia al producto, nombre vendido y cantidad de cada parte de una docena combinada                                                                                                                                                                    |
| `order_toppings`          | Referencia opcional al complemento, nombre vendido, cantidad, precio unitario aplicado y subtotal                                                                                                                                                          |

Las líneas de docena combinada no tienen un único `product_id`; las líneas normales requieren uno. Las claves foráneas de Product y catálogo restringen la eliminación, mientras que el borrado lógico mantiene utilizables las referencias históricas. Las filas de ítems, composiciones y toppings pertenecientes a un pedido solo se eliminan en cascada cuando se elimina físicamente la fila del pedido; la administración normal aplica borrado lógico a los pedidos.

Cambiar el nombre, precio o disponibilidad de un producto, la dirección de un cliente, el precio de un topping o el costo de entrega no modifica los pedidos existentes. El detalle, los recursos y el formateador leen snapshots en lugar del catálogo o la configuración actuales.

Una edición económica confirmada reemplaza los snapshots de ítems, composiciones, toppings y precios agregados únicamente del pedido seleccionado. El reemplazo y la actualización del pedido son atómicos. Una edición no económica deja intactas esas filas de precios y esos totales.

Los snapshots de descuento son `discount_type`, `discount_value` y el resultado aplicado por el backend, `discount_amount`. La base del descuento es `items_subtotal + toppings_subtotal`; se excluye el envío. El porcentaje admite de 0 a 100 con redondeo a medio hacia arriba en pesos enteros; los importes fijos son no negativos y ambos se limitan a la base del descuento.

`payment_method` admite nulo y está restringido por enum a efectivo, transferencia bancaria o tarjeta de débito. Las filas nuevas y actualizadas cumplen lo siguiente: `paid` requiere un método, `unpaid` requiere nulo y `not_required` requiere tanto un total cero como un método nulo. La comprobación de PostgreSQL es `NOT VALID` para no inventar métodos desconocidos en el historial pagado anterior a la migración, aunque sí se aplica a cada fila insertada o actualizada posteriormente.

`order_revisions` es la tabla de auditoría específica de Orders. Cada edición exitosa almacena la clave foránea del pedido, la clave foránea del administrador, el estado al persistir, los totales anterior y posterior, una lista JSON de los nombres de campos modificados junto con el indicador de cambio económico, un motivo opcional y acotado para editar un pedido cerrado, y el timestamp. Intencionalmente no duplica valores de teléfono, dirección ni notas. Las revisiones se eliminan en cascada con la eliminación física del pedido; la eliminación del administrador se restringe mientras tenga referencias.

Los snapshots de precios siguen una única regla: los valores cocinados son la base predeterminada y un descuento positivo se resta cuando el pedido elige productos crudos. Los campos históricos que expresaban `base_line_total + cooking_surcharge` o `items_base_subtotal + cooking_surcharge` se conservan literalmente como evidencia heredada, pero no definen los pedidos nuevos. Nunca se completan ni recalculan snapshots históricos con valores actuales de Products o Settings.

## Management: billeteras y ledger

`wallets` almacena nombre configurable, orden, `archived_at` e `is_order_payment_default`. No tiene una columna de saldo. Un índice único parcial permite como máximo una billetera predeterminada para cobros y un check impide que esa misma fila esté archivada. Una billetera archivada conserva su identidad e historia y las claves foráneas restrictivas impiden eliminarla físicamente mientras esté referenciada.

`financial_transactions` es la cabecera durable del hecho financiero: tipo, descripción opcional acotada, `effective_date`, administrador creador y `reversal_of_id` opcional. Los tipos actuales son `opening`, `adjustment_increase`, `adjustment_decrease`, `manual_income`, `manual_expense`, `management_outflow`, `transfer`, `distribution`, `order_payment` y `reversal`. Una restricción exige que sólo `reversal` tenga una referencia de origen y una clave única permite una única reversión por hecho original.

`ledger_entries` relaciona cada cabecera con una billetera y guarda un `amount` firmado. La representación física continúa siendo `decimal(14,2)` para seguir la convención existente, pero `ledger_entries_amount_nonzero_integer_pesos` exige valor distinto de cero e igualdad con `trunc(amount)`. Por lo tanto, sólo admite pesos enteros exactos, positivos o negativos. La clave única `(financial_transaction_id, wallet_id)` evita duplicar el impacto de una billetera dentro del mismo hecho, sin impedir que una cabecera futura tenga entries de varias billeteras.

Un constraint trigger diferible comprueba al confirmar que toda cabecera tenga al menos una entry. Los triggers `financial_transactions_immutable` y `ledger_entries_immutable` rechazan `UPDATE` y `DELETE`: las correcciones se expresan mediante nuevas transacciones. Las claves foráneas hacia billetera, administrador, cabecera y origen de reversión usan `RESTRICT` para conservar evidencia.

El saldo se deriva con `SUM(ledger_entries.amount)`. Los saldos negativos son válidos. El saldo total operativo incluye sólo billeteras activas, aunque las archivadas permanecen consultables en históricos. Las escrituras de cabecera más entries se confirman dentro de una transacción PostgreSQL; registrar, archivar/restaurar y revertir aplican locks sólo cuando el estado previo de una fila decide la operación.

Los índices responden a las consultas actuales: billeteras activas ordenadas, historial por fecha/ID, filtro tipo/período y entradas por billetera/transacción. No existe todavía una proyección o cache de saldo.

### Egresos semánticos

`management_outflow_categories` es el catálogo persistido para compras, gastos y retiros personales. Cada fila tiene un tipo (`purchase`, `expense` o `personal_withdrawal`), código estable, nombre, `is_active` y orden. La unicidad `(type, code)` permite repetir una etiqueta conceptual entre tipos sin perder identidad. El tipo y el código son identidad inmutable mediante trigger; nombre, estado y orden permanecen editables para una administración futura. Desactivar una categoría impide selecciones nuevas desde la aplicación, pero no modifica egresos históricos; las referencias usan `RESTRICT`.

`management_outflows` explica el origen administrativo de una salida: tipo, categoría, billetera, descripción, contraparte opcional, importe positivo, `effective_date`, administrador y `financial_transaction_id`. Esta última clave es única y restrictiva: cada egreso se vincula con exactamente una cabecera financiera de tipo `management_outflow`.

El importe administrativo es `decimal(14,2)`, pero el constraint exige que sea positivo e igual a `trunc(amount)`. Un constraint trigger diferible valida al confirmar que la cabecera tenga el mismo administrador y fecha, y exactamente una entry en la misma billetera por `-amount`. Otro trigger diferible impide confirmar una cabecera `management_outflow` sin origen administrativo. El trigger de inmutabilidad rechaza `UPDATE` y `DELETE` sobre el egreso confirmado.

`management_transfers` explica una redistribución interna: billetera de origen, billetera de destino, importe positivo, `effective_date`, descripción opcional, administrador y `financial_transaction_id`. Las claves foráneas son restrictivas, origen y destino deben ser distintos y la FK financiera única establece la relación 1:1 con una cabecera de tipo `transfer`.

El importe de transferencia también es `decimal(14,2)` restringido a pesos enteros positivos. Un constraint trigger diferible exige coincidencia de administrador y fecha y exactamente dos entries en la misma cabecera: `-amount` en origen y `+amount` en destino. También comprueba suma cero. Otro trigger diferible impide confirmar una cabecera `transfer` sin su `ManagementTransfer`, y el trigger de inmutabilidad rechaza `UPDATE` o `DELETE` sobre la transferencia confirmada.

No se agregan índices de consulta directa por origen/destino: el historial filtra participación de billetera mediante el índice existente de `ledger_entries(wallet_id, financial_transaction_id)`. La FK financiera única cubre el vínculo semántico; agregar otros índices queda sujeto a consultas reales futuras.

`management_outflows` indexa las consultas reales por fecha/ID y por tipo, categoría o billetera dentro de un período. Las categorías se indexan por tipo, estado activo, orden e ID para alimentar el formulario. La migración carga el catálogo inicial; este slice no expone CRUD de categorías.

### Distribuciones versionadas

`management_distribution_rules` almacena la identidad lógica, nombre y estado activo. Un índice único parcial permite una sola regla activa globalmente; los porcentajes no viven en la regla. `management_distribution_rule_versions` numera las versiones dentro de cada regla y registra su creador; cada versión es inmutable. `management_distribution_rule_allocations` relaciona la versión con billeteras destino mediante `percentage_bps` y `sort_order`.

Los basis points son enteros: `10.000 = 100 %`. Cada allocation tiene porcentaje positivo, una billetera aparece una sola vez por versión y `sort_order` es no negativo; los empates se resuelven por `wallet_id`. Un constraint trigger diferible exige suma exacta de `10.000` bps antes de confirmar. Las versiones y allocations rechazan `UPDATE` y `DELETE`; crear una versión nueva es la única forma de cambiar la composición. El estado activo pertenece a la regla lógica y sólo controla usos nuevos.

`management_distributions` conserva la billetera origen, la versión aplicada, importe positivo entero, fecha efectiva, administrador, descripción opcional y una FK financiera única hacia una cabecera `distribution`. `management_order_payment_id` es anulable para mantener el flujo manual y único para representar cero o una distribución automática por pago. `management_distribution_allocations` es el snapshot relacional: conserva para cada aplicación la billetera destino, su nombre al aplicar, `percentage_bps`, `sort_order` e importe positivo resultante. No se usa JSON como reemplazo de estas relaciones.

El cálculo ordena por `(sort_order, wallet_id)`, aplica división entera y suma todo el remainder a la última allocation. Una constraint impide snapshots o entries de valor cero. Los triggers diferibles comprueban que los snapshots coincidan con la versión, que exista una entry `-amount` en el origen, una entry positiva por destino y que `SUM(snapshot amount) = management_distributions.amount`; la suma de todas las entries debe ser cero. También impiden confirmar una cabecera `distribution` sin su origen semántico.

Todas estas filas históricas usan FKs restrictivas. Una billetera archivada no puede incorporarse a una versión nueva ni recibir una distribución nueva, pero sus referencias permanecen y `ReverseFinancialTransaction` puede crear las entries negativas exactas después del archivado. La reversión no vuelve a ejecutar porcentajes.

`management_distributions.source_wallet_id` identifica el origen obligatorio. Una distribución contiene una entry `-amount` en esa billetera y una positiva por snapshot destino; los triggers exigen que los créditos sumen el importe y que la operación completa sume cero. La migración conservativa se aborta ante filas legacy sin origen verificable en lugar de inventarlo.

### Cobros de Orders

`management_order_payments` conserva el origen financiero estructurado de un cobro. Sus columnas son `order_id`, `destination_wallet_id`, `financial_transaction_id`, importe positivo entero, `effective_date`, `created_by` y timestamps. Las FKs hacia `orders`, `wallets`, `financial_transactions` y `users` usan `RESTRICT` para preservar evidencia.

`order_id` es único: un Order puede originar como máximo un cobro, aun si `OrderPaymentConfirmed` se entrega más de una vez o dos procesos compiten. `financial_transaction_id` también es único y fija la relación 1:1 con una cabecera `order_payment`. Un constraint trigger diferible exige coincidencia de importe, fecha y administrador, además de exactamente una entry positiva por `amount` en `destination_wallet_id`; otro impide confirmar una cabecera `order_payment` sin su origen semántico. La fila confirmada rechaza `UPDATE` y `DELETE`.

Cuando una distribución referencia un pago, los triggers exigen que `source_wallet_id = destination_wallet_id` y que importe y fecha efectiva coincidan. `ManagementDistribution.created_by` conserva al administrador que aplicó o reintentó el reparto y puede diferir del creador del pago. La unicidad de `management_order_payment_id`, combinada con el bloqueo de la fila del pago, cierra la carrera entre reintentos. Revertir la cabecera `order_payment` se rechaza mientras la distribución vinculada no tenga su propia reversión.

La fecha efectiva es la fecha comercial recibida al confirmar `paid`; su distribución usa la misma fecha. La migración no completa Orders ya pagados ni intenta asociar distribuciones legacy: esos datos no demuestran cuándo ocurrió el hecho financiero ni cuál era su billetera, responsable o regla. La nueva FK es anulable y se agrega sin backfill. Cancelar o volver a `unpaid` no modifica estas filas automáticamente.

### Auditoría financiera derivada

La reconciliación no agrega tablas, saldos materializados ni estados de finding. El read model consulta `financial_transactions`, `ledger_entries` y las relaciones semánticas existentes para derivar balances, configuración y diferencias en el momento de lectura. Por lo tanto, este slice no requiere migración ni backfill.

Los filtros temporales usan `financial_transactions.effective_date`; los filtros por billetera usan la participación indexada en `ledger_entries(wallet_id, financial_transaction_id)`. Las relaciones 1:1 ya poseen índices únicos por `financial_transaction_id`, `order_id` o `management_order_payment_id`, y las consultas de cabeceras reutilizan los índices de tipo, fecha e ID. Se agregan índices sólo cuando un plan de consulta real demuestre una necesidad; no se duplican índices cubiertos por claves únicas o foráneas.

El filtro `type` opera sobre el resultado derivado: compara el valor solicitado tanto con el código del finding como con el tipo de operación o configuración que lo originó. No agrega una columna nueva ni modifica el enum persistido de `financial_transactions`.

Los findings no sustituyen constraints. PostgreSQL continúa siendo la protección de escritura para tipos conocidos, estructuras semánticas, inmutabilidad y conservación interna. La auditoría ofrece diagnóstico defensivo y visibilidad operativa, incluso si una inconsistencia sólo podría aparecer por datos importados, una restricción deshabilitada o una evolución futura incompleta.

### Comprobantes privados de Management

`management_attachments` almacena metadata documental, no los bytes del archivo. Cada fila referencia una `financial_transactions` mediante `financial_transaction_id`, de modo que egresos, transferencias, distribuciones, cobros y reversiones comparten una sola relación común sin introducir una referencia polimórfica. La FK usa `RESTRICT`: eliminar una cabecera financiera no puede borrar evidencia documental, aunque las cabeceras ya son inmutables por diseño.

La tabla conserva `disk`, `path`, `original_name`, `mime_type`, `size`, `checksum_sha256`, `uploaded_by`, timestamps y el tombstone opcional `deleted_at`, `deleted_by`, `deletion_reason`. `path` es único y generado por la aplicación; `original_name` nunca se usa como ubicación. El checksum es SHA-256 hexadecimal, sirve para verificación de integridad y no implica deduplicación. Las FKs de quien carga o elimina también son restrictivas para preservar la procedencia administrativa.

El contenido vive en el disco privado `local` bajo `management/attachments/{year}/{month}/{uuid}` sin extensión. Una fila confirmada no permite reemplazar bytes ni actualizar metadata de identidad. La eliminación visible exige un motivo de 3 a 240 caracteres y sólo puede completar el tombstone una vez; conserva fila y archivo físico. Un trigger bloquea `DELETE` y modificaciones posteriores de la metadata protegida. Las cargas que fallan antes de crear la fila no son evidencia confirmada y su archivo recién escrito se elimina mediante cleanup explícito.

Las consultas del overview no cargan attachments. Cuando el historial necesita indicar presencia documental usa un conteo agregado por `financial_transaction_id`; la metadata completa se obtiene únicamente al abrir un detalle. El índice por `(financial_transaction_id, deleted_at)` alimenta ambas lecturas sin incorporar bytes a PostgreSQL.

## Featured Products de Marketing

`marketing_featured_products` conserva cada recomendación pasada, actual, futura y deshabilitada. Referencia Products sin copiar campos ni precios del catálogo.

| Columna/regla | Propósito                                                                            |
| ------------- | ------------------------------------------------------------------------------------ |
| `id`          | Identidad interna de Marketing; nunca se expone públicamente                         |
| `product_id`  | Relación obligatoria con Product; el borrado lógico de Product conserva el historial |
| `headline`    | Texto plano opcional, con un máximo de 80 caracteres                                 |
| `short_copy`  | Texto plano opcional, con un máximo de 240 caracteres                                |
| `starts_at`   | Instante UTC inclusivo obligatorio                                                   |
| `ends_at`     | Instante UTC exclusivo opcional; nulo significa que no tiene fin                     |
| `is_enabled`  | Controla si el intervalo participa en la programación y resolución pública           |
| timestamps    | Historial administrativo mediante las fechas de creación y actualización             |

La ventana lógica es el rango semiabierto `[starts_at, ends_at)`. Una restricción check exige `ends_at > starts_at` cuando existe un final. Una restricción de exclusión GiST parcial de PostgreSQL rechaza la superposición entre rangos temporales habilitados y utiliza infinito positivo para un final nulo. La adyacencia es válida: una recomendación que termina a las `18:00` no se superpone con otra que comienza a las `18:00`.

La restricción de exclusión es la garantía final ante concurrencia. Las acciones de la aplicación también realizan una comprobación previa dentro de transacciones para ofrecer un mensaje de validación claro y traducir la infracción de exclusión de PostgreSQL `23P01`; la base de datos todavía protege las inserciones simultáneas cuando no había una fila disponible para bloquear.

Los índices permiten consultar el historial de Product, listar por hora de inicio habilitada y buscar la recomendación actual. La estructura GiST ya resuelve las comprobaciones de superposición de intervalos; no se agregan índices separados de baja selectividad sin una consulta que demuestre su necesidad.

La UI de v1 no elimina físicamente ningún registro de Marketing. La falta de disponibilidad o el borrado lógico de Product no modifica la programación; solo cambia su aptitud pública actual.

## Automatización de Instagram

`instagram_automation_settings` almacena el interruptor operativo singleton. `instagram_story_assets` almacena metadatos privados de JPEG normalizados y una ruta interna generada; `instagram_story_schedules` almacena la hora local, los días ISO de la semana, un asset opcional, el estado activo y un ordinal explícito `sort_order` de 1 a 999; `instagram_story_publications` funciona tanto como cola como historial operativo inmutable. El campo anulable `archived_at` oculta las filas finalizadas sin eliminar los datos de auditoría; la posibilidad de reintento y los timestamps de resultado continúan bajo responsabilidad del backend.

Las publicaciones programadas permanecen únicas por `(schedule_id, scheduled_for)`. La generación copia `sort_order` en el snapshot `queue_order`; las reservas utilizan `FOR UPDATE SKIP LOCKED` de PostgreSQL y ordenan por instante de vencimiento, orden de cola e ID antes de mover una fila al estado de procesamiento. El estado de publicación está restringido en la base de datos a pendiente, en procesamiento, publicada, fallida o cancelada. Los assets no pueden eliminarse físicamente mientras una programación o el historial los referencien, y un asset con programaciones activas no puede desactivarse.

`scheduled_for` y los timestamps de procesamiento y resultado son instantes UTC. La hora programada y la pertenencia al día ISO de la semana se interpretan en `INSTAGRAM_AUTOMATION_TIMEZONE` antes de persistir el instante de vencimiento.

## Restricciones de la base de datos

Las comprobaciones de PostgreSQL garantizan:

- estados operativos y de pago, origen, condición, tipo y período de entrega, y modalidad de precios válidos;
- cantidades positivas acotadas y valores monetarios no negativos;
- `total = items_subtotal + toppings_subtotal + shipping_fee_snapshot - discount_amount`;
- que el tipo, el valor y el importe aplicado del descuento estén ausentes en conjunto o coincidan con el cálculo porcentual o fijo sobre los ítems más los toppings, con el resultado limitado a esa base;
- que las filas pagadas tengan un método de pago permitido, las filas no pagadas no tengan ninguno y `not_required` se limite a las filas con total cero, aplicado a filas nuevas o actualizadas sin inventar historial heredado;
- que el retiro tenga costo de envío cero y la entrega tenga un snapshot de dirección no vacío;
- la forma del ítem de docena combinada y `savings = regular_price_total - line_total`;
- exactamente un valor tipado por fila de `order_settings`;
- que el hash y el fingerprint de idempotencia sean ambos nulos o ambos estén presentes;
- ventanas semiabiertas válidas de Marketing y ausencia de superposición entre programaciones habilitadas de Featured Product.
- tipos y forma de reversión válidos en Management, una sola reversión por origen, entries no cero en pesos enteros, al menos una entry por cabecera e historia financiera no actualizable ni eliminable;
- tipos/categorías de egreso válidos, importes administrativos positivos en pesos enteros, relación 1:1 y consistencia exacta entre `management_outflows`, su cabecera y su entry negativa.
- transferencias con importe positivo entero, billeteras distintas, relación financiera 1:1, exactamente dos entries opuestas coincidentes y suma cero.
- reglas de distribución versionadas, con una sola regla activa global, allocations positivas, billeteras únicas, orden estable y suma exacta de `10.000` bps;
- distribuciones con importe positivo entero, origen obligatorio distinto de destinos, relación financiera 1:1, un débito, N créditos coincidentes y suma completa cero;
- a lo sumo una distribución por `management_order_payment`, con origen, importe, fecha y administrador coincidentes;
- una sola billetera predeterminada para cobros, que no puede estar archivada;
- un `management_order_payment` por Order, relación financiera 1:1, importe positivo y una entry positiva coincidente;
- inmutabilidad de versiones, allocations, distribuciones y snapshots, preservando una única reversión compensatoria por aplicación y exigiendo reparto-antes-de-pago para las reversiones vinculadas;
- attachments con cabecera financiera existente, path generado único, tamaño positivo y acotado, checksum SHA-256 válido y procedencia administrativa preservada;
- identidad documental inmutable después de la carga y tombstone consistente —usuario, fecha y motivo se registran juntos— sin eliminar por cascada el archivo o su metadata.

El bloqueo de filas de la aplicación junto con `orders.edit_version` proporciona concurrencia optimista. Cada edición completa y cada mutación independiente de estado o pago incrementan la versión; los envíos de ediciones completas obsoletas fallan antes de modificar los snapshots.

El índice único `public_checkout_idempotency_hash` resuelve los envíos duplicados simultáneos.

## Eliminación y restauración

Orders y Customers conservan `deleted_at` y se eliminan o restauran mediante el borrado lógico de Eloquent. No existe una ruta administrativa de eliminación forzada. La relación `orders.customer_id` no se elimina en cascada al eliminar un cliente, y la restauración de cualquiera de los registros opera sobre la misma fila: no se vuelven a crear el ID del pedido, los snapshots, los ítems, las revisiones ni las relaciones.

El índice de teléfonos de clientes activos excluye intencionalmente los clientes eliminados. Por lo tanto, `RestoreCustomer` vuelve a comprobar si otra fila activa posee ahora el teléfono normalizado y rechaza atómicamente la restauración en caso de conflicto, en lugar de mutar o fusionar datos.

Los comprobantes de Management usan un ciclo de vida distinto al borrado recuperable de Orders y Customers. La acción administrativa crea un tombstone auditado y deja de exponer el archivo, pero no lo elimina físicamente ni ofrece restauración en este slice. No existe purga automática, expiración ni política de lifecycle; cualquier eliminación física futura requerirá una política de retención explícita y una decisión separada.

## Almacenamiento temporal y fechas comerciales

Los timestamps de la aplicación y la persistencia en PostgreSQL permanecen en UTC. `delivery_date` es una fecha calendario, no un instante; la validación de “hoy” proviene de `OrderBusinessDate` mediante `ORDERS_TIMEZONE` (`America/Argentina/Buenos_Aires` de forma predeterminada). El checkout público recibe esa fecha autoritativa desde `/checkout/options` en lugar de derivarla del UTC del navegador.

## Tablas de configuración

`order_settings` utiliza el enum cerrado `OrderSettingKey` en lugar de claves arbitrarias en runtime. Cada fila tiene exactamente un valor entero o string. Los valores iniciales son 17500 para la docena combinada cocinada como base, 16000 para la alternativa cruda de menor precio, costo de entrega cero y un teléfono de WhatsApp vacío.

`order_addons` es el catálogo pequeño y configurable de toppings. Tiene un slug público estable, nombre, precio entero, disponibilidad, orden manual y borrado lógico. Las filas iniciales son Lactonesa (500) y Yasgua (200).

Products conserva `unit_price`, `half_dozen_price` y `dozen_price` como precios base de productos cocinados. `raw_price_discounts` almacena los tres descuentos configurables y positivos que se restan únicamente para la alternativa cruda.

`products.description` es la descripción breve. `long_description` y `public_ingredients` son texto editorial opcional para la ficha pública, administrado en Products; no se derivan de recetas, consumo ni stock de Inventory. Los campos nuevos son nullable para conservar los productos existentes sin migración de contenido.

`products.slug` es una URL pública única incluso frente a productos con borrado lógico. Al crear puede omitirse y se genera desde el nombre; una edición del nombre no cambia la URL. Administración puede cambiar el slug explícitamente, con la advertencia de que los enlaces anteriores dejan de resolver. Las etiquetas son filas administrables de `tags`, asociadas por `product_tag`; una etiqueta asociada no se elimina hasta quitarla de sus productos. `ProductCatalogSeeder` sólo carga variedades iniciales cuando no existe ningún Product, incluido el catálogo con borrado lógico: nunca repuebla un catálogo ya administrado después de cambios de nombre o slug.

## Flujo de migraciones

```bash
./vendor/bin/sail artisan migrate
```

Nunca ejecute `migrate:fresh` sobre la base de datos de desarrollo actual. Pruebe las migraciones desde cero únicamente en la base de datos de pruebas aislada.

## Lista de verificación de migraciones

- [ ] El comportamiento de las claves foráneas preserva el historial requerido.
- [ ] Los requisitos de búsqueda y unicidad tienen índices.
- [ ] El dinero es exacto y se calcula en el servidor.
- [ ] Las escrituras de varios registros son transaccionales.
- [ ] Las comprobaciones de PostgreSQL reflejan invariantes importantes del dominio.
- [ ] La actualización preserva los administradores, productos, clientes y pedidos existentes.
- [ ] El comportamiento de reversión es seguro en el entorno previsto.

## POS-1: cambios aditivos y conservación histórica

- `2026_10_05_000000_prepare_order_context` permite null en `orders.customer_id`, `customer_name_snapshot`, `delivery_date` y `delivery_period`, sin modificar ninguna fila. La FK real y su `RESTRICT` permanecen. Amplía los CHECK de source/fulfillment y agrega `orders_context_valid`.
- `orders_context_valid` exige source `pos` y programación nula para dine-in inmediato; fuera de dine-in conserva Customer, nombre, fecha y período no nulos. `orders_delivery_details_valid` exige shipping cero/dirección nula en salón, mantiene pickup cero y delivery con dirección no vacía. Los CHECK de precios, pagos e idempotencia no cambian.
- `2026_10_05_000100_add_fixed_staff_profiles` agrega `users.staff_profile` nullable y `users_staff_profile_valid` (`waiter`/`cashier` o null). No asigna perfiles ni modifica `is_admin`; todos los administradores actuales conservan acceso.
- La aplicación aplica los requisitos de PII/contacto y prohíbe fuente inyectada; PostgreSQL protege sólo combinaciones estructurales, no replica el formulario completo.
- Ambas migraciones rechazan un rollback que perdería contextos POS o perfiles ya utilizados. No sustituyen datos por clientes ficticios ni convierten historia para permitir rollback. Antes de uso, up/down restaura el esquema anterior sin cambiar las filas. Ejecutar sólo migraciones normales en development; las pruebas from-scratch usan exclusivamente la DB privada.

## POS-2: dining areas and physical tables

Incremental migration `2026_10_05_230000_create_restaurant_floor_configuration.php`
adds only `dining_areas` and `dining_tables`. IDs follow the existing integer
convention; the area FK uses RESTRICT. No default area/table data is seeded.

`dining_areas`: name, active flag, logical integer width/height (100..10000),
non-negative `layout_version`, `layout_schema_version=1`, obstacles JSONB and
timestamps. `layout_version` is optimistic concurrency, not historical versioning
or event sourcing. `layout_schema_version` identifies the obstacle schema only.

`dining_tables`: area ID, code, optional name, rectangle/circle shape,
`position_x/position_y/width/height NUMERIC(5,4)`, rotation, nullable capacity,
active flag and timestamps. Canonical four-decimal coordinates are persistence
authority, never absolute pixels. Capacity is 1..100 or null and has no reservation,
pricing or ordering semantics. Rotation is one of 0/45/90/135/180/225/270/315.

Checks enforce shape, positive dimensions, normalized coordinates and sums,
rotation/capacity, canonical non-empty codes, area dimensions/version/schema and
obstacle schema/bounds. A deferred unique constraint protects area/code while
allowing atomic code swaps. Final-state constraint triggers verify rotated table
bounds against area dimensions, including area resizing. Geometry uses exact
NUMERIC/scaled-integer arithmetic and conservative 7072/10000 diagonal
coefficients; circles allow one logical unit of quantization tolerance.

Obstacles are bounded arrays (max 100) of type, x/y/width/height decimal strings
and optional label, with no unknown fields or domain relationships. Tables are
limited to 200 per aggregate by application validation. Overlap is allowed.

One transaction locks the area, checks expected_version, updates all configuration
and increments layout_version once. A stale save returns 409 before mutation.
There are no layout-history tables or operational free/occupied columns.

## Inventory foundation (AOB-1)

Three new independent migrations create the seven physical Inventory tables and system units (`2026_09_02_000000`), add nullable classification and three explicit capability flags (`2026_09_30_000000`), and add optional count-item reference mass (`2026_09_30_010000`). Existing POS migrations are unchanged. Quantities use NUMERIC(20,6), factors NUMERIC(20,9), and money integer pesos. PostgreSQL checks, immutable-history triggers and a deferred transaction-requires-movement trigger enforce the contract described in [INVENTORY.md](INVENTORY.md). Cross-domain recipe/consumption capability protections are deferred until their owning tables exist.

## Current costing configuration (AOB-6)

Incremental `2026_10_13_000000_create_current_product_costing.php` adds four
configuration tables without changing previous migrations or historical facts:
`inventory_costing_policies`, `inventory_costing_policy_presentations`,
`inventory_costing_policy_packaging_components`, `inventory_product_costing_policies`.
Names are trimmed/nonempty/case-insensitively unique; overhead is 0–10000 bp.
A policy has at most one rule per commercial quantity (1–120). A Product has at
most one assignment; FK restrictions preserve valid policy, Product, InventoryItem
and unit references. Packaging quantities are positive finite NUMERIC(20,6).
A PostgreSQL trigger checks active stock-tracked packaging/consumable eligibility,
unit family, exact base conversion and item precision. Deferred graph constraints
require either explicit `none` with no components or `components` with nonempty
unique item rows. Rule writes serialize on their parent presentation; configuration
and assignment actions are atomic. Existing current valuation remains the only
source of packaging price; no duplicated economic value is stored in policy.
Populated migration rollback deliberately refuses before deleting policies or
assignments; empty down/up is supported. See [INVENTORY.md](INVENTORY.md#current-product-costing-aob-6).
