# Módulos del backend

Cada módulo es dueño de una capacidad de negocio y sólo de las capas que necesita su comportamiento implementado.

## Responsabilidades actuales

| Módulo                   | Es responsable de                                                                                                                   | No es responsable de                                            |
| ------------------------ | ----------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------- |
| `Identity`               | Identidad, sesiones y límite de administrador                                                                                       | Perfiles de clientes o estado de pedidos                        |
| `Products`               | Categorías, productos, tags, disponibilidad, familia de precio explícita, precios base cocinados, descuentos crudos y catálogo público | Agrupación del carrito o líneas históricas de venta             |
| `Customers`              | CRUD de perfiles, teléfono/dirección/notas privadas opcionales, normalización de teléfono, búsqueda paginada, agregados e historial | Verificación pública de identidad o detalle de pedidos          |
| `Orders`                 | Pricing, creación POS/pública, edición segura, snapshots/revisiones, entrega, estados y confirmación operativa de pagos             | Escribir el ledger o elegir cómo se administra el dinero        |
| `Settings`               | Edición administrativa de configuración de pedidos, descuentos de precios crudos y adicionales existentes                           | Cálculos de pricing                                             |
| `PublicSite`             | Rutas públicas y entrada al menú/carrito                                                                                            | Lógica autoritativa de checkout                                 |
| `Dashboard`              | Períodos operativos de sólo lectura, KPIs, colas de hoy, agregados de snapshots y evolución                                         | Comandos de pedidos, pricing, Analytics, Marketing o Inventory  |
| `Management`             | Billeteras, ledger inmutable, movimientos, egresos, transferencias, distribuciones y cobros idempotentes de Orders                  | Ciclo de vida de Orders, stock o Analytics                      |
| `Marketing`              | Historial, programación, elegibilidad/view model público, preview administrativo y resumen de Dashboard de Featured Product         | Catálogo/precios, descuentos, Orders o publicación en Instagram |
| `Instagram`              | Assets de Stories, programaciones, historial pendiente/publicado, API n8n, medios firmados y recuperación conservadora              | Credenciales Meta, requests Graph, diseño, Feed o Reels         |
| `Analytics`, `Inventory` | Capacidades no implementadas                                                                                                        | Analítica funcional o comportamiento de stock                   |

## Customers

```text
Customers/
  Actions/       create, update, soft delete, restore and structured events
  Data/          validated customer command data
  Http/          admin controller, requests, explicit resource
  Models/        Customer and Orders relationship
  Policies/      admin authorization
  Support/       PhoneNormalizer
  routes.php
```

Los nombres no son únicos. El teléfono es opcional en registros administrativos. Si existe, se normaliza centralmente y debe ser único entre clientes activos. Las notas privadas sólo aparecen en el resource administrativo autenticado; nunca se copian a pedidos ni responses públicas.

El historial usa pedidos persistidos. El gasto histórico efectivo excluye pedidos eliminados lógicamente mediante Eloquent y los `cancelled` de forma explícita; listado y perfil reutilizan una única definición. El índice paginado carga `orders_count`, gasto efectivo y `latestOrder` como datos agregados/eager del read model, no con una consulta por fila. Su contrato de orden sólo mapea `name`, `orders_count` y `historical_spend` a expresiones conocidas, acepta `asc` o `desc` y agrega siempre `id` como desempate estable; valores crudos del request nunca se convierten en columnas SQL. El índice `orders_customer_history_index` se ordena por `customer_id`, `deleted_at`, `delivery_date` e `id` para consultas activas por cliente.

El perfil devuelve pedidos activos de más reciente a más antiguo, con entrega, estado, pago y total. Si el parámetro validado `order` identifica un pedido activo del cliente mostrado, el controller delega la hidratación completa en Orders mediante `AdminOrderResource` y `OrderSummaryFormatter`. El frontend también importa `OrderDetailSheet`, propiedad de Orders, cuya única responsabilidad es componer el sheet responsive alrededor de `OrderDetailContent`. Customers no duplica acciones, autorización, copia de ticket ni render completo del detalle.

La eliminación es recuperable: el módulo expone una papelera sólo para administradores y `RestoreCustomer`. Nunca elimina físicamente ni propaga borrados a Orders. Como la unicidad del teléfono activo ignora filas eliminadas, la restauración comprueba si existe un nuevo dueño activo del mismo `phone_normalized`; ante conflicto rechaza la restauración sin cambiar ni fusionar clientes.

