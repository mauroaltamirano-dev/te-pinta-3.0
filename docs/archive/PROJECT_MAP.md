# Mapa del proyecto

Utilice este mapa para modificar una regla en su responsable, en lugar de buscar en todo el repositorio.

## Mapa de mantenimiento de pedidos

| Cambio                                                                           | Fuente de verdad                                                                                                                        |
| -------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------- |
| Precios base cocinados, descuentos para productos crudos y vendibilidad          | `app/Modules/Products/`, especialmente `Pricing/DatabaseProductPriceProvider.php`                                                       |
| Contrato entre Products y Orders                                                 | `app/Core/Contracts/ProductPriceProvider.php`, `app/Core/Data/SellableProductPrice.php`                                                 |
| Agrupación, totales, docena combinada, toppings, envío y descuentos              | `app/Modules/Orders/Pricing/OrderPricingEngine.php`                                                                                     |
| Estructuras de resultados de precios                                             | `app/Modules/Orders/Pricing/OrderPriceQuote.php`, `PricingLine.php`, `PricingAddon.php`                                                 |
| Transacción de creación de pedidos, snapshots e idempotencia                     | `app/Modules/Orders/Actions/CreateOrder.php`, `Data/OrderCheckoutData.php`                                                              |
| Transacción de edición, recálculo de precios, auditoría y concurrencia           | `app/Modules/Orders/Actions/UpdateOrder.php`, `PersistOrderQuote.php`, `Models/OrderRevision.php`                                       |
| Hidratación estructurada de la edición y opciones históricas                     | `app/Modules/Orders/Actions/BuildAdminOrderEditData.php`, `BuildAdminOrderFormOptions.php`                                              |
| Resolución pública de slugs de productos y toppings                              | `app/Modules/Orders/Actions/ResolvePublicCheckoutSelections.php`                                                                        |
| Generación del ID de pedido                                                      | `database/migrations/2026_08_11_000400_create_orders_tables.php` (`order_public_id_seq`)                                                |
| Estados operativos y de pago, y métodos de pago                                  | `app/Modules/Orders/Enums/OrderStatus.php`, `PaymentStatus.php`, `PaymentMethod.php`                                                    |
| Fecha del calendario comercial de pedidos                                        | `app/Modules/Orders/Support/OrderBusinessDate.php`, `config/orders.php`                                                                 |
| Ticket administrativo compartido para copiar                                     | `app/Modules/Orders/Support/OrderSummaryFormatter.php`, `resources/js/features/orders/components/copy-order-detail-button.tsx`          |
| Papelera y restauración de pedidos y clientes                                    | `app/Modules/Orders/Actions/RestoreOrder.php`, `app/Modules/Customers/Actions/RestoreCustomer.php` y sus controladores de papelera      |
| URL de WhatsApp                                                                  | `app/Modules/Orders/Support/WhatsAppOrderLink.php`                                                                                      |
| Asociación y normalización de clientes                                           | `app/Modules/Customers/Support/PhoneNormalizer.php`, `app/Modules/Orders/Actions/CreateOrder.php`                                       |
| CRUD, agregados de lista, perfil, historial y papelera de clientes               | `app/Modules/Customers/`, `resources/js/features/customers/`, `resources/js/pages/admin/customers/`                                     |
| Lista, POS, detalle y diálogo de ticket de pedidos administrativos               | `app/Modules/Orders/Http/`, `resources/js/pages/admin/orders/`, `resources/js/features/orders/`                                         |
| Snapshots de tarjetas, filtros, ordenamiento e hidratación acotada de Orders     | `OrderManagementController.php`, `AdminOrderResource.php`, `resources/js/features/orders/components/order-{collection,card}.tsx`        |
| Inicio administrativo de Quick Order y borrador compartido                       | `resources/js/pages/admin/home.tsx`, `resources/js/features/orders/hooks/use-admin-order-draft.ts` y componentes compartidos de pedidos |
| Períodos, KPI, colas y distribuciones de Dashboard operativo                     | `app/Modules/Dashboard/`, `resources/js/pages/admin/dashboard.tsx`, `docs/DASHBOARD.md`                                                 |
| Programación, vista pública, vista previa y resumen de Featured Product          | `app/Modules/Marketing/`, `resources/js/features/marketing/`, `resources/js/pages/admin/marketing/`, `docs/MARKETING.md`                |
| Billeteras, ledger, egresos, transferencias e historial de Gestión               | `app/Modules/Management/`, `resources/js/features/management/`, `resources/js/pages/admin/management/`, `docs/MANAGEMENT.md`            |
| Panel de indicadores y agregados de Orders                                       | `app/Modules/Orders/Actions/BuildOrderDashboardData.php`, `resources/js/features/orders/components/orders-insights-panel.tsx`           |
| Contenido compartido del detalle, panel responsive y etiquetas de pedidos        | `resources/js/features/orders/components/order-detail-content.tsx`, `order-detail-sheet.tsx`, `presentation.ts`                         |
| Ticket de pedido y controles rápidos de filas                                    | `resources/js/features/orders/components/order-ticket-dialog.tsx`, `order-quick-controls.tsx`, `order-edit-button.tsx`                  |
| Menú, carrito y checkout públicos                                                | `app/Modules/PublicSite/routes.php`, `resources/js/pages/public/menu.tsx`, `resources/js/pages/public/cart.tsx`                         |
| Precios combinados base cocinados, descuento crudo, entrega, WhatsApp y toppings | `app/Modules/Settings/`, `resources/js/pages/admin/settings/general.tsx`                                                                |
| Guía de comportamiento de precios                                                | `docs/ORDER_PRICING.md`                                                                                                                 |
| Implementación actual y próximo slice del Inicio administrativo                  | `docs/CURRENT_STATE.md`, `docs/design/ADMIN_QUICK_ORDER.md`, `docs/decisions/0001-admin-home-is-quick-order.md`                         |

