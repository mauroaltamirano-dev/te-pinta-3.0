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

- [x] **AOB-1 — Inventory foundation:** categories, classified items and capabilities, immutable units and exact conversions, presentations, immutable transactions/movements/cost layers, balances/history/current valuation and useful functional admin UI. Negative stock remains allowed. Administrator HTTP and PostgreSQL constraints covered.
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
- AOB-1 closed as exact local commit `896546e8400a4c3a7d20ab997154402d8ba28846` under explicitly authorized `review tooling exception`. Native review: enabled/default; initial exact START preflight refused by context budget, no review authority created. AOB-2 preparation starts from that commit; AOB-3…7 remain untouched.

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
- Historical pre-H1 candidate: not committed or approved. Final closure is recorded below; running committed authored lines: 5,106. Rollback removes AOB-1 routes/UI/schema and scoped registrations only; financial/POS implementations untouched.

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
- User granted exact corrected-target exception and local commit on 2026-10-07. Commit `896546e8400a4c3a7d20ab997154402d8ba28846` has tree `25ca285c7a358ab7737317f249f4482e815ad4d0`, exactly target `sha256:2906110ef96468b0a0c1a56e0ee3b682669bfba697cbcd94557a96caab97fdcb`. Clean after commit. Status: `review tooling exception`; no native/independent approval. Original `lens_context_budget_exceeded` preserved. This exception does not transfer to AOB-2.

### AOB-2 plan

- Base: `896546e8400a4c3a7d20ab997154402d8ba28846`; independent work unit. Route: solo under `delegation_unverifiable`, multi-file domain/SQL/HTTP/UI reconstruction; no workers can write this scope.
- Recover reviewed Purchase/PurchaseLine/PurchaseReversal contracts, total/unit pricing, exact unit/presentation normalization, separated shipping, optional stock receipt, immutable snapshots and idempotent key/payload validation.
- Extend Inventory with only exact signed reversal-of-layer compensation; add one analyzed purchase migration without modifying integrated migrations.
- Strengthen PostgreSQL reconciliation: append attempts, orphan sources, null-safe aggregate relationships, exact reversal cardinality and snapshot normalization. Reject standalone financial reversal for rich purchases at action and database boundaries; keep historical legacy purchases untouched.
- Functional list/create/detail/reversal at `/admin/management/purchases`, existing private attachment endpoints. Add only navigation links and the existing ledger-summary/filter integration required by rich purchases, not global UI normalization or expanded AOB-7 metrics.
- Strict TDD: observed failing Purchase contract tests before implementation, then domain/HTTP/DB/reversal checks and deterministic separate-session concurrency verification. Run Inventory/Management/POS regression, private migration up/down/up, formatting/static/frontend/build/browser checks. No tests against development.
- Allowed scope: new Purchase-named files in Management actions/data/enums/models/policies/HTTP/queries; one new purchase migration; scoped Inventory reversal data/model/action; FinancialTransaction enum/reversal guard/resource and existing BuildManagementOverview ledger totals/filter; route/provider registration; purchase pages/feature tests; module docs and this tracker.
- No AOB-3+, future consumption/costing, pricing changes, public/dirty edits or remote operations. No inherited lockfile repair. New candidate requires its own applicable review boundary.

### AOB-2 implemented candidate (not closed)

