# Administrative operational base reconstruction

Recover the known administrative operational stack as a verified descendant of POS-2 before any global UX normalization.

## Base and boundaries

- Base: `9ec88408b6681ca4f194c239bc4a39add670bd5a` (POS-0 and POS-1 ancestors verified).
- Branch: `feat/admin-operational-base-reconstruction`.
- Worktree: dedicated `admin-operational-base-reconstruction` sibling worktree.
- Dirty checkout and external Recipe/Management handoff are read-only behavioral references, never wholesale implementations.
- No POS-3, global UX redesign, public redesign integration, push, PR, deployment, or other-worktree source writes.
- Preserve integer-peso money, exact NUMERIC quantities, immutable history, PostgreSQL integrity, backend pricing and administrator-only administration.
- Delivery: `auto-chain`, `feature-branch-chain`, local coherent commits only. Forecast: approximately 12,000–20,000 authored lines across seven units; do not omit tests or compress code for a line target.
- Native review remains enabled; every new candidate needs its own applicable review/consent. Historical reviews are not reused.
- Route: `solo`, `delegation_unverifiable`: effective worker role/model/effort not exposed and no authoritative worker UUID available for the documented inspector. No workers exist. Sequential implementation with direct verification.

## Units and acceptance

- [ ] **AOB-1 — Inventory foundation:** categories, classified items and capabilities, immutable units and exact conversions, presentations, immutable transactions/movements/cost layers, balances/history/current valuation and useful functional admin UI. Negative stock remains allowed. Administrator HTTP and PostgreSQL constraints covered.
- [ ] **AOB-2 — Rich purchases:** aggregate/lines, atomic ledger and optional stock receipt, shipping separated, historical snapshots, idempotency/concurrency, coordinated reversal, attachments, list/create/detail under `/admin/management/purchases`.
- [ ] **AOB-3 — Recipes and production:** versioned immutable recipes producing an InventoryItem, explicit expected output, actual/retired pagination, production/reversal and weighted current cost. Required integer-peso example: 2,150 + 14,500 = 16,650; 2 kg output = 8,325/kg.
- [ ] **AOB-4 — Adjustments:** count correction, waste, damage, expiry and manual adjustment; exact preview/create/reversal, positive/unknown valuation and historical allocations. No cashflow mutation.
- [ ] **AOB-5 — Order consumption:** versioned definitions/components, terminal delivery consumption/reversal, immutable historical COGS, known no_inventory zero versus missing configuration unknown, economic-edit lock. Reconcile POS-1 dine-in without round/session consumption.
- [ ] **AOB-6 — Product costing:** policy assignment, packaging and overhead estimates, coverage and current product cost; family-aware backend prices for Empanada versus Unit. No physical packaging deduction or pricing changes.
- [ ] **AOB-7 — Operational Management:** expanded overview/periods, rich/legacy purchase metrics, operating result excluding personal withdrawals, audit/reconciliation and four functional local tabs plus Purchases access.

## Verification policy

Each behavior follows observed RED → GREEN → REFACTOR on a private PostgreSQL database. Adapt historical tests only after inspecting their assertions and dependencies. Run affected domain/action/HTTP/authorization/database/idempotency/reversal checks. Verify migration → behavior → allowed rollback → migration on a separate private migration database. Run affected POS regression whenever Products/Orders/Identity changes; full backend regression and frontend lint/typecheck/format/build at completion. Browser checks demonstrate actual desktop/mobile operation where applicable.

## Progress and evidence

### Preparation

- Canonical worktree created with exact base HEAD and clean status before task-document creation.
- POS-0, POS-1 and POS-2 ancestry verified.
- Original dirty checkout HEAD/status/diff/index/untracked-content fingerprints captured before reconstruction work.
- AOB-1 functionality implemented and verified; candidate closure/review pending. No commits yet at this recorded boundary. Native review: enabled/default; initial exact START preflight refused by context budget, no review authority created. AOB-2…7 have not started.

### AOB-1 planning

- Historical classification migration directly references recipes/consumption tables that do not exist in the AOB-1 foundation. Recover fields/constraints now; add dependent cross-table protections with their owning units rather than import future domains early.
- Recover only foundation dependencies; defer order/production/adjustment audit panels until their units exist.
- Rollback boundary: new Inventory foundation schema/classes/UI, Inventory route inclusion and sidebar entry; existing financial/POS behavior remains unchanged.

### AOB-1 implemented candidate

