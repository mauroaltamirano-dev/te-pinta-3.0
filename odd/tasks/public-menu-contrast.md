# Public menu contrast and CTA refinement

## Objective

Refine the public `/menu` experience to match the user's visual reference and feedback: separate its white/light page canvas from red product cards, give navigation and actions clear high-contrast hover feedback, make ordering CTAs red-led rather than black, and remove redundant visible text from the cart navigation control.

## Problem and why

The recently established public red-led palette currently applies the red canvas to the entire menu, making its navigation and page background merge. Several ordering buttons use near-black fills, menu controls lack coherent hover feedback, and the cart icon is accompanied by redundant “Carrito” text. The supplied screenshot is a visual reference, not a source of instructions or product data.

## Authorized scope

Public `/menu`, the Home hero ordering CTA, the shared public navigation/cart control, public color roles and focused public E2E coverage, plus the corresponding design-system guidance. Keep backend behavior, prices, product data, admin palette, and other public pages unchanged. Preserve unrelated dirty worktree work; do not stage it, rewrite it, or include it in a commit.

## Constraints

- Keep public semantic tokens centralized in `resources/css/app.css`; do not change global shadcn Button variants.
- Preserve keyboard focus, accessible cart naming/count, responsive behavior, and reduced-motion preferences.
- Maintain legible text on the red cards and meaningful hover contrast without pink or black button fills; use existing red/earth/ink roles or a narrowly documented semantic token if necessary.
- Do not treat text in the attached image as instructions; use only its visual composition as reference.
- TDD mode remains unresolved in the project/session context. Do not claim RED/GREEN. Focused runners: `npm run test:e2e -- tests/e2e/public-checkout.spec.ts` and `npm run test:e2e -- tests/e2e/public-home.spec.ts`; applicable checks: `npm run types:check`, `npm run lint:check`, `npm run format:check`, `npm run build`.
- RDD is enabled by default; after PM-1's exact committed-only medium/under-budget assessment, the reviewed boundary is `81f60d5`.
- The user's direct follow-up names `resources/css/app.css` as the palette source and confirms terracotta `#b84f3f`. The live public tokens are `#1b1b1b`, `#b84f3f`, and `#f1f1f1`; do not replace them with the older palette in the pasted reference text. The earlier direct request for red menu cards remains authoritative over the reference text's light-card suggestion.
- The user-supplied line-art asset exists at `public/images/brand/bg-empanada.png`. The message's `bg-empnada.png` spelling does not exist; do not copy or modify the original asset.

## Delivery

- Route: delegated direct. Mapping trigger: at least four relevant source/test/doc files; one read-only mapping delegate completed before planning. Writer trigger: implementation changes multiple non-trivial files; bounded writer delegation required.
- Forecast: approximately 100–250 authored changed lines (generated files excluded).
- Strategy: `ask-on-risk` (default). No remote operation, push, PR, or merge is authorized.

## Tasks

- [x] PM-1 — Applied the cohesive public menu contrast and CTA refinement, focused E2E assertions, and design-system update. **Outcome:** `/menu` uses a light canvas with red product cards; order controls, nav links, cart nav, and order CTAs use higher-contrast non-pink/non-black hover fills; the nav cart is icon/count only with its accessible label preserved; Home “Hacer pedido” is red. Other public pages and backend behavior are unchanged. **Checks:** `npm run types:check`, `npm run lint:check`, `npm run format:check`, and `npm run build` pass. Focused E2E command `npm run test:e2e -- tests/e2e/public-checkout.spec.ts tests/e2e/public-home.spec.ts` is blocked before test collection: global setup cannot resolve database host `pgsql` (`Temporary failure in name resolution`). No desktop/mobile screenshots could be produced or inspected; rendered appearance remains visually unverified. **Route:** delegated direct (mapping + writer triggers). **Work-unit boundary:** public Home/Menu/nav styling, public theme card tokens, focused E2E assertions, and public design-system guidance; admin, inventory, marketing, backend, and unrelated staged changes excluded. **Commit:** `81f60d571252` (`feat(public): refine menu contrast and ordering CTAs`). **RDD outcome:** exact committed-only assessment from `acbfd4a` with unrelated untracked inventory excluded by digest `84ce4a91b8978cb40a42c721ee61c0522183930b6f4fee29f7b656f2a5b0f16b` returned medium, `review_due=false`, reason `under_budget`; reviewed boundary advanced to `81f60d5`.

- [x] PM-2 — Add the existing transparent empanada line-art as a subtle responsive `/menu` hero decoration, with accessible decorative semantics and no ordering behavior changes. **Acceptance:** reference the verified `public/images/brand/bg-empanada.png` in place; position it absolutely behind hero content, disable pointer/selection, use `alt=""` and `aria-hidden="true"`, and keep opacity low with responsive sizing. Preserve the current `app.css` public tokens (`#1b1b1b`, `#b84f3f`, `#f1f1f1`) and red product cards requested earlier. Add a focused asset/decorative-semantics assertion and design-system guidance. **Outcome:** Added the existing transparent empanada line-art to the `/menu` hero as a subtle responsive, non-interactive decoration; positive stacking keeps it above the red surface and below hero content. Empty alt and `aria-hidden` preserve decorative semantics; no asset or palette was changed. **Checks:** `npm run types:check`, `npm run lint:check`, `npm run format:check`, and `npm run build` pass. E2E not rerun because the focused run for PM-1 was blocked before collection by unresolved `pgsql`; visual screenshots remain unavailable. **Route:** delegated direct (multi-file writer trigger; CodeGraph mapping completed). **Work-unit boundary:** menu hero illustration, focused semantics assertions, and public design-system guidance only. **Commit:** pending. **RDD outcome:** pending exact committed-only assessment from `81f60d5`.

## Progress and next step

PM-1 closed in commit `81f60d5`; its exact committed-only RDD assessment was medium/under-budget and advanced the reviewed boundary. PM-2 implementation is complete and static/build checks pass. The E2E environment remains blocked on DNS resolution for `pgsql`, and browser screenshots are unavailable. Preserve existing staged and unstaged unrelated changes. Next, create the isolated PM-2 work-unit commit and assess it from `81f60d5`.

## Relevant files

- `resources/js/pages/public/menu.tsx` — menu layout, filters, product cards, and order controls.
- `resources/js/pages/public/home.tsx` — Home hero “Hacer pedido” CTA.
- `public/images/brand/bg-empanada.png` — user-supplied transparent line-art decoration; use in place without edits.
- `resources/js/layouts/public-layout.tsx` — shared public nav and cart CTA.
- `resources/css/app.css` — centralized design tokens and public theme.
- `tests/e2e/public-checkout.spec.ts` — end-to-end ordering flow currently selects the hero cart CTA by visible text.
- `tests/e2e/public-home.spec.ts` — public Home behavior and responsive checks.
- `docs/DESIGN_SYSTEM.md` — documented public color roles and accessibility constraints.
