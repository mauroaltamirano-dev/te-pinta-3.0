# Tables floor plan

Objective: show the restaurant floor plan on `/admin/tables` so waiters identify tables (presentation only). Branch `feat/tables-floor-plan` from `main` at `153fafe`, worktree `~/workspaces/tepinta3.0-worktrees/tables-floor-plan`. Delivery: local branch only; no push, merge or deploy without the user's OK. Test runners: PHPUnit in the `sail-8.5/app` image against a private Postgres (`tepinta-tables-floor-pg`, 127.0.0.1:55497, config in `../.tables-floor-test.env`), Playwright (chromium, mobile-chromium) on database `testing_playwright_tables_floor`, port 8091.

## Specs

- S1 — "en mesas, no debería mostrarse el plano? la idea del plano es que sea más fácil para el mozo identificar la mesa". `/admin/tables` shows the existing floor plan: areas, and each table's position, shape, size and rotation from the floor editor (obstacles as orientation).
- S2 — Each table on the plan shows its state: free/occupied, waiter, provisional total and "pide la cuenta". Tapping a table opens it as today (`/admin/tables/{id}`).
- S3 — The plan is the default view, with a button to switch to the current list.
- S4 — On a phone: one tab per area, and the plan scaled to the screen width.
- S5 — Presentation only: no migrations, no rule changes; geometry exposed to the read model is read-only.
- S6 — Tests: private PostgreSQL test container (never `testing_playwright` shared or dev/prod), e2e specs on desktop and mobile, before/after screenshots in the scratchpad, full PHPUnit suite in the `sail-8.5/app` image.

## Tasks

- T1 — S1, S2, S5: read-only floor geometry for the board (`ServiceTables::floor`, `TableServiceBoard`, controller) with a PHPUnit test. Route: inline. Status: done (`d5de6de`).
- T2 — S1–S4: plan view on `/admin/tables` (default), list toggle, area tabs on mobile, e2e desktop/mobile, docs. Route: inline. Status: done (`d3a7198`).

## Log

- L1 (2026-10-23) — User request: "en mesas, no debería mostrarse el plano? la idea del plano es que sea más fácil para el mozo identificar la mesa". Scope from the coordinating chat: show on `/admin/tables` the existing plan (areas, position, shape and size of each table from the floor editor) with each table's state (libre/ocupada, mozo, total provisorio, "pide la cuenta"); tapping a table opens it as today. Plan is the default view with a button to switch to the current list; on the phone one tab per area and the plan scaled to the width. Presentation only: no migrations or rule changes; read-only geometry in the read model.
- L2 — Route inline for both tasks: no Writer trigger (one behavior, small units).
- L3 — Before screenshots (chromium, mobile-chromium) with a throwaway spec, not committed: two areas (Salón with a "Barra" obstacle, Vereda), free, own, bill-requested and inactive tables. Before: card grid only.
- L4 — T1 RED: `test_the_board_draws_the_floor_of_the_listed_tables_without_changing_it` failed with "Property [areas] does not exist." GREEN after `ServiceTables::floor()` (areas of the listed tables only, ordered by id; obstacles limited to the editor's keys; coordinates as stored strings) and the `areas` prop; `TableServiceHttpTest` 11/11. The test also pins that reading the board leaves `layout_version`/`updated_at` unchanged and keeps a deactivated area while its table has an open session.
- L5 — T2: plan view is the default (`Plano`/`Lista` toggle, `aria-pressed`); tables are links named `Mesa <code>: <state>[, <waiter or Tu mesa>, <total>]`, at least 44 px and upright when rotated; detail shows by drawn width (container queries). After the first screenshots, small tables showed only their code, so each area gets a chip row with the open tables' waiter, total and "Pide la cuenta" (S2 on phones). Mobile: one tab per area, plan at full width, no horizontal scroll. The card list moved verbatim to `tables-list.tsx`; state labels/tones shared in `table-status.ts`.
- L6 — Checks: ESLint, Prettier, tsc, Pint, PHPStan (two list-shape type errors fixed by tightening types, not suppressing), build: clean; `node --test tests/frontend/*.test.mjs` 30/30. PHPUnit full suite in `sail-8.5/app` against the private Postgres: 1018 passed (8207 assertions); a first run without a `.env` file passed with 969 `file_get_contents(.env)` warnings, rerun with an empty git-ignored `.env` in the worktree (left in place: the agent's permissions deny touching `.env` files). New e2e spec 4/4 and `table-service.spec.ts` 5/5 (mobile) green; the new spec was not run against the base commit (no plan or toggle exists there, so RED is by construction). Full Playwright: 229 passed, 52 skipped, 17 failed — the same 17 environment-only failures as the previous feature (`InstagramPlaywrightFixture` via `php artisan tinker`, `ManagementOverviewSeeder` via `php artisan db:seed`: "GD extension is not installed" on the host PHP, reproduced directly). After screenshots in the session scratchpad. Native review: not run. Risk: none.