- Reconstructed Purchase, PurchaseLine and PurchaseReversal with atomic integer-peso ledger effects, optional exact stock receipts/cost layers, separate shipping, immutable snapshots and canonical list/create/detail/reversal routes. Existing private financial attachments are reused. Legacy simple purchases remain in Management history.
- Idempotency uses a normalized payload HMAC and transaction advisory lock; matching retries return the original aggregate and conflicting keys fail. Presentation and item locks have deterministic ID order; reversals lock the aggregate and compensate exact historical money, stock and layers rather than current catalog values.
- One new analyzed migration, `2026_09_03_000000_create_management_purchases.php`: three aggregate tables; exact snapshot/normalization, source/ledger/physical reconciliation, immutable history, coordinated reversal and signed original-linked cost layers. Committed-line append protection uses immutable full PostgreSQL `creation_xid xid8`, compatible with nested savepoints. No integrated migration changed.
- Existing operating-outflow totals and the Purchase movement filter now include rich purchases and reversals alongside legacy outflows. This fixes an omission that otherwise hid the new ledger effects; personal withdrawals and pricing semantics remain unchanged. Expanded periods/metrics/navigation remain deferred to AOB-7.
- Strict TDD: initial contract RED 10 tests / 0 passed (1 failure, 9 missing-domain errors), then GREEN 10 / 62 assertions; expanded functional-page RED exposed missing Inertia pages before UI recovery. Dedicated lock-order RED 1 / 2 assertions, full-transaction-identity RED 1 / 0 assertions and ledger-summary RED 1 / 8 assertions were observed before their corrections. Additional regression assertions do not claim a separate per-assertion RED.
- Final focused Inventory plus Purchase checks: 62 passed / 412 assertions. Inventory remains 38 / 234; Purchase recovery and concurrency are 24 / 178. Three separate-connection tests exercise same-key creation, same-purchase reversal and committed-line append rejection; contention has bounded timeouts and no scheduling sleeps.
- Full backend regression: 547 passed / 4,012 assertions using isolated Sail PHP and private PostgreSQL `aob_testing`; no simultaneous asset build. POS-0/1/2 tests are included. Products, Orders, Identity and their integrated migrations are unchanged.
- Browser: 2 passed, desktop/mobile. Real purchase of 2 kg for 4,300 pesos plus 300 shipping records 4,600 ledger outflow and 2,000 g stock; coordinated reversal returns stock to zero. Physical-history link, confirmation dialog and overflow checks passed.
- Private migration database: apply new migration, rollback only that migration, apply again all passed. POS dining tables, pricing-family schema and seven Inventory units preserved. Rollback is an empty-aggregate boundary; no historical purchase data is deleted to force it.
- Final checks: Pint, PHPStan level 7 (0 errors), TypeScript, scoped ESLint, scoped Prettier and production build passed. Global ESLint still fails: 232 errors in 108 files, all remaining failing files byte-identical to POS-2. Scoped normalization removed seven pre-existing errors; unrelated failures were not hidden or modified. Canonical Composer/npm manifests and lockfiles unchanged.
- Earlier implementation/harness failures retained in logs: invalid provisional SQL expressions, duplicate functions after private table-only reset, concurrency fixture preparation and 11 scoped lint errors. They were corrected and applicable checks repeated, not waived. Existing isolated-runtime dependency audit findings remain separate follow-ups.
- Historical pre-native self-review: initially CLEAR after the bounded lock-order, full-XID history and ledger-summary corrections; superseded by the H1 finding below. Reviewed domain/actions, PostgreSQL boundaries, authorization, reversal/cardinality, snapshots, routes/resources, functional UI, tests and normalized diff scope. This is not native or independent review approval. Limits: default PostgreSQL READ COMMITTED and the tested schedules; future domains, higher isolation and future fulfillment are not claimed.
- Original dirty checkout HEAD/status/diff/index/untracked-content fingerprints match the initial baseline exactly after final checks. No public/POS/dirty worktree writes and no remote operations.
- Review mode remains on (default), Gentle AI 4.0.0. AOB-2 has no transferred exception or approval; candidate-specific native preflight/consent and local commit remain pending. Keep AOB-2 unchecked until closure.
- Engram mirror update is pending: the runtime resume hook reports registration unconfirmed and forbids agent-attributed memory writes. Preserve this local recovery document; do not invent an identity or write under another session.

### AOB-2 H1 UUID identity correction (authorized)