## Rutas

### Administrativas

Todas las rutas siguientes utilizan el prefijo `/admin` y los middleware `auth`, `verified`, `admin` y `admin.request.log`.

| Área                     | Archivo de rutas                    | Endpoints principales                                                                                                                              |
| ------------------------ | ----------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------- |
| Dashboard                | `app/Modules/Dashboard/routes.php`  | `GET /admin/dashboard`; modelo de lectura por período e hidratación opcional del pedido seleccionado                                               |
| Marketing                | `app/Modules/Marketing/routes.php`  | `/admin/marketing`; lista, creación, actualización, desactivación y vista previa pública fiel de Featured Product                                  |
| Management               | `app/Modules/Management/routes.php` | `GET /admin/management`; ciclo de billeteras; movimientos manuales, egresos y `POST /admin/management/transfers`; reversión de transacciones       |
| Customers                | `app/Modules/Customers/routes.php`  | CRUD más `/admin/customers/trash` y restauración                                                                                                   |
| Orders                   | `app/Modules/Orders/routes.php`     | lista, creación, detalle, edición, eliminación, papelera, restauración, `/price-preview`, autorización para editar pedidos cerrados, estado y pago |
| Products                 | `app/Modules/Products/routes.php`   | `/admin/settings/products` y categorías                                                                                                            |
| Configuración de pedidos | `app/Modules/Settings/routes.php`   | `GET/PUT /admin/settings/general`                                                                                                                  |

### Públicas

| Endpoint                       | Responsable                 | Notas                                                              |
| ------------------------------ | --------------------------- | ------------------------------------------------------------------ |
| `GET /menu/catalog`            | Límite Products/PublicSite  | Productos disponibles; solo slugs públicos                         |
| `GET /`                        | Límite PublicSite/Marketing | Inicio público con una vista segura y anulable de Featured Product |
| `GET /checkout/options`        | Orders                      | Toppings disponibles; solo slugs públicos                          |
| `POST /checkout/price-preview` | Orders                      | 20 solicitudes por minuto                                          |
| `POST /checkout/orders`        | Orders                      | 5 solicitudes por minuto; requiere una clave de idempotencia       |

No existe un endpoint público de búsqueda de clientes.

## Mapa del esquema

| Capacidad                                                    | Migraciones                                                                                                                                                         |
| ------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Base del cliente y campos de gestión                         | `2026_08_11_000300_create_customers_table.php`, `2026_08_12_040000_expand_customers_for_management.php`                                                             |
| Base del pedido, secuencia pública e ítems                   | `2026_08_11_000400_create_orders_tables.php`                                                                                                                        |
| Configuración de pedidos y catálogo de toppings              | `2026_08_12_050000_create_order_configuration_tables.php`                                                                                                           |
| Snapshots de checkout, composiciones y toppings              | `2026_08_12_060000_expand_orders_for_checkout.php`                                                                                                                  |
| Snapshots heredados del desglose de condición                | `2026_08_12_070000_add_cooking_breakdown_snapshots_to_orders.php`                                                                                                   |
| Versión de edición segura y revisiones redactadas de pedidos | `2026_08_13_230000_add_safe_order_editing.php`                                                                                                                      |
| Snapshots y restricciones de descuentos y métodos de pago    | `2026_08_14_000000_add_discounts_and_payment_methods_to_orders.php`                                                                                                 |
| Índice del historial de pedidos activos por cliente          | `2026_08_15_000000_add_customer_history_index_to_orders_table.php`                                                                                                  |
| Catálogo y precios de productos                              | `2026_08_11_000200_create_catalog_tables.php`, `2026_08_11_000500_expand_product_catalog.php`, `2026_08_11_000600_add_product_catalog_checks.php`                   |
| Historial y programación de Featured Product de Marketing    | `2026_08_23_220000_create_marketing_featured_products_table.php`; clave foránea de Product, ventana UTC, exclusión de superposición habilitada e índices de soporte |
| Billeteras y ledger financiero inmutable de Management       | `2026_08_26_180000_create_management_ledger_tables.php`; tipos/importes restringidos, cabeceras con entries, reversión única y triggers de inmutabilidad            |
| Egresos semánticos y categorías de Management                | `2026_08_27_000000_create_management_outflows.php`; categorías por tipo, origen 1:1, entry negativa coincidente e inmutabilidad                                     |
| Transferencias neutrales entre billeteras de Management      | `2026_08_27_120000_create_management_transfers.php`; origen/destino distintos, fuente 1:1, dos entries opuestas, suma cero e inmutabilidad                          |

