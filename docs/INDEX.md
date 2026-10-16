# Documentation index

The documents below describe the system as implemented. When code and documentation disagree, the code is the truth; fix the document in the same change.

## Reading order

1. [ARCHITECTURE.md](ARCHITECTURE.md) — modules, dependency rules and known violations, request flows, tenancy, durable decisions.
2. [DATABASE.md](DATABASE.md) — money and identifier conventions, tenancy, constraint philosophy, deletion, business dates, settings, migrations.
3. The domain document for the area you will change.
4. [OPERATIONS.md](OPERATIONS.md) — how to run, test, log and deploy it.

## Documents

| Need | Document |
| --- | --- |
| Module boundaries, dependencies, multi-tenancy | [ARCHITECTURE.md](ARCHITECTURE.md) |
| Tables, constraints, migrations, time zones | [DATABASE.md](DATABASE.md) |
| Order lifecycle, sources, snapshots, payments, pricing engine, derived pricing foundation | [ORDERS_AND_PRICING.md](ORDERS_AND_PRICING.md) |
| Stock, cost layers, valuation, recipes, production, adjustments, order consumption, costing | [INVENTORY.md](INVENTORY.md) |
| Wallets, ledger, outflows, transfers, distributions, order payments, purchases, audit, dashboard KPIs | [FINANCE.md](FINANCE.md) |
| Public site, checkout, featured products, Instagram automation runbook | [STOREFRONT_AND_MARKETING.md](STOREFRONT_AND_MARKETING.md) |
| Local setup, commands, testing, logging, deployment, backups | [OPERATIONS.md](OPERATIONS.md) |
| Authorization, public checkout controls, private data, production checklist | [SECURITY.md](SECURITY.md) |
| Tokens, primitives, shared compositions | [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md) |
| Recorded trade-offs | [decisions/README.md](decisions/README.md) |
| Superseded documents and old task logbooks (not maintained) | [archive/README.md](archive/README.md) |

Active feature logbooks live in `odd/tasks/`.

## Change checklist

1. Identify the owning module and read its document.
2. Keep authorization, validation, prices, totals and state transitions in the backend.
3. Preserve order and financial snapshots; never recompute history from current catalog or settings.
4. Run the focused tests, then `make check` (and `make e2e` for UI changes); report anything not run.
5. Update the affected document when a boundary, table, rule or command changes; add an ADR for a durable trade-off.
