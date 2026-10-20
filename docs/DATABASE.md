# Database

PostgreSQL 18 is the application database and the final integrity boundary. The test suite and every migration assume PostgreSQL; there is no SQLite support. Migrations live in `database/migrations/` and are append-only: never edit an applied migration, add a new one.

Domain-specific tables are described in their own documents: [ORDERS_AND_PRICING.md](ORDERS_AND_PRICING.md), [INVENTORY.md](INVENTORY.md), [FINANCE.md](FINANCE.md), [STOREFRONT_AND_MARKETING.md](STOREFRONT_AND_MARKETING.md).

## Money and identifiers

| Convention | Rule |
| --- | --- |
| Money | Exact integer Argentine pesos. Columns may be `decimal(14,2)` for historical reasons, but checks require `amount = trunc(amount)`; models cast to `int`. Never divide by 100. |
| Commercial values | Prices and order totals are non-negative. Ledger amounts are signed and non-zero. |
| Physical quantities | `NUMERIC(20,6)` handled as decimal strings; conversion factors `NUMERIC(20,9)`. Never JavaScript `Number`. |
| Percentages | Integer basis points (`10000 = 100 %`). |
| Primary keys | Internal `bigint` identities; never shown to customers. |
| Order IDs | `orders.public_id` defaults to `TP-` + six digits from sequence `order_public_id_seq`. Gaps after rollbacks are valid; never "repair" with `max(id) + 1`. |
| Public catalog identity | Product and add-on slugs; internal IDs are rejected on public endpoints. |

## Tenancy

- Every business row belongs to a restaurant: `restaurant_id bigint not null`, foreign key to `restaurants` (`on delete restrict`), indexed. Excluded: framework tables, `users` (global identities; access comes from memberships) and `inventory_units` (system units).
- The column default is the PostgreSQL session setting `app.current_restaurant_id`, set on every connection by `App\Core\Tenancy\RestaurantContext`. A write without context fails with a NOT NULL violation. Manual PDO connections (concurrency tests, scripts) must run `SET app.current_restaurant_id = '<id>'`. Because it is a session setting, do not put a transaction-mode pooler (for example PgBouncer `transaction`) in front of the app without setting it per transaction.
- Every process currently runs as `tenancy.default_restaurant_id` (`1`, "Te Pinta"). Business unique keys are unique per restaurant: category/product/tag/add-on slugs, order setting keys, raw-price discount tiers, outflow category codes, active customer phone, inventory category/item/recipe/costing-policy names, the single active distribution rule, the single order-payment default wallet and the featured-product window exclusion.
- Pending before a second restaurant: read isolation with row-level security (it also covers `DB::table` reads), per-restaurant order numbering (`order_public_id_seq` is global), same-restaurant validation in cross-table triggers, and per-restaurant seed settings (`order_settings`, `order_addons`, outflow categories, Instagram).

- Restaurant branding is stored in `restaurants.accent_color varchar(7) not null default '#b84f3f'`. The `restaurants_accent_color_hex` check requires lowercase six-digit hex; backend validation also enforces contrast. Migration `2026_10_16_020000_add_restaurant_accent_color.php` adds only this configuration column and preserves existing restaurant rows. Rolling it back discards customized accents; it does not affect order or financial history.

Decision record: [ADR 0012](decisions/0012-tenant-ready-schema.md). Migration: `2026_10_16_000000_prepare_restaurant_tenancy.php`.

## Staff memberships

- `restaurant_user` (`restaurant_id`, `user_id`, `role`, `is_active`, timestamps) gives a user access to one restaurant. Unique per restaurant and user; `role` is checked against the code-defined roles (`owner`, `manager`, `cashier`, `waiter`, `kitchen`, `accountant`). Login and every admin request require an active membership in the current restaurant.
- `users.is_admin` and `users.staff_profile` are legacy columns kept for one release so a rollback still works. No code reads them; every membership save mirrors them (active owner → `is_admin`; active waiter/cashier → `staff_profile`; any other role or an inactive member → neither). Drop them in the release after the one that introduced memberships.

Decision record: [ADR 0013](decisions/0013-code-defined-roles-and-permissions.md). Migration: `2026_10_17_000000_create_restaurant_memberships.php`.

## Constraint and trigger philosophy

