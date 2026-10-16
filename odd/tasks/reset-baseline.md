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
| T3 | S3 | inline (explorer mapped schema) | done | `9660ced`, `a81db4e`, `25aebb4`: RED→GREEN, 737 tests pass, pint/phpstan clean, rollback round-trip exact. Independent verifier: PASS, no blockers. RDD review (risk+reliability, range `694abea..a81db4e`): approved and acknowledged (lineage review-de2cbe66d440dbbd); critical reconnect claim refuted, now pinned by test. |
| T4 | S4 | inline | done | `7319c2e`, `4013b5b`, current HEAD: rehearsal on restored prod copy PASSED (43 tables count+hash, wallet balances, stock, order totals reconciled; second import refused unchanged; broken source rolled back). Independent verifier: no data defects; blockers B1 (report lacked balances/stock) and B2 (no repeatable test) fixed in `4013b5b`, recheck by lead (rehearsal). RDD review (risk+resilience+reliability, `a81db4e..4013b5b`): approved and acknowledged; advisories addressed in follow-up commit. |
| T5 | S5 | inline | pending | |
| T6 | S6 | delegated (bounded writer) | pending | |

## Log

- L1 (2026-10-15, owner): "ahora tú tomas las riendas, vas a dar todas las órdenes y tareas para hacer lo mejor de esto" — Phase 1 authorized with "arranca".
- L2: Owner decisions: production stays on the notebook (D1); light theme with tenant accent `#b84f3f`. Lead decisions: D2 tenant-ready schema now; D3 evidence in PR descriptions, no docs-only evidence commits.
- L3: Found CI could never pass: Pest declared but missing from `composer.lock`; `package.json` deps missing from `package-lock.json`; no Postgres service. Fixed in T2.
- L4: Collision reports ~700 "warnings" while plain PHPUnit reports none; triage later (likely PHP 8.5 vendor deprecations).
- L5: `composer update --lock` reported 7 security advisories in 5 packages; triage in a dependency task (Phase 2).
- L6: S3 scope refined (lead decision): schema-level tenancy now (column + per-tenant uniques, value from PostgreSQL session setting `app.current_restaurant_id`); read isolation via row-level security, per-tenant order numbering, cross-tenant trigger checks and per-tenant seed settings deferred until a second restaurant — ~40 `DB::table` read paths would bypass an Eloquent global scope, RLS covers them without rewrites.
- L7: Native review of the full branch range refused with `lens_context_budget_exceeded` (lock file + P0-A); reviewed the high-risk tenancy range only. P0-A was already reviewed in its own Codex task; lock/CI/style commits are low risk.
- L8: Verifier advisory A1 (blocker for cutover, owned by T4): the deployed public image `release-9a4dac2` shares the production DB and predates tenancy; once prod is migrated its inserts fail NOT NULL. Cutover must redeploy the public app from this line (or a transitional DB-level default).
- L9: Reviewer advisories left open: backfill test on populated tables (covered by T4 ETL reconciliation), `down()` cannot restore global uniques once two restaurants share keys (acceptable, transactional failure).
- L10: Prod data finding: 3 paid orders (2026-08-13/14) without payment_method; `orders_payment_matches_total` is NOT VALID in prod and the import keeps the same exemption. Packaging costing policy in prod is named "Test" (possible test data; imported as-is).
- L11: Open before cutover: trigger re-validation is not automated (one-off replay on 2026-10-16 accepted all rows); README documents it. `/admin/marketing/instagram` returns 500 when the Instagram media base URL env is missing — should degrade gracefully (follow-up).