- [x] **AOB-2/H1 functional correction:** canonical UUID identity implemented and verified across advisory lock, lookup, Purchase identity and physical source. Real-commit uppercase HTTP/idempotent retries and separate-connection case-order tests observed RED → GREEN → REFACTOR. New candidate review and AOB-2 commit are separate pending closure steps.
- User explicitly authorized this bounded correction after H1 was reported. Route: solo under existing `delegation_unverifiable`; no workers exist. Do not change pricing, schema, financial amounts or physical semantics.
- Edit surfaces: `app/Modules/Management/Actions/CreatePurchase.php`, `tests/Feature/Management/PurchaseRecoveryConcurrencyTest.php`, `docs/PURCHASES.md` and this tracker. No new migrations or unrelated files.
- Superseded candidate: `sha256:7beae100c2c2fc88dfb8849a90ef85df82990b2f59b50a5b827cf7699216f5d2`, tree `66367a0a517033c067d5a097f8af65d781ccbf73`, 42 files / 2,891 authored lines. User granted native review; Gentle AI 4.0.0 rejected exact START in preflight with `lens_context_budget_exceeded`, `mutation_outcome=not_started`, no authority created. No native/independent approval, exception or AOB-2 commit. No blind retry, abandon or mode change.
- Primary self-review became NOT CLEAR: HIGH H1. Uppercase UUID passes HTTP validation, but a stockable purchase fails PostgreSQL 23514 because raw Inventory source text differs from canonical UUID text. Private DB control accepted lowercase and rejected uppercase; probe fully rolled back. The same database UUID also produced different advisory lock hashes for case variants. The old tests did not cover this edge; their passing counts are not waived or treated as proof of H1 correctness.
- Observed H1 RED: 3 failed / 7 assertions, including uppercase HTTP commit rejected 23514 and both case orders timing out on the wallet query instead of the UUID advisory lock. Minimal action correction canonicalizes the validated UUID once and reuses it for lock, lookup, physical source, persisted identity and failure log. Initial GREEN: 3 passed / 39 assertions, real committed HTTP creation plus uppercase/lowercase retries, and separate-connection advisory contention/retry in both case orders. Migration and business rules unchanged. Source normalization completed before final checks; no post-freeze mutation is permitted.
- Renewed focused checks: 65 passed / 451 assertions (Inventory 38 / 234; Purchases 27 / 217, including the three new H1 cases). Full backend: 550 passed / 4,051 assertions; POS-0/1/2 regressions included. No overlapping asset build during HTTP tests.
- Renewed Pint, PHPStan level 7 (0 errors), TypeScript, scoped ESLint/Prettier, Node formatter 2 tests and production build passed. Browser 2 desktop/mobile tests passed. No frontend source changed for H1; the previously measured global lint failure remains 232 inherited errors in 108 POS-2-identical files.
- Repeated private migration cycle: empty-aggregate boundary verified, existing new Purchase migration rolled back and reapplied successfully; seven units, dining tables and Product pricing-family schema survive. H1 introduces zero migrations or schema changes. Original dirty checkout all five fingerprints match baseline exactly after renewed checks.
- Final primary structured self-review after H1: CLEAR. Verified canonical identity is used after UUID validation at every locking, lookup and physical/persisted/log identity use; no fingerprint, quantities, money, authorization, historical snapshots or pricing semantics changed. Both case orders contend specifically on the advisory SQL, not the wallet query, and replay one committed aggregate. Limit: bounded timeout followed by retry is proven; a continuously blocked process-resume schedule is not claimed. This is primary self-review, not independent/native approval. Final candidate binding and its own review/closure remain pending.
- H1 source delta is restricted to the four authorized paths; earlier native failure and withdrawn CLEAR remain historical evidence. AOB-2 remains unchecked and uncommitted. No exception transfers from AOB-1 or the superseded AOB-2 candidate.
- Review evidence is retained separately from frozen source in `/tmp/aob2-review-evidence.json`. Do not reuse the old candidate's consent or approval for changed bytes. Engram mirror remains pending until runtime registration is confirmed.

## Next step

H1 functional correction is verified. Freeze the updated AOB-2-only candidate and follow its new candidate-specific review boundary; old consent is not reused. No AOB-2 commit without applicable closure; no AOB-3+, UX normalization or POS-3.