- Application validation produces clear errors; PostgreSQL enforces the invariants that must hold under concurrency or direct SQL.
- Checks guard enums, positive/non-negative amounts, integer pesos, totals arithmetic and shape rules (for example `total = items_subtotal + toppings_subtotal + shipping_fee_snapshot - discount_amount`).
- Deferred constraint triggers verify multi-row aggregates at commit: a financial transaction has its entries, a transfer sums to zero, a distribution matches its rule version, an inventory transaction has movements.
- Immutability triggers reject `UPDATE`/`DELETE` on financial and physical history (ledger, outflows, transfers, distributions, order payments, inventory transactions, movements, cost layers, production, adjustments, consumption). Corrections are new reversing rows.
- Several guards bind child inserts to the creating top-level transaction (full `xid8`), so committed aggregates cannot receive late lines.
- Partial unique indexes and GiST exclusion constraints are the concurrency authority where row locks cannot serialize (active phone, featured-product windows).
- Constraints that legacy data cannot satisfy are created `NOT VALID` (for example `orders_payment_matches_total`): they apply to every new or updated row without inventing history.
- Foreign keys to historical facts use `RESTRICT`. Cascades exist only from an order to its own child rows on a physical delete, which the application never performs.
- Functions called from a check constraint pin `search_path = public` (or qualify every name): `pg_restore` loads data with an empty `search_path`, so an unqualified helper call fails the restore. The floor functions are pinned by `2026_10_20_000000_pin_restaurant_floor_function_search_path.php`; a dump taken after it restores with a plain `pg_restore --exit-on-error`. A dump taken before it (every nightly dump and restore point up to the release that ships it) must be restored by sections into an empty database: `pg_restore --section=pre-data`; `ALTER FUNCTION public.restaurant_obstacles_valid(jsonb, integer, integer) SET search_path = public`; `pg_restore --section=data`; `pg_restore --section=post-data`; then either `RESET search_path` on that function (to stay on the dump's release) or run `migrate`, which pins all three functions.
- Add an index only for a real query; do not duplicate indexes already provided by unique or foreign keys.

## Deletion and restoration

- `orders` and `customers` use soft deletion (`deleted_at`). There is no force-delete route. Restoring reuses the same row, ID, snapshots and revisions.
- Deleting a customer never cascades to orders. Restoring a customer re-checks the active-phone uniqueness and rejects the restore on conflict instead of merging.
- Products, product categories and order add-ons are soft-deleted; historical references remain valid.
- Financial, physical and Marketing history is never deleted; it is reversed, archived (`wallets.archived_at`, Instagram `archived_at`) or disabled (`is_enabled`, `is_active`).
- Management attachments use a tombstone (`deleted_at`, `deleted_by`, `deletion_reason`); the row and the private file are retained.

## Business dates and time zone

- Application timestamps and PostgreSQL storage are UTC (`config/app.php` timezone `UTC`).
- Business calendar dates come from `Orders\Support\OrderBusinessDate` using `ORDERS_TIMEZONE` (default `America/Argentina/Buenos_Aires`). This drives order delivery-date validation, payment confirmation dates, Dashboard periods, Management reporting dates and default dates in inventory adjustment and production forms.
- `delivery_date` and `effective_date` columns are calendar dates, not instants.
- Instagram schedules use their own `INSTAGRAM_AUTOMATION_TIMEZONE` (default `America/Argentina/Cordoba`).

## Settings tables

| Table | Content |
| --- | --- |
| `order_settings` | Closed key set (`Orders\Enums\OrderSettingKey`), exactly one integer or string value per row. Seeds: `empanada_unit_surcharge_percent` 10 (derives stored empanada unit prices), `delivery_fee` 0, `whatsapp_order_phone` empty. Retired rows kept as history and never read: `combined_dozen_cooked_price` 17500, `combined_dozen_raw_price` 16000; `combined_half_dozen_surcharge` 1500 feeds only the inactive derived classes. |
| `order_addons` | Toppings: slug, name, integer price, availability, sort order, soft delete. Seeds: Lactonesa (500), Yasgua (200). |
| `raw_price_discounts` | Positive discounts per tier. Only `dozen` is used: raw dozen = `dozen_price − dozen`; `unit` and `half_dozen` rows are retired history. |
| `instagram_automation_settings` | Operator on/off switch for Story automation. |

These are database records edited through the admin UI, not environment variables. Seed values are safe placeholders: configure the real delivery fee and WhatsApp number before taking orders.

## Migration workflow

```bash
make migrate                                   # pending migrations on the local development DB
make artisan ARGS="migrate:fresh --env=testing" # from-scratch check, testing DB only
```

- Never run `migrate:fresh`, `db:wipe` or `down --volumes` against development or production data. `DB::prohibitDestructiveCommands()` is enabled in production.
- Several migrations refuse a rollback that would lose populated history (POS context, staff profiles, recipes/production, adjustments, consumption, costing, derived pricing). Migrate forward instead.
- Production was migrated from an uncommitted checkout with a different migration history; it moves to this schema through the import in `deploy/cutover/`, not by migrating in place. See [OPERATIONS.md](OPERATIONS.md#legacy-database-cutover).

### Checklist

- [ ] Foreign keys preserve required history (`RESTRICT` toward facts).
- [ ] New business tables have `restaurant_id` with the session-setting default, a foreign key and an index; new unique keys include `restaurant_id`.
- [ ] Lookups and uniqueness rules have indexes.
- [ ] Money is exact integer pesos and computed on the server.
- [ ] Multi-row writes are transactional; aggregate invariants have deferred checks where needed.
- [ ] Existing administrators, products, customers, orders and financial history survive the upgrade.
- [ ] Rollback is safe or explicitly refused when it would lose data.
- [ ] The migration was run from scratch on a testing database and the full suite passed.
