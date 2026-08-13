# Project map

Use this map to change a rule at its owner instead of searching the whole repository.

## Ordering maintainer map

| Change                                                 | Source of truth                                                                                                               |
| ------------------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------------- |
| Product base prices and sellability                    | `app/Modules/Products/`, especially `Pricing/DatabaseProductPriceProvider.php`                                                |
| Products-to-Orders contract                            | `app/Core/Contracts/ProductPriceProvider.php`, `app/Core/Data/SellableProductPrice.php`                                       |
| Grouping, totals, combined dozen, toppings, shipping   | `app/Modules/Orders/Pricing/OrderPricingEngine.php`                                                                           |
| Pricing result shapes                                  | `app/Modules/Orders/Pricing/OrderPriceQuote.php`, `PricingLine.php`, `PricingAddon.php`                                       |
| Order creation transaction, snapshots, idempotency     | `app/Modules/Orders/Actions/CreateOrder.php`, `Data/OrderCheckoutData.php`                                                    |
| Order edit transaction, repricing, audit, concurrency  | `app/Modules/Orders/Actions/UpdateOrder.php`, `PersistOrderQuote.php`, `Models/OrderRevision.php`                             |
| Structured edit hydration and historical options       | `app/Modules/Orders/Actions/BuildAdminOrderEditData.php`, `BuildAdminOrderFormOptions.php`                                    |
| Public product/topping slug resolution                 | `app/Modules/Orders/Actions/ResolvePublicCheckoutSelections.php`                                                              |
| Order ID generation                                    | `database/migrations/2026_08_11_000400_create_orders_tables.php` (`order_public_id_seq`)                                      |
| Operational/payment states                             | `app/Modules/Orders/Enums/OrderStatus.php`, `PaymentStatus.php`                                                               |
| Shared text summary                                    | `app/Modules/Orders/Support/OrderSummaryFormatter.php`                                                                        |
| WhatsApp URL                                           | `app/Modules/Orders/Support/WhatsAppOrderLink.php`                                                                            |
| Customer matching/normalization                        | `app/Modules/Customers/Support/PhoneNormalizer.php`, `app/Modules/Orders/Actions/CreateOrder.php`                             |
| Customer CRUD/history                                  | `app/Modules/Customers/` and `resources/js/pages/admin/customers/`                                                            |
| Admin order list/POS/detail/drawer                     | `app/Modules/Orders/Http/`, `resources/js/pages/admin/orders/`, `resources/js/features/orders/`                               |
| Admin Quick Order Home and shared draft                | `resources/js/pages/admin/home.tsx`, `resources/js/features/orders/hooks/use-admin-order-draft.ts`, shared order components   |
| Orders insights panel and aggregates                   | `app/Modules/Orders/Actions/BuildOrderDashboardData.php`, `resources/js/features/orders/components/orders-insights-panel.tsx` |
| Shared order detail content and presentation labels    | `resources/js/features/orders/components/order-detail-content.tsx`, `presentation.ts`                                         |
| Public menu/cart/checkout                              | `app/Modules/PublicSite/routes.php`, `resources/js/pages/public/menu.tsx`, `resources/js/pages/public/cart.tsx`               |
| Combined prices, delivery, WhatsApp, cooking, toppings | `app/Modules/Settings/`, `resources/js/pages/admin/settings/general.tsx`                                                      |
| Pricing behavior guide                                 | `docs/ORDER_PRICING.md`                                                                                                       |
| Current implementation and next admin Home slice       | `docs/CURRENT_STATE.md`, `docs/design/ADMIN_QUICK_ORDER.md`, `docs/decisions/0001-admin-home-is-quick-order.md`               |

## Routes

### Administrative

All routes below use the `/admin` prefix and `auth`, `verified`, `admin`, and `admin.request.log` middleware.

| Area              | Routes file                        | Main endpoints                                                                              |
| ----------------- | ---------------------------------- | ------------------------------------------------------------------------------------------- |
| Customers         | `app/Modules/Customers/routes.php` | CRUD at `/admin/customers`                                                                  |
| Orders            | `app/Modules/Orders/routes.php`    | list/create/detail/edit/delete, `/price-preview`, closed-edit grant, status, payment status |
| Products          | `app/Modules/Products/routes.php`  | `/admin/settings/products` and categories                                                   |
| Ordering settings | `app/Modules/Settings/routes.php`  | `GET/PUT /admin/settings/general`                                                           |

