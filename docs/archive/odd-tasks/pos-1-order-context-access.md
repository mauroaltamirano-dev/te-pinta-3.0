# POS-1 — Order Context & Access

## Objective and boundaries

Prepare Orders for immediate dine-in without a Customer or artificial scheduling, independently of creation source. Preserve public checkout, admin workflows, pricing snapshots and financial/inventory semantics. Prepare fixed backend staff capabilities without creating POS screens or routes.

Base: `027f111`. Branch: `feat/pos-1-order-context-access`.
User explicitly authorized solo execution for this slice only. No workers, push, PR, deployment, public-redesign integration, Quick Order recovery, tables, sessions, tickets or printing.

## Plan

- [x] P1 — Add source/fulfillment context, nullable dine-in data, structural PostgreSQL invariants, null-safe existing read/edit contracts and regression tests.
- [x] P2 — Add fixed waiter/cashier capabilities, preserve administrator boundaries, document and verify the complete slice.

Route for both tasks: inline, explicit user exception to mandatory delegation and preparation triggers. Strict TDD: observed new deterministic failures before production changes, GREEN then normalization/checks. Tests run sequentially in the dedicated `tepinta-pos1-pg` container/database `testing_pos1`; no development database operations.

Delivery strategy: single local feature branch, no PR authorized. Forecast: 900–1300 authored lines including tests/docs; coherent behavior takes priority over the advisory 400-line heuristic. Each work unit keeps tests and responsible docs with code. Native review remains enabled. It was attempted and blocked by tooling; the user explicitly authorizes a POS-1-only closure exception with structured primary-session self-review. Do not invent a selection schema or external verdict.

## Acceptance and checks

- Source `admin`, `public_checkout`, `pos` is persisted, never inferred. Fulfillment is `delivery`, `pickup`, `dine_in`; no public dine-in.
- POS dine-in can have null customer/name/phone/schedule, rejects address, shipping zero. Existing admin name requirement/optional phone, delivery address and scheduling remain unchanged.
- Public name/phone/delivery address remain required, pickup address optional, no injected POS source.
- Legacy rows and item/financial snapshots remain unchanged through migration; down refuses lossy rollback.
- Existing serializers/admin display/edit contracts tolerate null without fabricated names or dates and without per-order customer queries.
- Staff profiles are fixed, non-mass-assignable; legacy admins retain all access. Backend capabilities cannot grant waiter/cashier sensitive admin/financial access.
- Run Orders, Products/POS-0, Identity/auth, full backend and reasonable frontend lint/format/types/build. No parallel suites over the private DB.

## Evidence and progress

- Branch and clean baseline verified.
- Local delegation exception explicitly authorized by user. Later, a separate explicit POS-1-only closure exception permits self-review fallback for the two documented tooling blockers; future reviews and delegation remain enabled.
- Read-only exploration: public request is independent; admin currently requires name but allows null phone; customer/date/period schema are mandatory; shipping engine already charges delivery only.
- Private PostgreSQL 18 test container created; local ignored dependency copies prepared.
- Initial RED: private runner `artisan test --compact tests/Feature/Orders/OrderContextTest.php tests/Feature/Identity/StaffAccessTest.php`: 22 failed, 11 warnings, 60 assertions on unchanged production. Failures include missing dine-in/pos enums, nullable DTO, fixed capabilities/schema, accepted source spoofing and absent migrations. Baseline testing guard: 14 passed / 19 assertions. Followed by the GREEN evidence below.
- Domain and access implementation completed together: POS creation needs the fixed actor capability, so P1/P2 form one coherent review/commit candidate. Both task outcomes and checks are observed; the user authorizes one coherent local closure commit under the documented exception.
- Additional observed RED: customerless contact audit (1 failure / 8 assertions), snapshot-only duplicate phone (1 failure / 5 assertions), legacy dashboard distribution (1 failure in initial full regression), public-only idempotency namespace (1 failure / 1 assertion). Each was followed by GREEN without changing historical expected contracts.
- Final focused context/access: 42 tests / 180 assertions. Final full `vendor/bin/phpunit --display-warnings`: 406 tests / 3382 assertions, no warnings or failures. Includes Orders, public checkout, POS-0 pricing, Identity and Management payment integration. An Artisan compact invocation reported 392 runner warnings; direct PHPUnit verification is the final record. An earlier invalid exploratory run overlapped diagnostic DB activity; final backend runs did not overlap another suite on their database; browser suites ran sequentially on their separate private database.
- Browser: new null-customer admin contract 2 passed; existing Orders 17 passed / 5 existing project-specific skips; public checkout 18 passed / 2 failed on the old `Empanadas cocinadas` expectation. Base `027f111` already renders `Productos cocinados` in the unchanged PriceSummary; test and public surfaces were not changed. This pre-existing text mismatch is not fixed in the frozen public slice.
- Final checks: Pint check-only 391 files; PHPStan level 7; ESLint; Prettier resources; TypeScript noEmit; Vite production build; git diff --check, all passed.
- Browser setup failures (missing libgbm, leaked private server occupying port) resolved only in isolated runtime. No development migrations or production dependency changes. Separate private browser database: testing_pos1_browser.
- Inventory has no executable Order-dependent behavior/tests on this base; delivered/consumption remain untouched. Unrelated browser suites and deployment/LAN/physical devices were not run.
- Conservative up/down migrations tested with exact old rows/users and snapshots; no arbitrary backfill. Down refuses existing POS contexts or assigned staff profiles.
- Known limits: no production POS entrypoint/staff screens/provisioning UI; non-dine-in real Customer and existing scheduling retained; scheduled daily dashboard excludes immediate dine-in; financial reversal and status transition redesign deferred. No POS-2 artifacts.
- Candidate size before this evidence update: 1227 authored lines (579 tracked diff + 648 new file lines), excluding generated/ignored runtime artifacts. No PR requested or created.
- Native review: mode on by default (global/clone unset). Initial assessment reports high/unassessable because seven new paths require explicit inventory selection; canonical preflight returned collect/intended_untracked_selection_required with external.select_intended_untracked and schema_bound_json gentle-ai.review-intended-untracked-selection/v1. No exact START offered. Installed Gentle AI 4.0.0 help describes provider-bound JSON but does not define its fields; no documented capture command is available in the returned transition. Stopped without synthesizing JSON, staging, freezing authority or starting reviewers. No review exception inherited from POS-0; no schema guesses or staging workaround.
- Commit evidence: the local `feat(orders): prepare restaurant context and fixed staff access` work-unit commit containing this task; its exact identity is recorded in the final handoff and Engram after creation. Base remains 027f111; no push/PR/merge/deploy.