La limpieza de datos de desarrollo requiere aprobación y queda fuera de la entrega normal. Una auditoría enmascarada de sólo lectura precedió la aprobación explícita para enviar los IDs `1`, `3`, `4`, `5`, `10`, `11`, `12`, `13`, `14` y `15` por la acción existente de soft delete; se preservaron explícitamente `16` y `19`. Toda limpieza futura requiere aprobación por registro exacto y nunca debe borrar físicamente según un nombre supuesto.

## Orders

```text
Orders/
  Actions/       create/update, quote persistence, edit hydration, public selection resolution, status/payment updates
  Data/          immutable validated checkout input
  Enums/         states, source, condition, delivery, pricing, setting keys
  Events/        confirmed operational payment contract for Management
  Http/          admin/public controllers, requests, resource
  Models/        order, item/composition, topping, addon, setting, redacted revision
  Policies/      admin authorization
  Pricing/       one deterministic backend pricing engine and DTOs
  Support/       summary formatter and WhatsApp link builder
  routes.php
```

`Products` valida la familia `empanada` o `unit` al límite administrativo. `empanada` conserva sus tres tiers y descuentos crudos; `unit` sólo expone y cotiza su precio unitario. Orders recibe ese contrato mediante `ProductPriceProvider`, sin clasificar por categorías o nombres.

`CreateOrder` es el límite de aplicación compartido entre POS y checkout público. En una transacción resuelve/crea el cliente, calcula la cotización, resuelve el pago, crea el pedido, persiste snapshots de ítems/composiciones/adicionales y guarda totales. La fuente pública fija `pending_confirmation`; la administrativa inicia en `waiting`. `ResolveOrderPayment` normaliza todo total cero calculado por backend como `not_required` sin método.

`UpdateOrder` es el límite de corrección administrativa. Crear y editar comparten `OrderCheckoutData`, `ResolveOrderCustomer`, `OrderPricingEngine` y `PersistOrderQuote`; la acción de actualización no contiene una segunda implementación de pricing. `BuildAdminOrderEditData` reconstruye cantidades reales desde ítems normales y composiciones de docenas combinadas, y arma la cotización original persistida. `BuildAdminOrderFormOptions` incluye opciones actualmente vendibles y selecciones históricas no disponibles necesarias para mostrar el draft.

Toda actualización bloquea el pedido, compara `expected_version` con `orders.edit_version` y confirma pedido, reemplazo eventual de snapshots y una fila `order_revisions` en una transacción. Las actualizaciones independientes de estado/pago también incrementan la versión para impedir que una pestaña vieja las sobrescriba.

Los pedidos entregados y cancelados requieren un motivo breve. El permiso se guarda en la sesión administrativa, ligado al pedido y su versión actual, y se consume tras una actualización exitosa; no cambia el estado por sí mismo.

Los requests públicos usan slugs de producto/adicional. `ResolvePublicCheckoutSelections` los transforma en IDs internos sólo después de validar vendibilidad. Luego el motor invoca `ProductPriceProvider`; ningún caller calcula totales autoritativos.

Crear/editar/Quick Order administrativo puede aplicar descuento porcentual o fijo y elegir método de pago. El cálculo permanece en `OrderPricingEngine`; pagado exige un valor de `PaymentMethod`, no pagado limpia el método y total cero pasa a `not_required`. El checkout público desactiva ambas funciones. `OrderBusinessDate` entrega el día calendario de Argentina usado por opciones administrativas y validación pública sin cambiar timestamps UTC.

`payment_status = paid` continúa siendo un estado operativo mutable de Orders, no la evidencia financiera. Una creación administrativa ya pagada, una edición completa que transiciona de no pagado a pagado y el control rápido de `UpdateOrderPaymentStatus` emiten `OrderPaymentConfirmed` una sola vez por transición. El evento contiene únicamente `orderId`, importe, fecha comercial de confirmación y administrador; el listener síncrono de Management registra el hecho financiero dentro de la misma transacción. Volver a `unpaid` o cancelar no borra ni revierte el ledger automáticamente.

La eliminación también es recuperable. La papelera sólo administrativa restaura la misma fila, ID público, revisiones y snapshots; no existe ruta ni UI de eliminación física.