## Pruebas

| Área                                                        | Ubicación                                                   |
| ----------------------------------------------------------- | ----------------------------------------------------------- |
| Customers, agregados, perfil, teléfono y papelera           | `tests/Feature/Customers/`                                  |
| Precios, checkout y restricciones de base de datos          | `tests/Feature/Orders/`                                     |
| Modelo de lectura de snapshots de tarjetas de Orders        | `tests/Feature/Orders/OrderCardReadModelTest.php`           |
| Edición administrativa segura de pedidos                    | `tests/Feature/Orders/AdminOrderEditingTest.php`            |
| Papelera y restauración de Orders                           | `tests/Feature/Orders/OrderTrashRestorationTest.php`        |
| Manifiesto administrativo instalable                        | `tests/Feature/PwaManifestTest.php`                         |
| Configuración de pedidos                                    | `tests/Feature/Settings/OrderingSettingsManagementTest.php` |
| Recorrido de pedidos administrativos                        | `tests/e2e/orders.spec.ts`                                  |
| Recorrido de lista, perfil y detalle de pedido de Customers | `tests/e2e/customers.spec.ts`                               |
| Modelo de lectura de Dashboard operativo                    | `tests/Feature/Dashboard/`                                  |
| Recorrido de Dashboard operativo                            | `tests/e2e/dashboard.spec.ts`                               |
| Backend de Featured Product de Marketing                    | `tests/Feature/Marketing/`                                  |
| Backend, constraints y queries de Management                | `tests/Feature/Management/`                                 |
| Recorrido responsive de Gestión                             | `tests/e2e/management.spec.ts`                              |
| Recorrido responsive administrativo y público de Marketing  | `tests/e2e/marketing.spec.ts`                               |
| Derivación de checkout público a WhatsApp                   | `tests/e2e/public-checkout.spec.ts`                         |
| Products                                                    | `tests/Feature/Products/`, `tests/e2e/products.spec.ts`     |

## Mapa de automatización de Instagram

| Aspecto                                | Responsable                                                                                                                                                    |
| -------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Página administrativa y CRUD           | `app/Modules/Instagram/Http/Controllers/InstagramManagementController.php`, `resources/js/pages/admin/instagram/index.tsx`, `resources/js/features/instagram/` |
| Generación de vencimientos y scheduler | `GenerateDueInstagramStoryPublications`, comandos de consola del módulo, `routes/console.php`                                                                  |
| API atómica de n8n                     | `routes/api.php`, `AuthenticateN8nAutomation`, `InstagramAutomationApiController`, acciones de reserva, finalización y fallo                                   |
| Medios temporales                      | `app/Modules/Instagram/public-routes.php`, `InstagramStoryMediaController`                                                                                     |
| Persistencia                           | `database/migrations/2026_08_23_210000_create_instagram_story_automation_tables.php`                                                                           |
| Runtime y workflow de n8n              | `ops/n8n/`                                                                                                                                                     |
| Guía del operador                      | `docs/instagram-automation.md`                                                                                                                                 |
| Cobertura crítica                      | `tests/Feature/Instagram/InstagramAutomationTest.php`                                                                                                          |

## Infraestructura