- Reconstructed foundation only: seven physical tables, seven immutable system units, exact conversion/presentations, explicit item classification/capabilities, immutable movements/positive receipt layers, stock/history/current valuation and administrator-only functional UI.
- Independently authored Inventory index/resources/dialogs; no global PageShell or redesign. Reviewed exact quantity formatter and feature-local field helper retained; future-domain model relationships, resource panels and signed cost compensation intentionally deferred.
- Three new migrations: foundation `2026_09_02_000000`, classification `2026_09_30_000000`, reference mass `2026_09_30_010000`. No integrated POS migration changed.
- Strict TDD observed: 32 backend tests RED (0 passed, 3 failures, 29 errors); implementation GREEN 32 tests / 189 assertions, then normalized/refactored and repeated GREEN. Exact formatter module missing RED, then 2 Node tests GREEN.
- Final full regression: 517 passed / 3,789 assertions in isolated Sail PHP + private PostgreSQL. Earlier full run: 516 passed / 1 Instagram failure; isolated Instagram 6 / 99 passed and final full repeat passed. No unrelated Instagram source/test changes made; initial failure not erased from evidence.
- Browser: 1 scenario × desktop/mobile = 2 passed. Real category/item create/edit and precise presentation configuration, responsive overflow assertion and inspected screenshots. Host Chromium initially unavailable (libgbm); Sail verification runtime supplies dependencies. First actual browser run exposed an ambiguous test label selector; exact selector corrected before final pass.
- Migration boundary: existing POS schema first on private `aob_migration_testing`, apply three AOB migrations, verify seven seeded units, rollback only three, confirm `dining_tables` and `products.pricing_family` survive, reapply. Initial boundary assertion used wrong Restaurant table name; corrected to actual `dining_tables`, then complete cycle passed.
- Checks: full PHP Pint check, PHPStan level 7, TypeScript, resource Prettier, scoped ESLint, Node tests and production build passed. Full ESLint: 239 errors in 110 files, every failing file byte-identical to POS-2; zero candidate files fail. Do not normalize unrelated source to hide baseline errors.
- Canonical Composer/npm manifests and lockfiles unchanged. Inherited lock mismatches required isolated ignored runtime manifests under `storage/app/aob-runtime` and `storage/app/aob-node`; own vendor/node_modules, private database and `.env.testing`. Runtime npm audit: 3 high findings in brace-expansion/js-yaml/source-map-js; dependency repairs are separate scope, not silently applied.
- Original dirty checkout five fingerprints (HEAD/status/diff/index/untracked bytes) match the initial baseline exactly after functional verification.
- Commit/review: pending, no native approval claimed. Running committed authored lines: 0. Rollback removes AOB-1 routes/UI/schema and scoped registrations only; financial/POS implementations untouched.

### AOB-1 H1 concurrency correction (authorized)

- [x] **AOB-1/H1:** close the PostgreSQL stock-movement/parent-measurement race with deterministic two-connection RED → GREEN → REFACTOR, then repeat migration lifecycle and AOB-1/full regression.
- Authorization: user explicitly approved bounded correction and renewed verification after primary self-review reproduced committed movement history on an item concurrently changed to `tracks_stock=false`.
- Route: solo under existing `delegation_unverifiable`; no writing workers. Edit surface: foundation migration's movement validation lock, one new concurrency test file, Inventory contract and this tracker. No future aggregate or UX changes.
- Original candidate: 72 files / 4,912 lines, sha256:58b824f6ac6e0fd215a4a507775ae58f5025d54927561cec397aaa9783a284e1. Exact granted START on Gentle AI 4.0.0 failed in preflight with `lens_context_budget_exceeded`, `mutation_outcome=not_started`; no native authority/reviewer/approval. Same-candidate retry cannot succeed; no blind retry/abandon/disable.
- PostgreSQL gap: movement BEFORE INSERT reads parent without a row lock; the concurrent parent UPDATE sees no committed movements and can invalidate the pending movement. Laravel action locks alone do not prove database invariants.
- Implemented parent `SELECT ... FOR UPDATE` in the new foundation migration only. Deterministic two-PDO tests cover both schedules for stockability, measurement and precision; exact bounded SQLSTATE assertions, no scheduling sleeps.
- Observed RED on the unlocked trigger: 6 failures / 0 passed / 12 assertions. After lock and normalization: 6 passed / 45 assertions; complete Inventory suite 38 passed / 234 assertions.
- Additional runtime proof: six asynchronous direct-SQL schedules observed `pg_stat_activity.wait_event_type=Lock` before releasing the parent; resumed updates rejected with 55000 and stale inserts with 23514. These experiments are not counted as additional PHPUnit tests.
- Test-harness correction: full `DatabaseMigrations` teardown encountered inherited POS rollback constraint duplication (42710). Only the new concurrency harness now uses safety-guarded private-schema reset with committed fixtures; no integrated POS down migration changed.
- Repeated three-migration apply/rollback/reapply on a separate private database, preserving `dining_tables` and `products.pricing_family`; installed trigger lock verified.
- Renewed checks: Pint, PHPStan level 7, TypeScript, scoped ESLint, resource Prettier, Node 2 tests, production build and desktop/mobile browser 2 tests passed. Full global lint remains the previously verified byte-identical POS-2 baseline failure.
- Final full backend regression: 523 passed / 3,834 assertions. Earlier corrected run: 522 passed / 1 ManagementAudit Vite missing-font failure while production build rewrote assets; serialized build before full HTTP suite then passed. No Management behavior patched to hide the harness scheduling failure.
- Structured primary self-review: CLEAR after H1 correction; no remaining HIGH/CRITICAL candidate finding. Reviewed normalized domain/action/request/resource/query/SQL boundaries, authorization/routing and functional UI/test scope. This is self-review, not independent/native approval. Limits: default PostgreSQL READ COMMITTED verified; future aggregate/fulfillment behavior and higher isolation are not claimed. The final staged identity is recorded externally in Engram to avoid changing the candidate after freeze.
- Old consent does not approve corrected bytes. No tooling exception requested or granted, no commit, no AOB-2 yet.

## Next step

H1 is corrected and verified. Resolve the exact corrected AOB-1 review boundary: native review remains missing after the original tooling refusal; any review tooling exception requires explicit candidate-scoped user authorization before closure/commit. Do not start AOB-2 until that boundary is resolved. Stop after AOB-7 for user acceptance; do not start UX normalization automatically.