El índice administrativo lee snapshots, no proyecta el catálogo actual. Su consulta paginada carga anticipadamente `items.compositions` y `toppings` con cantidad fija de queries; `AdminOrderResource` expone nombres, cantidades y precios persistidos a la presentación de Orders. Las cards no consultan imágenes actuales ni reconstruyen etiquetas históricas. El contrato valida grupos/estado/fecha exactos, vista, cuatro campos conocidos y dirección; el estado exacto prevalece sobre su grupo y el orden siempre usa ID estable. Grid/list son sólo presentaciones del mismo resource.

## Management

```text
Management/
  Actions/       wallet/rule lifecycle, atomic manual/outflow/transfer/distribution/order-payment movements and reversals
  Data/          validated typed commands
  Enums/         financial transaction and semantic outflow types
  Http/          administrator-only controllers, requests and resource composition
  Listeners/     synchronous Orders payment integration
  Models/        wallets, ledger, semantic outflows/transfers/distributions/order payments, versioned rules and snapshots
  Policies/      explicit administrator authorization
  Queries/       bounded overview, metrics and paginated unified history
  routes.php
```

`Management` se eligió porque el límite futuro incluye más que tesorería, y porque `Inventory` debe conservar su significado de stock. La superficie administrativa es `/admin/management`; el texto visible es **Gestión**.

`CreateManualTransaction` recibe un importe positivo y el tipo determina el signo. `CreateManagementOutflow` registra una compra, gasto o retiro inmutable, valida una categoría activa del mismo tipo y crea una cabecera `management_outflow` más una entry negativa coincidente. `CreateManagementTransfer` registra una redistribución interna mediante una cabecera `transfer` y exactamente dos entries opuestas. Cada caso se ejecuta en una transacción PostgreSQL.

`CreateDistributionRule` crea una regla y su primera versión; `CreateDistributionRuleVersion` agrega una composición inmutable; `SetDistributionRuleActive` mantiene una única regla activa global sin reescribir historia. Las allocations usan basis points y deben sumar exactamente `10.000`. `CreateManagementDistribution` debita una billetera origen, recalcula el reparto, asigna el remainder a la última allocation canónica y persiste una cabecera neutral, sus entries y snapshots relacionales de forma atómica.

Una transferencia bloquea origen y destino por ID ascendente, exige que sean distintos y estén activos, y permite saldo negativo. `ManagementTransfer` posee una FK única hacia su `FinancialTransaction`; su neutralidad preserva el saldo global y excluye la operación de ingresos, egresos operativos y retiros personales.

`ManagementOutflow`, `ManagementTransfer`, `ManagementDistribution` y `ManagementOrderPayment` conservan la semántica mediante FKs únicas hacia `FinancialTransaction`; el ledger sigue siendo la única fuente del saldo. `BuildManagementOverview` deriva `SUM(ledger_entries.amount)`, suma `order_payment` a ingresos y presenta un historial unificado con filtros semánticos. El filtro por billetera usa los impactos del ledger y alcanza cualquier operación multi-entry.

`RecordConfirmedOrderPayment` traduce el evento de Orders a `CreateManagementOrderPayment` y luego delega el reparto a `ApplyDistributionToOrderPayment`. La primera Action crea el ingreso externo en la única billetera activa marcada `is_order_payment_default`; la segunda reutiliza `CreateManagementDistribution` con esa billetera, el mismo importe/fecha y la versión actual de la regla activa. `UNIQUE(order_id)` protege el ingreso y la FK anulable/única desde distribución al pago protege el reparto.

Sin regla activa, el pago queda pendiente y puede reintentarse desde Management. Una falla técnica con una regla seleccionada se propaga dentro del listener síncrono y revierte la confirmación completa. La distribución conserva la versión usada aunque cambie la regla. No se migran ni reparten retrospectivamente pedidos legacy.

`ArchiveWallet` y `RestoreWallet` conservan la misma fila. Las operaciones ordinarias no escriben sobre billeteras archivadas. `ReverseFinancialTransaction` bloquea el hecho original, exige que no sea una reversión ni tenga otra y crea una cabecera nueva con entries opuestas; esta corrección puede afectar una billetera archivada para no perder la capacidad de reparar historia. Un pago con distribución activa exige revertir primero el reparto y después el ingreso.

