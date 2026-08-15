# Testing

Tests prove backend authority, historical stability, and the two order creation paths. Frontend validation alone is never security or pricing coverage.

## Focused suites

| Suite                                                       | Proves                                                                                                                                    |
| ----------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------- |
| `tests/Feature/Customers/PhoneNormalizerTest.php`           | Deterministic digits-only normalization and null handling                                                                                 |
| `tests/Feature/Customers/CustomerManagementTest.php`        | Admin boundary, duplicate names, optional phone, active phone uniqueness, search, private notes, history/spend, soft deletion, safe logs  |
| `tests/Feature/Customers/CustomerTrashRestorationTest.php`  | Admin trash/restore, unchanged linked orders, active-only restore, and no-mutation phone-conflict rejection                               |
| `tests/Feature/Orders/OrderPricingEngineTest.php`           | Grouping, cooking snapshots, discounts/rounding/caps, toppings, delivery, limits, and sellability                                         |
| `tests/Feature/Orders/OrderCheckoutHttpTest.php`            | Public/admin creation, Argentina business date, backend discount, payment invariant, copy ticket, snapshots, idempotency, throttling      |
| `tests/Feature/Orders/OrderDatabaseConstraintTest.php`      | PostgreSQL sequence, snapshot/discount/payment constraints, cooking checks, unique idempotency hash                                       |
| `tests/Feature/Orders/AdminOrderEditingTest.php`            | Active/closed editing, structured quantities, current-rule repricing, immutable ID, audit, rollback, unavailable history, and concurrency |
| `tests/Feature/Orders/OrderTrashRestorationTest.php`        | Admin-only trash and restore by immutable public ID                                                                                       |
| `tests/Feature/Settings/OrderingSettingsManagementTest.php` | Admin-only typed settings and atomic updates                                                                                              |
| `tests/Unit/Core/TestingDatabaseGuardTest.php`              | Testing-only database-name boundary, `DB_URL` precedence, and non-testing bypass                                                          |
| `tests/e2e/orders.spec.ts`                                  | Ticket dialog/copy, mobile two-column catalog/navigation, quick status/payment, edit/cancel, closed unlock, immutable ID, and repricing   |
| `tests/Feature/Orders/AdminQuickOrderHomeTest.php`          | Quick Home authorization, sellable catalog/category filtering, props, and Dashboard separation                                            |
| `tests/e2e/admin-quick-order.spec.ts`                       | Fast quantities, backend discount/payment, success copy/detail, exact-one confirmation, cancel/discard, and screenshots                   |
| `tests/Feature/Orders/OrderDashboardDataTest.php`           | Real most-ordered product aggregation, combined-dozen composition, images, delivery and cooking-condition distributions, soft deletes     |
| `tests/e2e/public-checkout.spec.ts`                         | Persistence before mocked WhatsApp navigation                                                                                             |
| `tests/e2e/trash-restoration.spec.ts`                       | Orders/customers trash restore journey and actionable customer phone-conflict feedback                                                    |
| `tests/Feature/PwaManifestTest.php`                         | Manifest metadata, icons/shortcut, layout linkage, and absence of a service worker                                                        |

The pricing matrix includes 5/6/7/12/18 of one product, 3+3, 6+3+3, 6+6, and 24 varied units. When a grouping rule changes, update `docs/ORDER_PRICING.md` and its focused tests together.

## Commands

```bash
# Focused backend
./vendor/bin/sail artisan test tests/Feature/Customers tests/Feature/Orders tests/Feature/Settings

# Focused browser
./vendor/bin/sail npm run test:e2e -- tests/e2e/orders.spec.ts tests/e2e/public-checkout.spec.ts

# Complete PHP gate: Pint check + PHPStan + Laravel suite
./vendor/bin/sail composer test

# Complete frontend gate
./vendor/bin/sail npm run lint:check
./vendor/bin/sail npm run format:check
./vendor/bin/sail npm run types:check
./vendor/bin/sail npm run build
./vendor/bin/sail npm run test:e2e

# Dependency audits
./vendor/bin/sail composer audit
./vendor/bin/sail npm audit
```

`composer ci:check` combines frontend lint/format/types with `composer test`; it does not run Vite build, Playwright, or dependency audits.

Quick Order and order-ticket visual captures are written under `storage/app/`, including `admin-quick-order-mobile-grid.png`, `orders-ticket-dialog.png`, and `orders-ticket-mobile.png`. They are verification artifacts and are not versioned.

## Required regression invariants

- Client totals/prices/shipping/combined price cannot alter persisted values.
- Public input cannot set internal IDs, paid state, or operational state.
- Deleted/unavailable catalog entries cannot be sold.
- Exact public retries create one order; changed-payload key reuse is rejected.
- Product, customer address, delivery-fee, and topping changes do not alter snapshots.
- New raw/cooked orders snapshot base and cooking values per line; legacy orders never derive them from current settings.
- Cooking breakdown never changes the final total; combined dozen cooking remains cooked minus raw (`17500 - 16000 = 1500`).
- Percentage/fixed discounts are backend-calculated over items (including cooking) plus toppings, exclude shipping, round/cap correctly, and snapshot the applied result.
- Paid orders require a valid method; unpaid orders persist none; public checkout can set neither payment method nor discount.
- Delivery-date minimums come from `OrderBusinessDate` in the configured Argentina timezone, not browser UTC; timestamps remain stored in UTC.
- Customer private notes never appear publicly or in WhatsApp.
- Cancelled orders remain historical but do not contribute to effective spend.
- Guests/non-admins cannot access administrative operations.
- An edit with a stale `expected_version` is rejected, including when another surface changed status or payment.
- Delivered/cancelled edits require a reasoned version-bound grant; one successful update consumes it without implicitly changing status.
- Economic edits reprice the complete order with current rules and replace snapshots only for that order; non-economic edits keep the persisted quote.
- Historical unavailable selections remain visible and unchanged for non-economic corrections, but block current-rule repricing until removed or replaced.
- Revision failure rolls back order fields, totals, item/composition/topping replacement, version, and audit together.
- Soft-deleted orders/customers can be restored only by an admin; no force-delete path is exposed and customer lifecycle never cascades to orders.
- A restore-time normalized-phone conflict leaves both customer records unchanged.
- The installable manifest starts at `/admin`, links valid icons/shortcut, and registers no service worker.

## Test data

Persistence tests use `RefreshDatabase` and the isolated test connection configured from `.env.testing.example`. `TestingDatabaseGuard` rejects `APP_ENV=testing` when the effective database name, including a `DB_URL` override, lacks a standalone `test` or `testing` segment. Playwright global setup seeds deterministic catalog/admin data through `tests/Support/PlaywrightDatabaseSeeder.php`. Never use `migrate:fresh` on the existing development database to prove clean migrations; follow [DEVELOPMENT.md](DEVELOPMENT.md#migration-safety).

## Delivery report

Report the exact commands executed, their results, and any skipped or environment-blocked check. Never describe an unexecuted check as passing.
