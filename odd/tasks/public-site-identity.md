# Public site identity

## Objective
Give Te Pinta's existing public ordering flow an original, responsive visual identity using `#333333` for primary ink, `#e3e3e3` for the base canvas, and `#b85c3f` for the Cafayate-earth accent.

## Problem and why
The public home, menu, and cart are functional but visually generic. Customers need a recognizable restaurant landing and a clear path to order without changing backend-owned prices, validation, order creation, or WhatsApp handoff.

## Authorized scope
Visual and UX work in the existing public React/Inertia pages and their shared public styling/components; focused tests and design-system documentation. No admin/POS redesign, backend business-rule change, third-party assets, remote operations, or new product-detail route without demonstrated need.

## Constraints
- Treat both supplied Figma Community links as composition/interaction inspiration only; exact files could not be opened from this environment, so no unverified measurements or asset claims.
- Preserve the existing server-quoted checkout, persisted order before WhatsApp redirect, and slug-based cart.
- Preserve unrelated dirty worktree edits; stage only this feature's files or hunks.
- Design system tokens remain centralized in `resources/css/app.css`; public styling must not change the admin-shell palette.
- TDD mode: unresolved (no explicit project/session setting found); no RED/GREEN claim. Runner: `npm run test:e2e -- tests/e2e/public-checkout.spec.ts`; also `npm run types:check`, `npm run lint:check`, `npm run format:check`, `npm run build` and affected PHP tests as applicable.

## Delivery
- Route: delegated direct. Trigger: mapping required 4+ files and implementation touches 2+ non-trivial files; one read-only mapping delegate completed, bounded implementation writer to follow.
- Forecast: approximately 700–1000 authored changed lines across three work units (advisory estimate, not a cap).
- Strategy: `single-pr` for this local preview; no PR or remote operation is authorized. This avoids interrupting the requested implementation for delivery-only confirmation. If a later PR exceeds the repository size gate, pause there for the required exception or chain decision.
- RDD: enabled by default; assess each committed work unit against the prior boundary, starting at `9d57ff8`, and follow native transitions if due.
- Slice boundaries: pending.

## Tasks
- [x] **PUI-1 — Public foundation and home.** Established semantic public colors, responsive shell/navigation, restaurant-led home hierarchy and featured product treatment. Acceptance observed: CTA reaches `/menu`; no-featured state remains coherent; admin tokens and asset remain unchanged. Checks: `npm run types:check`, `lint:check`, `format:check`, `git diff --check` pass; public-home E2E 2/2 and public-checkout E2E 6/6 pass with local DB/library overrides; Vite bundle without Wayfinder plugin passes. Normal `npm run build` blocked by a pre-existing root-owned generated Wayfinder file. Route: delegated; CSS, layout, home, featured card, test, and docs are non-trivial.
- [ ] **PUI-2 — Fast menu.** Improve category browsing and product cards with existing catalog images/data, quantity actions, and a mobile cart CTA. Acceptance: products remain orderable, categories usable, and cart count accurate. Checks: public checkout E2E plus static/build. Route: delegated; menu and shared public components are non-trivial.
- [ ] **PUI-3 — Cart and final order.** Clarify cart, customer form, quote, and WhatsApp handoff responsively without moving pricing or validation to the client. Acceptance: existing checkout flow and error/empty states pass. Checks: public checkout E2E, affected PHP tests, static/build. Route: delegated; cart and public styling are non-trivial.

## Progress and evidence
- Read-only mapping identified the existing public routes/pages and checkout boundary. Working tree already contains unrelated admin/backend changes.
- Exact Figma Community designs were inaccessible through the available browser; only user-described patterns are accepted as design direction.
- Next: commit PUI-1 as an isolated work unit, then implement PUI-2. Do not stage unrelated dirty admin/backend changes.
