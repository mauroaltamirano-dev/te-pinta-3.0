# POS-0 — Restaurant Catalog & Pricing Foundation

## Objective
Separate catalog organization from authoritative pricing behavior without changing historical orders or existing empanada prices.

## Authorized scope
Explicit empanada/unit pricing family, minimal admin configuration, mixed backend quotes, compatible raw/cooked eligibility, conservative structural migration, known seeds, tests and responsible documentation. Excludes all POS-1 features and public visual redesign. No remote operations, branch incorporation, push, PR or deploy.

## Constraints
Strict pricing RED → GREEN → REFACTOR. Exclusive PostgreSQL instance for tests; never development migrate:fresh or shared testing concurrency. Preserve snapshots and existing final rounding. No category-name or ID heuristics. New Product admin input explicitly chooses family. Preserve legacy behavior for structurally valid three-tier products, with documented explicit review for products intended as unit. No commits before implementation, checks and normal review (user override of per-task ODD commits).

## Tasks
- [x] P0-1: Isolated test runtime and observed RED for family/mixed/raw cases. (Dedicated `tepinta-pos0-pg` on private `tepinta_pos0_net`; intended RED asserted raw 12-unit product at 7000 instead of required 12000 before the unit branch.)
- [x] P0-2: Product family, structural backfill and PostgreSQL/application invariants; minimal admin/resource/type compatibility. (Migration rollback/reapply on the private DB backfilled a valid legacy three-tier record to `empanada`; PostgreSQL check constraint, automated migration backfill, invalid/null family rejection, and admin/seed/resource contracts covered.)
- [x] P0-3: Partition authoritative pricing; unchanged empanada grouping/rounding, unit-only calculation and raw isolation; historical/readback/checkout tests. (Focused Products and Orders suites verify Pizza/Beverage/Dessert unit pricing at 6/12 in cooked/raw modes, the requested 6 Criolla + 6 JyQ + 2 beverage + 1 pizza + 2 dessert mixed quote, underfilled mixed rejection, raw isolation, checkout requoting, and snapshot readback preservation.)
- [x] P0-4: Focused and full regression, lint/format/type/build, responsible docs, independent review, and native-review disposition before the local commit. Native review is omitted only for this candidate under the explicit tooling exception below; it is not recorded as approved.

## Routing
Delegated direct: one Terra/High worker owns reading, implementation and verification; trigger is multi-file logic and strict integration TDD. Fresh Sol/High independent review after verification. Parent controls architecture, scope and acceptance.

## Acceptance
All 18 user-required behaviors covered with observed evidence, DB migration checks, backend requoting, no historical reinterpretation and no public redesign. Report exact counts and unavailable checks. READY FOR POS-1 only if all blockers resolved; do not implement POS-1.

## Checks
Use private PostgreSQL container/instance. Focused Products, OrderPricingEngineTest, Orders checkout, Public checkout and directly affected tests, then project lint/format/types/build/full suites where feasible. RED must fail for intended assertions rather than missing environment/schema only. Source normalization before candidate freeze.

## Delivery
Forecast 420–520 authored changed lines, heuristic only; the complete implementation exceeds that estimate because it includes integration safeguards and regression coverage. Delivery strategy: `exception-ok`, with the user's explicit authorization to create one local POS-0 work-unit commit after verification and the candidate-only native tooling exception. No chain, PR, publishing, or integration action is authorized. Base: `a67a392`. RDD remains on (default).

The work-unit boundary is the commit containing this document, retrievable with `git log -1 --format='%H %s' -- odd/tasks/pos-0-pricing-foundation.md`. Its final identity is recorded in the Engram recovery mirror and final handoff, avoiding a self-referential commit hash in the committed file. Rollback boundary: this complete POS-0 work unit, including its migration, tests, contracts, and documentation; no public-redesign commits are included.

## Progress and next step
Implementation and verification are complete on the dedicated private PostgreSQL container. Independent-review corrections hide empanada-tier controls for unit products and render historical/dashboard aggregates as generic product units while retaining the stable `empanadas_count` API alias. Browser seeding includes a test-only active default wallet because base Quick Order already required one but its browser seed omitted it. The final fresh independent review returned `ship`, with no required findings. The user authorized the local commit and the candidate-only native-review exception below. No functional blocker remains for the POS-0 foundation; POS-1 is not implemented.

## Observed verification

