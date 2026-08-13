# Testing

Tests prove backend authority, historical stability, and the two order creation paths. Frontend validation alone is never security or pricing coverage.

## Focused suites

| Suite                                                       | Proves                                                                                                                                          |
| ----------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------- |
| `tests/Feature/Customers/PhoneNormalizerTest.php`           | Deterministic digits-only normalization and null handling                                                                                       |
| `tests/Feature/Customers/CustomerManagementTest.php`        | Admin boundary, duplicate names, optional phone, active phone uniqueness, search, private notes, history/spend, soft deletion, safe logs        |
| `tests/Feature/Orders/OrderPricingEngineTest.php`           | Grouping matrix, raw/cooked tiers, base/cooking breakdown, fixed combined price and surcharge, savings, toppings, delivery, limits, sellability |
| `tests/Feature/Orders/OrderCheckoutHttpTest.php`            | Public/admin creation, price authority, snapshot-safe cooking output, legacy omission, matching, idempotency, throttling, state/payment         |
| `tests/Feature/Orders/OrderDatabaseConstraintTest.php`      | PostgreSQL sequence, snapshot/value and cooking-breakdown checks, unique idempotency hash                                                       |
| `tests/Feature/Settings/OrderingSettingsManagementTest.php` | Admin-only typed settings and atomic updates                                                                                                    |
| `tests/Unit/Core/TestingDatabaseGuardTest.php`              | Testing-only database-name boundary, `DB_URL` precedence, and non-testing bypass                                                                |
| `tests/e2e/orders.spec.ts`                                  | Cooked POS breakdown, insights panel, drawer/full detail, status/payment/copy, history navigation, desktop/mobile                               |
| `tests/Feature/Orders/OrderDashboardDataTest.php`           | Real most-ordered product aggregation, combined-dozen composition, images, delivery and cooking-condition distributions, soft deletes           |
| `tests/e2e/public-checkout.spec.ts`                         | Persistence before mocked WhatsApp navigation                                                                                                   |

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
- New raw/cooked orders snapshot base and cooking values per line; legacy orders never derive them from current settings.
- Cooking breakdown never changes the final total; combined dozen cooking remains cooked minus raw (`17500 - 16000 = 1500`).
- Customer private notes never appear publicly or in WhatsApp.
- Cancelled orders remain historical but do not contribute to effective spend.
- Guests/non-admins cannot access administrative operations.

## Test data

Persistence tests use `RefreshDatabase` and the isolated test connection configured from `.env.testing.example`. `TestingDatabaseGuard` rejects `APP_ENV=testing` when the effective database name, including a `DB_URL` override, lacks a standalone `test` or `testing` segment. Playwright global setup seeds deterministic catalog/admin data through `tests/Support/PlaywrightDatabaseSeeder.php`. Never use `migrate:fresh` on the existing development database to prove clean migrations; follow [DEVELOPMENT.md](DEVELOPMENT.md#migration-safety).

## Delivery report

Report the exact commands executed, their results, and any skipped or environment-blocked check. Never describe an unexecuted check as passing.