## Structured self-review and candidate-only closure exception

Result: **CLEAR**, performed by the primary session on the actual current diff without code edits. This is self-review, not independent review. No native approval, external SHIP, reviewer receipt or POS-0 authority is claimed.

Native blocker: Gentle AI 4.0.0, horizon `partial`, `collect`, `intended_untracked_selection_required`, no START; required provider-bound selection schema/producer unavailable. Independent reviewer blocker: runtime native UUID/model/effort cannot be verified; the local protocol forbids accepting that verdict. The user explicitly authorizes closure ONLY for POS-1 despite both blockers; review mode, delegation and future reviews remain unchanged.

| Check | Self-review evidence / result |
| --- | --- |
| 1. Nullable Customer does not weaken public checkout | CLEAR: structural null Customer limited to POS dine-in; public request and domain validation retained. |
| 2. Public name/phone required | CLEAR: StorePublicOrderRequest unchanged required PII and context regressions. |
| 3. Public delivery address required | CLEAR: required_if delivery and domain required address. |
| 4. No public source=pos injection | CLEAR: Store/Preview source prohibited; trusted creation source. |
| 5. Source independent of fulfillment | CLEAR: distinct enums/columns, persisted source unchanged on edit. |
| 6. Real dine-in absence of Customer/PII | CLEAR: nullable snapshots/link; resolver returns null without fake Customer. |
| 7. No incompatible dine-in address/scheduling | CLEAR: prohibited app fields; DB requires null address/date/period/time. |
| 8. No accidental dine-in/pickup shipping | CLEAR: existing delivery-only pricing plus zero-shipping CHECKs. |
| 9. Historical Orders preserved | CLEAR: schema-only migration; exact row/item/customer up/down tests. |
| 10. No silent lossy POS rollback | CLEAR: down refuses pos/dine_in/null fields before structural changes. |
| 11. Staff profile cannot elevate privileges | CLEAR: fixed enum/CHECK, not mass-assignable, independent is_admin authority. |
| 12. Waiter denied Management/sensitive configuration | CLEAR: admin routes/policies not expanded; backend access tests. |
| 13. Cashier not administrator | CLEAR: cashier capability is narrower; isAdministrator uses is_admin only. |
| 14. Existing admins compatible | CLEAR: no privilege backfill; legacy admins retain all Gates/access. |
| 15. Backend authorization | CLEAR: POS actor capability/paid-staff denial plus Gates, existing admin boundary. |
| 16. Null-safe serializers | CLEAR: nullable Customer/date resources, snapshots without Customer queries; existing UI guards. |
| 17. Obvious impossible PostgreSQL contexts | CLEAR: valid source/fulfillment/profile, non-dine-in required historic fields, POS-only immediate dine-in. |
| 18. POS-0 pricing intact | CLEAR: Products/Pricing files unchanged; unit pricing regression and full suite pass. |
| 19. Inventory/payment semantics untouched | CLEAR: no Inventory/Management backend/payment state changes; existing payment integration passes. |
| 20. Public idempotency separate | CLEAR: context rejects nonpublic keys before replay/persistence. |
| 21. Seven intended new files only | CLEAR: StaffProfile, two migrations, this task, two PHP test files, one browser test. |
| 22. Final scope/diff check | CLEAR: exclusively POS-1, git diff --check green, frozen public branch untouched. |

Baseline execution proof: isolated detached worktree at exact `027f1111d47f43b65ea1ae92221870e8ce366f35`, private `testing_pos0_public_baseline` database. Ran only `npx playwright test tests/e2e/public-checkout.spec.ts --grep 'checkout público usa cocinadas por defecto y crudas como alternativa más barata' --workers=1`: exactly two desktop/mobile cases fail at line 299 on `Empanadas cocinadas`; both snapshots show `Productos cocinados` (unchanged PriceSummary line 112). Confirmed pre-existing, excluded as POS-1-caused regressions, no test correction.

Closure boundary: this single coherent POS-1 work unit includes context, capabilities, conservative migrations, minimal null-safe surfaces, tests and responsible docs. No production dependency, public-redesign integration or POS-2 artifacts. Rollback of database changes is guarded and requires explicit data reconciliation once used; never fake customers or discard assigned access.

## Next step

POS-1 is locally delivered under the user's candidate-only tooling exception. POS-2 may consume these contracts in a separately authorized slice; no POS-2 implementation was performed. Do not push, open a PR, merge or deploy without separate authorization.