- Final focused coverage: Products 63 passed / 398 assertions; pricing-family cases 14 passed / 71 assertions; Order Pricing 25 passed / 63 assertions; Orders 66 passed / 633 assertions; checkout 19 passed / 203 assertions; Settings 9 passed / 68 assertions; Marketing 11 passed / 262 assertions; Dashboard 10 passed / 180 assertions. These focused counts overlap with the final full backend suite and must not be added together.
- Intended RED: `ProductPricingFamilyTest` failed because legacy global grouping priced a raw 12-unit product at `7000` instead of `12000`; after the pricing branch, its first GREEN passed with 4 assertions and the later focused family suite passed with 9 assertions.
- Correction RED: `OrderCheckoutHttpTest` expected generic `Productos` labels while the unchanged formatter still returned `Empanadas`; 3 failed, 16 passed, 188 assertions before the display correction.
- Correction GREEN: `OrderCheckoutHttpTest`: 19 passed, 203 assertions; `OperationalDashboardTest`: 10 passed, 180 assertions; both ran with `--env=testing` against the private PostgreSQL container.
- Browser correction GREEN: after private database reset per spec, `admin-quick-order`: 4 passed, 4 skipped; dashboard: 10 passed; orders: 17 passed, 5 skipped; public menu: 6 passed. Aggregate: 37 passed, 9 skipped. Skips are intentional viewport coverage: four Quick Order Chromium-only cases, one desktop-only orders navigation case, one desktop-state detail case, and three desktop-only edit cases.
- Browser causality: detached baseline `a67a392` on separate `testing_pos0_baseline_browser` reproduced the Quick Order payment failure because its existing `PlaywrightDatabaseSeeder` had no wallet while the existing base payment action requires an active default wallet. The candidate fixture adds that test-only default wallet. Candidate dashboard passes standalone (10 passed); the one-command combined run has an order-dependent dashboard expectation failure because Quick Order first creates 19 Salteña units and the dashboard then correctly renders 31 rather than its seed-only assertion of 12. No dashboard layout change was made.
- Full backend rerun after the browser fixture: 357 passed, 3181 assertions; 7 failures were observed under a runner with `SESSION_DRIVER=database`: `CustomerManagementTest::test_list_read_model_query_count_does_not_grow_per_customer` expected 5 queries, received 6; `ManagementAuditTest::test_filters_are_bounded` expected 9, received 10; `ManagementOutflowTest::test_outflow_history_query_count_is_bounded_and_logs_exclude_semantic_text` expected 20, received 21; `ManagementTest::test_management_read_model_query_count_does_not_grow_per_row` expected 19, received 20; `ManagementTransferTest::test_transfers_have_no_destructive_endpoint_and_history_queries_remain_bounded` expected 21, received 22; `OrderCardReadModelTest::test_orders_card_read_model_query_count_does_not_grow_per_order` expected 12, received 13; and `ProductManagementTest::test_database_tag_slug_conflict_after_validation_is_translated_to_a_field_error` errored with `Call to a member function getBag() on array`.
- Final parent full proof reran with the correct private PHP configuration: `APP_ENV=testing`, `APP_DEBUG=false`, `DB_DATABASE=testing_pos0`, `DB_URL=''`, `CACHE_STORE=array`, and `SESSION_DRIVER=array`; it passed 364 tests and 3202 assertions in 34.89 seconds. The earlier failure is not reproducible under that configuration; its cause is not asserted. Focused Orders/Dashboard contracts and browser specs above pass.
- Final dashboard wording RED/GREEN: the new generic distribution assertions first failed in both browser profiles (4 failed, 6 passed) against `por empanadas` / `No hay empanadas para distribuir.`; after the two neutral label replacements, `tests/e2e/dashboard.spec.ts` passed 10 tests in 31.2 seconds on the private browser database. `npm run lint:check`, `npm run format:check`, `npm run types:check`, `npm run build`, and `git diff --check` passed.
- Final fresh review found no code blockers. A mechanical documentation-only correction changed the three remaining empanada-only aggregate statements in `CURRENT_STATE.md` and `MODULES.md` to product-unit wording. A subsequent fresh documentation-closure review returned `ship`, with no required findings; native review was subsequently omitted by the explicit candidate-only exception below.


## Next step

Preserve the local POS-0 commit and clean worktree. Start POS-1 only after a new explicit instruction. Do not push, open a PR, merge, deploy, or integrate the frozen public redesign.

## Native review tooling exception

- Disposition: native review **omitted for this current POS-0 candidate only**, by explicit user authorization on 2026-10-05. This is not a native approval, receipt, or authority acknowledgement.
- Tool: Gentle AI `4.0.0`.
- Failed action: negotiated `review.status` selection submission using `--intended-untracked-selection`.
- Error: `invalid_request`; required input contract: `gentle-ai.review-intended-untracked-selection/v1`.
- Mutation evidence: `mutation_outcome: not_started`; `authority_applicability: not_evaluated`. No review START, lineage, or native approval was created.
- Documentation consulted: `gentle-ai --help`, `gentle-ai review --help`, `gentle-ai review schema --help`, and `gentle-ai review status --help`. The local schema command exposes reviewer/refuter/validator/capture-result-dry-run schemas but not the required selection schema; status help names the bound JSON without documenting its exact structure. No further capture was attempted after the local-help-only investigation.
- Accepted verification: 364 backend tests / 3202 assertions; 37 E2E passed / 9 intentional viewport/state skips; PHP lint, PHPStan, ESLint, Prettier, TypeScript, build, and diff checks passed. Tests are not repeated for this documentation-only closure.
- Independent review: fresh Sol review returned `ship`, with no required findings; the implementation bytes remain those previously verified and reviewed.
- Mode: native review remains enabled normally for future candidates; no global or clone-local switch was changed.
- Authorized new paths, unchanged:
  - `app/Modules/Products/Enums/ProductPricingFamily.php`
  - `database/migrations/2026_10_04_000000_add_product_pricing_family.php`
  - `odd/tasks/pos-0-pricing-foundation.md`
  - `tests/Feature/Products/ProductPricingFamilyTest.php`
- Scope: isolated `feat/pos-0-pricing-foundation`, with no other branches incorporated and no modifications to the public-redesign worktree.
