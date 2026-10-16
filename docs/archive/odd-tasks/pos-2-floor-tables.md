# POS-2 — Floor Plan & Dining Tables

## Objective and scope
Model physical DiningArea/DiningTable configuration in Restaurant and administer
it visually with a shared responsive renderer. Exact branch point: e39f5df.
Orders, Products and Payments remain unchanged. No TableSession, occupancy,
orders, submissions, tickets, payments, reservations, waiter assignment or POS-3.
No public redesign, remote operations, development migrations, push, PR or deploy.

## Route and work unit
`solo` / `delegation_unverifiable`: the permitted exploration attempt did not expose
native UUID/model/effort; the worker is completed and no writer remains. The
user-owned fallback explicitly permits sequential primary-session execution.
One integrated work unit: physical configuration plus its usable editor, tests and
docs. Checkpoints below share this coherent eventual commit. Commit only after
normal review, or a newly authorized candidate-specific review tooling exception.
Delivery strategy: `single-pr` for planning only; no PR creation authorized.
Forecast: approximately 1,800 authored lines (generated files excluded). This is
not a hard limit and no code/tests will be compressed to meet a line budget.

## Design and invariants
- Integer IDs follow current models; visible table code is independent identity.
- Coordinates are canonical decimal strings with four fractional digits in
  NUMERIC columns; exact scaled-integer bounds are the backend authority.
- Logical width/height preserve physical aspect ratio; circle dimensions must
  represent a circle (one logical unit tolerance for decimal quantization).
- Rotation uses eight 45-degree steps; conservative integer coefficients protect
  the full rotated footprint without floating-point persistence authority.
- Obstacles are bounded schema-validated JSON, not independent domain entities.
- Full aggregate save includes area metadata, all preserved tables and obstacles.
  Lock area, compare expected_version, commit atomically, increment once.
- Missing existing tables cannot silently delete history; deactivate instead.
- Admin-only routes; active read query is reusable but no POS endpoint is exposed.
- No drag library; local pointer state, explicit save, accessible numeric controls.
- Collision warnings deferred; overlap allowed, area bounds mandatory.

## Acceptance and checkpoints
- [x] T1: Backend RED → GREEN → REFACTOR; models, aggregate actions, requests,
  migration and exact invariants; HTTP/domain/PostgreSQL negative cases.
- [x] T2: Shared read-only/editable FloorPlan, desktop pointer movement/resizing,
  mobile selection/property controls, explicit inactive labels and dirty/conflict UX.
- [ ] T3: Isolated sequential backend regression and focused E2E at 320, 360,
  390/393, Pixel 7, 1280, 1920; quality checks, responsible docs and final review.

## Checks and evidence
PHPUnit: Restaurant, Identity, POS-1 context regression, full suite when feasible.
Pint, PHPStan, ESLint, Prettier, TypeScript, Vite, focused Playwright.
All DB checks use a dedicated local PostgreSQL container; browser DB separate.
No development migrations. Observed initial RED: 47 domain cases failed before
implementation; GREEN: 47 cases/181 assertions. Numeric/string ID alias regression
then RED (200 instead of 422), fixed validation and action guards, GREEN (1/3).
Final focal regression: 121 passed/398 assertions (Restaurant 79, Identity 7,
POS-1 context 35). Full PHP suite: 485 passed/3,600 assertions in the local
verification container with APP_ENV=testing and SESSION_DRIVER=array.
First full-container attempt inherited production mode: 196 failed/289 passed
(419 CSRF); explicitly corrected test environment, no application bypass added.
Focused browser suite: 9 unique scenarios passed, 9 duplicate-project variants
intentionally skipped. Final mobile-control improvement rerun: 9 passed/9 intentionally skipped (26.4s).
PHPStan, Pint, ESLint, Prettier, TypeScript and Vite observed passed; final checks
remain binding to final bytes. Isolated migration DB: migrate → rollback POS-2
only → migrate all passed. No existing migration modified.
Host browser launch unavailable (libgbm); local verification image supplied browser
libraries. Screenshots used only for visual inspection, not coordinate authority.
Review mode: on/default. Boundary: e39f5df. Commit: pending.

## Progress / next step
Implementation and focal/full backend verification complete. Shared renderer,
editor, responsive matrix, read-only mode and two-admin conflict verified. Final
browser rerun complete; candidate normalization/fingerprint and native review pending.
No POS-3 code and no commit. Collision hints and arbitrary rotation are follow-ups;
MVP supports rectangle/circle and 45-degree rotation steps. No native approval,
independent approval or review tooling exception has been granted for POS-2.
