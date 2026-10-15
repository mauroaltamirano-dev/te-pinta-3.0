# Reset baseline (Phase 1)

Objective: one clean integration line that is tenant-ready, CI-verified and deployable over the current production data.
Branch: `reset/baseline` (from `b3cd9da` + P0-A). Delivery: `auto-chain`, stacked to `main`. Test runner: PHPUnit on PostgreSQL 18, Playwright for e2e.
Audit: `~/tepinta-handoffs/global-architecture-audit-2026-10/`.

## Specs

- S1 — Baseline = `b3cd9da` + P0-A (`5c5dee9`), nothing else unreviewed.
- S2 — Clean installs and CI must pass: lock files in sync with manifests; backend suite runs on PostgreSQL in CI.
- S3 — Tenant-ready schema (decision D2): `restaurants` table; NOT NULL `restaurant_id` on every business table; per-tenant unique keys, settings and sequences; existing single-restaurant behavior unchanged (all data belongs to restaurant 1).
- S4 — Data migration: an idempotent, tested procedure that moves the current production database (dirty-checkout migration history) onto this schema, verified on a restored copy with a reconciliation report (row counts, ledger balances, stock).
- S5 — Port unique work from `archive/2026-10-15/dirty-checkout`: economic performance, inventory reconciliation, order consumption preview, `BusinessDate`.
- S6 — Docs reset (decision D3): ~9 current docs + ADRs; stale claims fixed; old `odd/tasks` logbooks archived.

## Tasks

| ID | Specs | Route | Status | Evidence |
|---|---|---|---|---|
| T1 | S1 | inline | done | `03eb3db` (cherry-pick, no conflicts) |
| T2 | S2 | inline | done | `db3eda9` lock sync, `f322db3` eslint autofix, `694abea` CI Postgres; local: 730 tests pass, pint/phpstan/eslint/prettier/tsc clean. CI run not yet observed (not pushed). |
| T3 | S3 | inline (explorer mapped schema) | in progress | |
| T4 | S4 | inline | pending | |
| T5 | S5 | inline | pending | |
| T6 | S6 | delegated (bounded writer) | pending | |

## Log

- L1 (2026-10-15, owner): "ahora tú tomas las riendas, vas a dar todas las órdenes y tareas para hacer lo mejor de esto" — Phase 1 authorized with "arranca".
- L2: Owner decisions: production stays on the notebook (D1); light theme with tenant accent `#b84f3f`. Lead decisions: D2 tenant-ready schema now; D3 evidence in PR descriptions, no docs-only evidence commits.
- L3: Found CI could never pass: Pest declared but missing from `composer.lock`; `package.json` deps missing from `package-lock.json`; no Postgres service. Fixed in T2.
- L4: Collision reports ~700 "warnings" while plain PHPUnit reports none; triage later (likely PHP 8.5 vendor deprecations).
- L5: `composer update --lock` reported 7 security advisories in 5 packages; triage in a dependency task (Phase 2).
