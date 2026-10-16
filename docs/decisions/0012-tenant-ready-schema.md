# 0012: Tenant-ready schema with a session-scoped restaurant

Status: accepted
Date: 2026-10-16

## Context

Te Pinta runs one restaurant today, but the product is expected to serve more than one. Retrofitting tenancy after data from several restaurants exists is far more expensive than adding the column now. Roughly 40 read paths use `DB::table` query-builder calls, so an Eloquent global scope alone would not isolate tenants: those reads would bypass it.

## Decision

Make the schema tenant-ready now, and defer read isolation.

- A `restaurants` table exists; restaurant `1` ("Te Pinta", slug `te-pinta`) owns all existing data.
- Every business table has `restaurant_id bigint not null`, a restricting foreign key to `restaurants` and an index. Framework tables, `users` (future membership-based ownership) and `inventory_units` (system units) are excluded.
- The column default is the PostgreSQL session setting `app.current_restaurant_id`. `App\Core\Tenancy\RestaurantContext` sets it on every connection (including reconnections) from `config('tenancy.default_restaurant_id')`. Existing write paths keep working unchanged; a write without context fails with a NOT NULL violation.
- Business unique keys (slugs, names, setting keys, active phone, single default wallet, single active distribution rule, featured-product window exclusion) are unique per restaurant.

Source: `database/migrations/2026_10_16_000000_prepare_restaurant_tenancy.php`, `app/Core/Tenancy/RestaurantContext.php`, `config/tenancy.php`.

## Consequences

- Single-restaurant behavior is unchanged.
- Manual PDO connections (concurrency tests, scripts) must run `SET app.current_restaurant_id = '<id>'`. A transaction-mode connection pooler cannot be used unless the setting is applied per transaction.
- Deferred until a second restaurant exists: read isolation through PostgreSQL row-level security (it also covers `DB::table` reads without rewrites), per-restaurant order numbering (`order_public_id_seq` is global), same-restaurant checks in cross-table triggers, per-restaurant seed settings (order settings, add-ons, outflow categories, Instagram) and per-request tenant resolution.
- `down()` cannot restore global unique keys once two restaurants share a key; the rollback then fails transactionally.

## Alternatives considered

- Eloquent global scope for isolation: rejected because `DB::table` reads bypass it.
- Separate database per restaurant: rejected for now; it multiplies migrations, backups and deployment for a single-node operation.
- Postpone tenancy entirely: rejected; backfilling `restaurant_id` across ~60 tables with multi-tenant data later is riskier than doing it while there is one tenant.