| Necesidad                                    | Ubicación                                                                                                                                   |
| -------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------- |
| Composición de rutas de aplicación y módulos | `routes/web.php`                                                                                                                            |
| Registro de middleware                       | `bootstrap/app.php`                                                                                                                         |
| Autenticación e identidad                    | `app/Modules/Identity/`, `config/auth.php`, `config/fortify.php`                                                                            |
| Base de datos                                | `config/database.php`, `database/migrations/`                                                                                               |
| Aislamiento de la base de datos de pruebas   | `.env.testing.example`, `app/Core/Support/TestingDatabaseGuard.php`, `tests/Unit/Core/TestingDatabaseGuardTest.php`, `playwright.config.ts` |
| Logging                                      | `config/logging.php`, `storage/logs/`                                                                                                       |
| Entrada y layouts de Inertia                 | `resources/js/app.tsx`, `resources/js/layouts/`                                                                                             |
| Tokens de diseño                             | `resources/css/app.css`                                                                                                                     |
| Configuración de calidad                     | `composer.json`, `package.json`, `phpstan.neon`, `pint.json`, `playwright.config.ts`                                                        |
| Metadatos administrativos instalables        | `public/manifest.webmanifest`, `public/icons/`, `resources/views/app.blade.php`                                                             |
| Runtime operativo preparado de Tailscale     | `deploy/tailscale/`                                                                                                                         |

Los archivos generados por Wayfinder dentro de `resources/js/routes/`, `resources/js/actions/` y `resources/js/wayfinder/` son salidas. Modifique su fuente PHP, no los archivos generados.

`GET /admin` pertenece a Orders y renderiza el Inicio de Quick Order. `GET /admin/dashboard` conserva Dashboard como destino secundario. La confirmación rápida negocia JSON desde el `POST /admin/orders` existente; la ruta completa de creación continúa recibiendo su respuesta de redirección.

Dashboard solo es responsable de la proyección operativa tipada sobre Orders y las filas de snapshots persistidos. Utiliza `OrderBusinessDate` para resolver el día actual, la semana ISO en curso y el mes calendario; luego determina toda pertenencia mediante `delivery_date`. Orders continúa siendo responsable de los totales, estados, recursos, resúmenes, políticas y comandos; Dashboard abre el `OrderDetailSheet` compartido en lugar de agregar otra implementación del detalle del pedido. [DASHBOARD.md](DASHBOARD.md) es el contrato de métricas.

Marketing es responsable de la programación de Featured Product y de la distinción canónica entre actividad temporal y visibilidad pública. PublicSite renderiza su modelo visual seguro, Dashboard renderiza su resumen compacto y Products conserva la autoridad sobre vendibilidad, identidad, imagen, descripción y precios actuales. Marketing e Instagram son módulos hermanos; ninguno lee ni escribe los modelos del otro en v1.

El índice de Customers resuelve `orders_count`, `effective_orders_sum_total` y `latestOrder` en un conjunto acotado de consultas antes de paginar. Su contrato de ordenamiento validado asigna `name`, `orders_count` o `historical_spend` a expresiones conocidas más un desempate por `id`, y conserva la búsqueda, el orden y la dirección en los enlaces del paginador. El perfil serializa una vez su historial de pedidos activos; un parámetro opcional validado `?order=TP-*` solo hidrata mediante `AdminOrderResource` y `OrderSummaryFormatter` un pedido activo perteneciente a ese cliente. Customers renderiza el `OrderDetailSheet` perteneciente a Orders, que envuelve el `OrderDetailContent` canónico; no es responsable del comportamiento del detalle del pedido ni lo duplica.

La limpieza de clientes no es una ruta de la aplicación ni una migración. La revisión de datos de desarrollo comenzó como una auditoría de solo SELECT con teléfonos enmascarados; después de la aprobación explícita de cada ID, los clientes `1`, `3`, `4`, `5`, `10`, `11`, `12`, `13`, `14` y `15` atravesaron el límite existente de borrado lógico `DeleteCustomer`. Los clientes `16` y `19` se conservaron explícitamente. No se permiten eliminaciones físicas masivas ni basadas en nombres inferidos.

El enlace de modelos de rutas de Order utiliza `orders.public_id`, incluidas `GET /admin/orders/{public_id}/edit`, `POST /admin/orders/{public_id}/enable-edit` y `PUT /admin/orders/{public_id}`. El frontend nunca identifica un pedido mediante su clave primaria interna.

El índice de Orders carga anticipadamente `items.compositions` y `toppings` paginados antes de serializar con `AdminOrderResource`. `OrderCollection` alterna la presentación en grilla o lista persistida en la URL, mientras que `OrderCard` es responsable de ambas densidades y de las acciones existentes. Los grupos de estado y los filtros exactos de estado y fecha se validan; el ordenamiento asigna `delivery_date`, `customer`, `total` o `id` a columnas conocidas y agrega el ID como desempate estable. Los snapshots textuales inmutables se renderizan sin imágenes actuales de Product. Las referencias proporcionadas para las tarjetas permanecen en `docs/design/references/orders-cards-desktop.png` y `orders-cards-mobile.png`.
