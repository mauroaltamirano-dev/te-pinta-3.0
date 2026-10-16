# Te Pinta 3.0

Ordering and back-office application for Te Pinta, an empanada business: a public storefront with WhatsApp checkout, and an authenticated admin/POS for orders, customers, catalog, inventory, finance, marketing and Instagram Story automation. The schema is tenant-ready (`restaurant_id` on every business table); today it serves one restaurant.

## Stack

- PHP 8.3+ (Sail and CI use PHP 8.5), Laravel 13, Fortify (passwords, two-factor, passkeys)
- PostgreSQL 18 (the only supported database, including tests)
- React 19, TypeScript, Inertia 3, Tailwind CSS 4, shadcn/ui primitives, Laravel Wayfinder
- PHPUnit through `php artisan test`, Playwright for browser tests
- Laravel Sail for local development; Docker, Tailscale and a Cloudflare tunnel for deployment

## Quick start

Requires Docker with Compose and GNU Make.

```bash
cp .env.example .env    # set a non-empty DB_PASSWORD
make setup
make admin
make dev
```

Quality gates: `make check`, `make e2e`, `make audit`. Details in [docs/OPERATIONS.md](docs/OPERATIONS.md).

## Documentation

Start at [docs/INDEX.md](docs/INDEX.md). Agents also read [AGENTS.md](AGENTS.md).

There is no online payment: public checkout saves the order and hands the customer a WhatsApp link. The server is authoritative for prices, totals, identifiers and state transitions.