### Public

| Endpoint                       | Owner                        | Notes                                       |
| ------------------------------ | ---------------------------- | ------------------------------------------- |
| `GET /menu/catalog`            | Products/PublicSite boundary | Available products; public slugs only       |
| `GET /checkout/options`        | Orders                       | Available toppings; public slugs only       |
| `POST /checkout/price-preview` | Orders                       | 20 requests/minute                          |
| `POST /checkout/orders`        | Orders                       | 5 requests/minute; requires idempotency key |

There is no public customer lookup endpoint.

## Schema map

| Capability                                          | Migrations                                                                                                                                        |
| --------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------- |
| Customer base and management fields                 | `2026_08_11_000300_create_customers_table.php`, `2026_08_12_040000_expand_customers_for_management.php`                                           |
| Order base, public sequence, items                  | `2026_08_11_000400_create_orders_tables.php`                                                                                                      |
| Ordering settings and toppings catalog              | `2026_08_12_050000_create_order_configuration_tables.php`                                                                                         |
| Checkout snapshots, compositions, topping snapshots | `2026_08_12_060000_expand_orders_for_checkout.php`                                                                                                |
| Raw-base and cooking-breakdown snapshots            | `2026_08_12_070000_add_cooking_breakdown_snapshots_to_orders.php`                                                                                 |
| Safe edit version and redacted order revisions      | `2026_08_13_230000_add_safe_order_editing.php`                                                                                                    |
| Product catalog/prices/surcharges                   | `2026_08_11_000200_create_catalog_tables.php`, `2026_08_11_000500_expand_product_catalog.php`, `2026_08_11_000600_add_product_catalog_checks.php` |

## Tests

| Area                                    | Location                                                    |
| --------------------------------------- | ----------------------------------------------------------- |
| Customers and phone normalization       | `tests/Feature/Customers/`                                  |
| Pricing, checkout, database constraints | `tests/Feature/Orders/`                                     |
| Administrative safe order editing       | `tests/Feature/Orders/AdminOrderEditingTest.php`            |
| Ordering configuration                  | `tests/Feature/Settings/OrderingSettingsManagementTest.php` |
| Admin order journey                     | `tests/e2e/orders.spec.ts`                                  |
| Public checkout/WhatsApp handoff        | `tests/e2e/public-checkout.spec.ts`                         |
| Products                                | `tests/Feature/Products/`, `tests/e2e/products.spec.ts`     |

## Infrastructure

| Need                                 | Location                                                                                                                                    |
| ------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------- |
| Application/module route composition | `routes/web.php`                                                                                                                            |
| Middleware registration              | `bootstrap/app.php`                                                                                                                         |
| Auth and identity                    | `app/Modules/Identity/`, `config/auth.php`, `config/fortify.php`                                                                            |
| Database                             | `config/database.php`, `database/migrations/`                                                                                               |
| Testing database isolation           | `.env.testing.example`, `app/Core/Support/TestingDatabaseGuard.php`, `tests/Unit/Core/TestingDatabaseGuardTest.php`, `playwright.config.ts` |
| Logging                              | `config/logging.php`, `storage/logs/`                                                                                                       |
| Inertia entry/layouts                | `resources/js/app.tsx`, `resources/js/layouts/`                                                                                             |
| Design tokens                        | `resources/css/app.css`                                                                                                                     |
| Quality configuration                | `composer.json`, `package.json`, `phpstan.neon`, `pint.json`, `playwright.config.ts`                                                        |

Generated Wayfinder files under `resources/js/routes/`, `resources/js/actions/`, and `resources/js/wayfinder/` are outputs. Change their PHP source, not the generated files.

`GET /admin` is owned by Orders and renders the Quick Order Home. `GET /admin/dashboard` preserves Dashboard as a secondary destination. Quick confirmation negotiates JSON from the existing `POST /admin/orders`; the full create route still receives its redirect response.

Order route-model binding uses `orders.public_id`, including `GET /admin/orders/{public_id}/edit`, `POST /admin/orders/{public_id}/enable-edit`, and `PUT /admin/orders/{public_id}`. The frontend never addresses an order by its internal primary key.