PostgreSQL garantiza tipos e importes válidos, relaciones 1:1 y consistencia entre cada origen semántico, su cabecera y sus entries. Para distribuciones exige un débito de origen y créditos que suman exactamente el importe; el vínculo automático único exige que origen, importe, fecha y administrador coincidan con el pago. Para pagos exige un origen único por Order y una entry positiva coincidente en la billetera predeterminada. También protege una sola regla activa, el orden de reversión, al menos una entry, una reversión por origen e inmutabilidad del historial. Consulte [MANAGEMENT.md](MANAGEMENT.md) y [ORDERS.md](ORDERS.md).

## Dashboard

```text
Dashboard/
  Data/          typed operational read-model values when needed
  Http/          administrator-only period validation and Inertia composition
  Queries/       bounded SQL aggregates and short operational queues
  Support/       period/bucket ordering helpers when needed
  routes.php
```

Sólo se crean carpetas con comportamiento real. Dashboard consulta Orders y sus snapshots; no se convierte en segundo dueño. Los períodos son hoy calendario, semana ISO o mes calendario en la zona horaria de negocio de Orders, y la pertenencia usa siempre `delivery_date`.

El read model define una población confirmada compartida (`waiting`, `prepared`, `delivered`) para ventas, ingresos cobrados, productos, unidades y evolución. Los ingresos cobrados además requieren `paid`. `pending_confirmation` permanece visible en cantidad de pedidos y cola urgente de hoy, pero no contribuye a esas métricas. Cancelados y eliminados lógicamente no contribuyen.

El trabajo urgente es siempre de hoy y se ordena por prioridad de estado, período, hora exacta con faltantes al final e ID. El resumen general de hoy se ordena cronológicamente. Ambas superficies hidratan pedidos seleccionados mediante `AdminOrderResource` y `OrderSummaryFormatter`; el frontend reutiliza `OrderDetailSheet` y Orders conserva toda autorización de comandos.

El ranking cuenta unidades reales persistidas: `units_count` en líneas directas o cantidades de composición en combinadas sin duplicar el padre. Crudas/cocinadas distribuye unidades de productos; delivery/pickup distribuye pedidos. La evolución vuelve en orden calendario y el backend completa ceros. Consultá [DASHBOARD.md](DASHBOARD.md).

Dashboard sólo consume el resumen compacto de Marketing para la recomendación activa, siguiente, no disponible o vacía. No consulta ventanas de Featured Product ni posee comandos, descuentos, códigos, ofertas ficticias o fallbacks de Marketing.

## Marketing

```text
Marketing/
  Actions/       transactional create, update, and disable use cases
  Data/          validated command and public-safe presentation values
  Http/          admin/public composition, Form Requests, and Resources where needed
  Models/        FeaturedProduct history and Product relationship
  Policies/      administrator authorization
  Queries/       canonical temporal, public-eligibility, and Dashboard-summary reads
  Support/       status and business-time presentation helpers where needed
  routes.php
```

Marketing guarda sólo la relación Product, título/copia opcionales en texto plano, flag activo e instantes UTC. Nombre, descripción, imagen, categoría, disponibilidad y precios públicos siguen siendo datos actuales de Product; nunca se copian a Marketing.

Una consulta canónica distingue recomendación temporalmente activa de públicamente visible. La visibilidad pública también exige Product disponible/no eliminado en Category activa/no eliminada. Admin Marketing, Home público y Dashboard reutilizan ese límite.

Las ventanas activas usan `[starts_at, ends_at)` y no se superponen. Las acciones verifican dentro de una transacción para devolver validación amigable; la restricción parcial GiST de PostgreSQL es autoritativa bajo concurrencia. Los registros se deshabilitan, no se eliminan, para conservar historial.

Marketing v1 termina en programación de Featured Product, preview, integración Home/carrito y resumen de Dashboard. No incluye descuentos, promociones, cupones, combos, upload de banners, campos Instagram ni automatización entre módulos. [MARKETING.md](MARKETING.md) contiene el contrato completo.

## Instagram

```text
Instagram/
  Actions/       due generation, atomic claim, completion/failure, stale reconciliation, media normalization
  Console/       scheduler entry points
  Enums/         publication lifecycle
  Http/          admin Inertia, signed media, token API, requests, middleware
  Models/        automation setting, assets, schedules, publications
  Support/       external-error redaction
  routes.php     authenticated administration
  public-routes.php signed Meta-downloadable media
```

El POS sigue siendo la fuente de verdad. Laravel crea filas de cola idempotentes y expone un claim atómico `pending → processing`; n8n contiene las llamadas oficiales a container/publish de Meta. Fallas reintentables previas a publicar pueden volver a pending dentro del límite. Resultados ambiguos de `media_publish` y claims vencidos terminan en falla que exige conciliación manual, nunca requeue automático.

