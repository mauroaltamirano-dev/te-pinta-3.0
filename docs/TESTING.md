# Testing

Tests prove backend authority, historical stability, and the two order creation paths. Frontend validation alone is never security or pricing coverage.

## Focused suites

| Suite                                                       | Proves                                                                                                                                         |
| ----------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------- |
| `tests/Feature/Customers/PhoneNormalizerTest.php`           | Deterministic digits-only normalization and null handling                                                                                      |
| `tests/Feature/Customers/CustomerManagementTest.php`        | Admin boundary, duplicate names, optional phone, active phone uniqueness, search, private notes, history/spend, soft deletion, safe logs       |
| `tests/Feature/Orders/OrderPricingEngineTest.php`           | Grouping matrix, raw/cooked prices, fixed combined prices, savings, toppings, delivery/pickup, limits, availability, public slugs              |
| `tests/Feature/Orders/OrderCheckoutHttpTest.php`            | Public/admin creation, backend price authority, privacy-safe matching, idempotency, rate limiting, states/payment, quick create, soft deletion |
| `tests/Feature/Orders/OrderDatabaseConstraintTest.php`      | PostgreSQL sequence, snapshot/value checks, unique idempotency hash                                                                            |
| `tests/Feature/Settings/OrderingSettingsManagementTest.php` | Admin-only typed settings and atomic updates                                                                                                   |
| `tests/e2e/orders.spec.ts`                                  | Admin POS creation and lifecycle journey                                                                                                       |
| `tests/e2e/public-checkout.spec.ts`                         | Persistence before mocked WhatsApp navigation                                                                                                  |

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

## Required regression invariants

- Client totals/prices/shipping/combined price cannot alter persisted values.
- Public input cannot set internal IDs, paid state, or operational state.
- Deleted/unavailable catalog entries cannot be sold.
- Exact public retries create one order; changed-payload key reuse is rejected.
- Product, customer address, delivery-fee, and topping changes do not alter snapshots.
- Customer private notes never appear publicly or in WhatsApp.
- Cancelled orders remain historical but do not contribute to effective spend.
- Guests/non-admins cannot access administrative operations.

## Test data

Persistence tests use `RefreshDatabase` and the isolated test connection. Playwright global setup seeds deterministic catalog/admin data through `tests/Support/PlaywrightDatabaseSeeder.php`. Never use `migrate:fresh` on the existing development database to prove clean migrations.

## Delivery report

Report the exact commands executed, their results, and any skipped or environment-blocked check. Never describe an unexecuted check as passing.