La administración archiva historial, no lo elimina. La limpieza masiva conservadora retiene pending, processing y revisión manual. Retry/cancel bloquean y revalidan estado; resultados ambiguos o filas con ID Meta conocido nunca vuelven a cola. Programaciones simultáneas usan orden numérico explícito guardado en la publicación sin cambiar la idempotencia.

Instagram es hermano de Marketing, no hijo. Su URL administrativa anidada actual es legacy y no cambia la propiedad del módulo. La cola v1 no lee ni escribe Marketing.

Los uploads quedan privados, se decodifican y normalizan a JPEG estándar. URLs firmadas temporales ligan un asset a la ruta fija `story.jpg` sin exponer paths. Consultá [instagram-automation.md](instagram-automation.md).

## Settings

`GET/PUT /admin/settings/general` edita en una transacción los cuatro settings tipados de pedidos, tres descuentos positivos para preparación cruda y las filas existentes de adicionales. Settings configura datos; `OrderPricingEngine` conserva el cálculo.

| Setting                       |        Valor inicial |
| ----------------------------- | -------------------: |
| `combined_dozen_raw_price`    |                16000 |
| `combined_dozen_cooked_price` |                17500 |
| `delivery_fee`                |                    0 |
| `whatsapp_order_phone`        | vacío/sin configurar |

Los tiers de `raw_price_discounts` son `unit`, `half_dozen` y `dozen`, con valores positivos que se restan sólo para `raw`. Los precios de Product son cocinados y principales; no existe un “recargo de cocción” actual. Los nombres técnicos legacy `cooking_surcharges` se conservan únicamente donde sean necesarios para compatibilidad histórica y nunca representan el modelo actual.

Los adicionales iniciales son Lactonesa (500) y Yasgua (200); nombre, precio, disponibilidad y orden son configurables. Un teléfono de WhatsApp vacío impide crear el enlace con error explícito; no se reemplaza por un número real ni hardcodeado.

## Cómo agregar comportamiento

1. Ubicá el caso de uso en su módulo dueño.
2. Reutilizá un contrato acotado si otro módulo posee los datos requeridos.
3. Mantené input/output HTTP explícito mediante Form Requests y resources.
4. Usá una transacción para invariantes entre múltiples registros.
5. Agregá sólo carpetas con código real.

## POS-1: contratos preparados

- Orders persiste `admin`, `public_checkout` o `pos` como origen; `delivery`, `pickup` o `dine_in` describe fulfillment, no el canal público.
- `OrderCheckoutData::assertContext` protege la creación antes de resolver clientes. `CreateOrder` exige un actor con capacidad POS para el origen `pos`; el argumento histórico `adminId` identifica ese actor interno y nunca debe venir de un payload público.
- Identity publica `isAdministrator`, `canUsePos` y `canOperateCashier`, con Gates `administrator`, `use-pos` y `operate-cashier`. Los perfiles son fijos; no agregan rutas ni pantallas.
- Admin puede leer/editar un pedido POS de salón con Customer y programación nulos. Recursos, resumen y consumidores administrativos leen snapshots sin queries por cliente.
- No se crea Restaurant, DiningArea, DiningTable, TableSession, KitchenTicket ni un nuevo Quick Order. Las superficies existentes del baseline se conservan sin recuperaciones o rediseños.

## Restaurant — POS-2

`app/Modules/Restaurant/` contains physical Models, aggregate Actions, validated
HTTP Requests/Controller, an explicit FloorLayoutQuery and integer FloorGeometry.
The admin surface is `/admin/restaurant/floor`; area creation uses
`POST /admin/restaurant/areas`, and aggregate saving uses
`PUT /admin/restaurant/areas/{area}/layout`. There is no ordinary deletion route.

Areas are configurable, can be inactive and have independent logical dimensions.
Tables have stable integer identity, required visible code, optional name/capacity,
rectangle/circle shape, normalized geometry, rotation and active flag. Numeric
codes render as Mesa N; an optional name overrides the code presentation. Codes
are trimmed/uppercased and unique per area, including inactive tables.

A save includes all existing tables; omissions, duplicate/foreign IDs and unknown
element fields are rejected. Deactivate tables to preserve future references.
No TableSession, order submission, occupancy, payment, kitchen or Inventory
fulfillment behavior is part of this module.
