# Administrative operational UX: AUX-0 approved contract

**Status: approved design contract with the conditions below. No AUX-1 implementation is authorized by this document.**
Base: `ea526d8bd8bdebf8bc130c662ed3025a8d549f5f` (AOB-7), branch `feat/admin-operational-ux-normalization`.
Scope: Inventory, Recipes, Production, Adjustments, current Product Costing, Products configuration, Management and Rich Purchases. Public Site and POS-3 are excluded.

## Approved decision and execution order

The user approved one opt-in operational page shell, the existing dark identity and Barlow, the 80rem outer container, 20/24px gutters, 48rem internal simple forms, the proposed spacing scale, consistent headers/breadcrumbs/URL navigation, controls of at least 44px, consistent panels, coral buttons with dark text, AA contrast and small shared components without unnecessary dependencies. This document is the responsible implementation reference for that approved operational scope; the broader [design system](../DESIGN_SYSTEM.md) remains responsible for unrelated Public/POS surfaces.

The approved sequence is strictly serial:

1. Formalize AUX-0 without application-source changes; complete its applicable review and obtain any required local documentary commit authorization.
2. Only after AUX-0 closes, implement **AUX-FIX-0 — Inventory Adjustment Stale Preview** as a separate functional candidate with observed RED → GREEN, focused browser proof, AOB-4 regression and a new review. Stop before its functional commit for separate authorization.
3. Only after AUX-FIX-0 closes, prepare AUX-1. Do not implement AUX-1 automatically or start AUX-2.

Design approval is not native review approval, a receipt, or permission to commit a functional candidate. Do not run multiple candidates simultaneously. No previous AOB approval transfers to AUX work.

Preserve the reconstructed domain contracts. No pricing, ledger, stock, authorization, financial reporting, historical COGS, production costing or reversal redesign is part of visual normalization.

## Current UI inventory

The current base has **18 page files and 21 primary URL surfaces**. The four Management sections share one page. Categories, purchase presentations and several financial operations are dialogs, not separate GET pages.

Page paths below are relative to `resources/js/pages/admin/`.

| Real URL | Page / associated UI | Current composition |
| --- | --- | --- |
| `/admin/management` | `management/index.tsx`, Panorama | Barlow; 30px title; period filter, financial summary and distribution panels |
| `/admin/management?section=operations` | Same page; financial operation dialogs | URL-authoritative navigation; manual income, expense, withdrawal, transfer and distribution actions |
| `/admin/management?section=wallets` | Same page; `wallet-grid`, `wallet-form-dialog` | Ledger-derived balances, active/archive states |
| `/admin/management?section=movements` | Same page; `transaction-history` | Independent history filters, desktop table/mobile cards, detail sheets |
| `/admin/management/audit` | `management/audit.tsx`, `audit-dashboard` | Barlow; 30px title; derived reconciliation findings and filters |
| `/admin/management/purchases` | `management/purchases/index.tsx` | 24px title; different font from Management; local filters and pagination |
| `/admin/management/purchases/create` | `management/purchases/create.tsx` | Loose back link; long form; item lines, shipping, wallet/date |
| `/admin/management/purchases/{purchase}` | `management/purchases/show.tsx`, attachment section | Loose back link; impact, lines, private attachments, reversal dialog |
| `/admin/inventory` | `inventory/index.tsx` | Barlow; 24px title; valuation summary, categories/items, presentations, physical history and dialogs |
| `/admin/inventory/recipes` | `inventory/recipes/index.tsx` | Loose back link; recipe cards; local pagination |
| `/admin/inventory/recipes/create` | `inventory/recipes/create.tsx`, recipe form | Recipe identity/output/ingredients form |
| `/admin/inventory/recipes/{recipe}` | `inventory/recipes/show.tsx` | Current/retired URL views; versions, quantities, current estimated costs and production CTA |
| `/admin/inventory/production` | `inventory/production/index.tsx` | Batch cards, historical costs, pagination; empty state already links to recipes |
| `/admin/inventory/production/create` | `inventory/production/create.tsx` | Recipe-version selection, actual output, date and confirmation |
| `/admin/inventory/production/{productionBatch}` | `inventory/production/show.tsx` | Historical input/output/cost snapshots and reversal |
| `/admin/inventory/adjustments` | `inventory/adjustments/index.tsx`, adjustment form | Embedded preview/confirmation form plus history |
| `/admin/inventory/adjustments/{inventoryAdjustment}` | `inventory/adjustments/show.tsx` | Physical/economic impact and reversal |
| `/admin/inventory/product-consumption/{product}` | `inventory/product-consumption.tsx` | Entire outer page capped at 768px; three physical states and component editor |
| `/admin/inventory/costing` | `inventory/costing/index.tsx` | Product selector; `product_id` and optional `quantity`; policy assignment and presentation economics |
| `/admin/inventory/costing-policies` | `inventory/costing-policies/index.tsx` | Existing policies plus inline create/edit; overhead editor exposes basis points |
| `/admin/settings/products` | `products/index.tsx`, Products feature components | 20px title; specialized SettingsLayout with 17rem local navigation and scrolling workspace |

### Existing shared building blocks

- `layouts/admin-layout.tsx`: desktop sidebar column of 104px, mobile bottom navigation, administration header, main padding of 20px below `sm` / 24px from `sm`. Existing specialized Orders/Settings compositions must not be changed globally.
- `layouts/settings-layout.tsx`: settings navigation and product workspace; retain its editor behavior, normalize its outer header alignment rather than replacing the workspace.
- `components/shared/admin-sidebar.tsx`: working desktop/mobile navigation and More sheet. Preserve it; avoid a sidebar redesign.
- `components/ui/`: Button, Input, Label, Card, Alert, Dialog, Sheet, Select and breadcrumb primitives already exist.
- `components/breadcrumbs.tsx`: existing Inertia breadcrumb composition. Reuse it instead of making a second breadcrumb implementation.
- `features/inventory/components/form-field.tsx`: label/hint/error composition used by operational forms.
- `features/management/components/transaction-history.tsx`: already has desktop table/mobile-card representations. Reuse this pattern, not a new generic data-grid engine.
- Existing feature money formatters and `features/inventory/presentation.ts` exact decimal/quantity formatters. The latter uses string/BigInt formatting, not float quantity conversion.

## Visual inconsistencies and concrete evidence

| Finding | Evidence on the AOB-7 base | Proposed owner |
| --- | --- | --- |
| Font changes between related screens | Rendered Management/Inventory use Barlow; Purchases, Recipes, Costing and Products inherit Instrument Sans. `admin-shell` does not set a common font. | AUX-1 opt-in scope; feature adoption afterward |
| Three title scales | Rendered Management/Audit 30px; Inventory/Purchases/Recipes/Costing 24px; Products 20px. | Shared page header |
| No common outer width contract | At 1280px, main operational pages start at x=128 and are 1128px wide; consumption is only 768px wide. At larger widths most pages continue stretching. | Page shell; narrow forms only inside it |
| Purchase list/detail are not currently different outer widths | Both measured x=128 / width=1128 at 1280px. Their hierarchy, panels and back links differ; do not invent a container bug already absent from AOB-7. | AUX-2 |
| Navigation resembles operations | Management section links use Button styling; Inventory's header mixes sibling links with create actions; Costing uses loose links. | Shared section navigation and action placement |
| Back links are inconsistent and lose list context | Recipes/production/purchases use loose hard-coded parent links. Pagination implementations retain different subsets of query parameters. | Feature-owned URL helpers plus shared presentation |
| Active navigation needs pathname handling | Sidebar compares the full Inertia URL; a query on a base route can lose its active state. Nested recipe URLs match both Inventory and Recipes as `aria-current=page`. | Small, explicitly tested operational navigation correction; no global regrouping |
| Controls vary in size | Button/Input defaults are 36px; purchase selects are 40px; several Management controls explicitly use 44px. Management section links measured 36px. | Opt-in operational control size |
| Primary contrast is insufficient | Existing white on `#EA7C69` is **2.77:1**. `#1F1D2B` on the same coral is **5.98:1**. | Scoped semantic foreground; never global Public/POS Button changes |
| Borders cannot identify inputs alone | `#393C49` on `#1F1D2B` is 1.51:1. Keep that decorative panel border but introduce a clearer interactive boundary. | Scoped control token |
| Purchase form has document overflow | Browser baseline: +55px at 320px, +15px at 360px. Intrinsic form/control sizing requires investigation and a local sizing fix. | AUX-2 |
| Purchase detail has document overflow | Desktop-mode Chromium: +36px on narrow viewports and +144px on desktop. Offending element is the attachment file Input combining `sr-only` with shared Input sizing. Pixel 7 emulation did not reproduce it. | Attachment control containment; not `overflow-x:hidden` on the document |
| Panels use unrelated density/surfaces | Management uses surface headers/separators, recipes use local bordered cards, policies expose a long inline editor, purchase reversal is a full-width red action. | Shared panel rules; feature hierarchy |
| Overhead configuration speaks an internal language | Policy summary already displays 10%, but the editor asks for basis points. | AUX-4 exact percentage adapter |
| Technical explanations dominate the reading order | Recipe reference mass and costing technical caveats sit near core business values; missing-cost explanations repeat in every presentation. | Progressive disclosure; retain all critical coverage/warnings |

### Behaviors that already work and must not be “fixed” incorrectly

- Recipe money is already integer pesos. The private fixture rendered Cebolla **$2.150**, Roast Beef **$14.500**, total **$16.650**, expected **2 kg**, estimate **$8.325/kg**. No monetary division by 100 is needed.
- Policy packaging already renders **1 unidad** and exact **2,000001 unidades**, not a raw micro-unit integer. Preserve exact display precision; improve product/item wording, not persisted scale.
- Current costing already explains missing consumption/policy and withholds unknown margin. Improve grouping and contextual CTAs instead of replacing its coverage contract.
- Production's empty state already links to recipes. Make it consistent and actionable, not a duplicate feature.
- Management navigation is already URL-authoritative. Summary defaults to inclusive 30 days and movements to 7 days; their filters are intentionally independent.

## Navigation audit and proposed hierarchy

Retain canonical routes and the working sidebar. Add a single consistent sibling-navigation pattern in the content area; actions belong in the page/section header, not a competing second row of navigation buttons.

| Area | Proposed content navigation / detail path |
| --- | --- |
| Management | Panorama / Registrar operación / Billeteras / Movimientos, still controlled by `section` in the URL |
| Management context | Compras and Auditoría as a clearly labeled related-area navigation group; not primary confirmation buttons and not redundant nested tabs |
| Purchases | Gestión → Compras → Compra #N / Registrar compra; stable parent list link with valid list context |
| Inventory | Insumos / Recetas / Producción / Ajustes / Costos de productos; policies are contextual configuration under Costing |
| Recipes | Inventario → Recetas → recipe name; retain Actual / Retiradas and version-specific production actions |
| Costing | Inventario → Costos de productos → selected product using `?product_id=...`; retain `quantity` for a unit-family quote |
| Policy configuration | Inventario → Costos de productos → Políticas de costo; no new route or product alias |
| Product consumption | Canonical `/admin/settings/products` remains the product catalog; link to existing product-specific consumption editor and back to the selected costing product |

Use real Inertia links, normalized pathname for active matching, query-driven state and `aria-current`. This is section navigation, not an ARIA tab widget unless full tab/tabpanel keyboard semantics are implemented. Mobile navigation scrolls within its own row, never the document, and keeps the selected section reachable.

Preserve `period/from/to` independently of `history_period/history_from/history_to` and history filters. Pagination, product selection and breadcrumbs must preserve only the relevant allowed context, not blindly append arbitrary URLs. Browser Back/Forward and direct reload remain authoritative.

One concrete CTA correction: purchase prerequisites currently link “Configurar billeteras” to `/admin/management` (Panorama). Target `/admin/management?section=wallets` instead. All 21 audited real surfaces returned HTTP 200; this is a wrong destination, not an observed 404. Do not introduce `/admin/inventory/products`.

## Approved design system contract

The following values are approved, subject to the clarifications and acceptance gates below. Tokens belong in the existing stylesheet, scoped to operational administrative pages and their explicitly scoped portals. Do not change global CSS in a way that affects Public Site or the future operational POS. No second theme file or parallel component framework.

| Contract | Approved rule |
| --- | --- |
| Identity | Canvas `#252836`; surface `#1F1D2B`; raised `#2D303E`; decorative border `#393C49`; accent `#EA7C69`; Barlow 400/500/600. Do not turn neutral panels orange. |
| A. Content container | Keep AdminLayout as the sole owner of outer gutters: **20px mobile / 24px from sm**. PageContainer adds no second padding; `width:100%`, `min-width:0`, **max-width:80rem**, centered in the available main column, excluding the sidebar. |
| Variants | Data and detail pages share that same outer container. Simple forms have an inner **max-width:48rem** centered within it; line-item purchase/recipe editors may use the full inner width. Specialized Products workspace retains its internal 17rem navigation. No page-local arbitrary outer max widths. |
| Spacing | Shared steps **4 / 8 / 12 / 16 / 20 / 24 / 32px**. Outer page regions 24px; form fields 16px; label-to-control 8px; panel padding 16px mobile / 24px desktop. The retained 20px is the existing mobile shell gutter, not another page-specific exception. |
| B. Page header | Breadcrumbs first; one h1 **24px/32px mobile, 28px/36px desktop**, weight 600; concise 14px/20px description; title/actions share one responsive header. Body 16px/24px; dense data 14px/20px; section titles 18px/24px. |
| C. Page actions | One primary action per operational context; secondary/outline navigation or edits. Wrap on small screens without changing DOM reading order; destructive actions separate from the primary workflow. |
| D. Section navigation | Real URL links, one common selected treatment (accent text/indicator, neutral surface), 44px targets, focus visible; no solid-coral wall of equally important buttons. |
| E. Section header | Consistent title/description/action alignment. Use the common 18px/24px section-title scale and semantic h2/h3 sequence, not page-local arbitrary sizes. No arbitrary accent-top line on one sibling panel. |
| F. Panels/cards | Surface + 1px decorative border + **12px radius**; consistent header/content/footer spacing. Raised surface reserved for inset controls or meaningful subordinate regions. Avoid card-within-card without a distinct task. No decorative heavy shadows. |
| G. Forms | Persistent labels; hint/error associations; exact string values for quantities; minimum 44px controls, 16px input text on mobile; responsive columns only when fields are related. Pending/success/error states remain understandable. |
| H. Tables/lists | Semantic caption/headings; consistent 48px minimum rows, aligned actions, tabular numerals and right-aligned money. Mobile labeled cards retain date/status/amount/identity and important actions; no hiding financial or coverage data. Shared pagination presentation with feature-owned query context. |
| I. Filters | Labeled responsive grid, common control sizing, clear apply/reset, visible applied period/range. Never allow a movements filter to recalculate Panorama. |
| J. Empty state | Compact title + explanation + real first-step CTA. Distinguish empty, no filter matches, missing prerequisites and failed load. Do not imply an unknown cost is zero. |
| K. Feedback | Field errors close to controls; operation errors/warnings inline with icon and text, not color alone. Existing toast only for non-critical confirmation. Keep pending button state and prevent accidental duplicate submissions using existing contracts. |
| L. Detail navigation | Existing breadcrumb primitive + stable parent link with context. Same outer shell as the list; identity, state and consequences precede technical audit details. |
| M. Dialogs | Reuse Dialog/Sheet and their focus/escape behavior. 16/24px padding, constrained height with internal scrolling, visible title/description, reachable actions. Carry operational tokens onto portaled content explicitly. No new dialog engine. |
| N. Buttons | Primary coral + **surface-colored dark text**; secondary raised + light text; outline neutral with clear boundary; ghost for low-emphasis navigation; danger only for destructive actions with confirmation. All states must retain contrast and visible keyboard focus. Opt in to 44px size; do not change shared defaults globally. |
| Interactive boundaries | Proposed neutral `#7A7F91` for input/outline identification: contrast **3.67:1** on canvas, **4.15:1** on surface, **3.29:1** on raised. Keep the requested border color for decorative separators. Focus uses a visible accent outline, not a low-opacity shadow alone. |
| Money and quantities | Argentine display (`$ 12.500`, `1,5 kg`, `250 g`, `6 unidades`) using existing exact formatters. Add spacing through formatting/presentation only, never change stored money or quantity scale. |

### Page composition

```text
AdminLayout (existing sidebar, header and gutters)
  PageShell / PageContainer (opt-in operational theme, Barlow, shared width)
    Breadcrumbs (existing composition)
    PageHeader (title and description)
      PageActions (consistent header slot; below title/description when stacked)
    SectionNavigation (when applicable)
    PageContent (panels, tables, internal narrow forms or specialized editor)
```

Keep the component API compositional: children and a few named slots, not an enormous schema/configuration object. Descriptions, filters and breadcrumbs are optional; their positions are not arbitrary when present.

Sibling pages always share the same outer PageShell. An internal form-width limit must not change the exterior shell or breadcrumb/header/navigation alignment. Navigation changes location or section; actions perform operations. Never give sibling navigation the same primary-action hierarchy as a confirmation or create operation.

### Surface selection and nesting

| Structure | Use when | Avoid |
| --- | --- | --- |
| Simple section | Group related headings, explanation, navigation or content already on a clear page surface | Adding a border/card merely to fill space |
| Panel | A substantial bounded task or data region needs a shared header/content/action boundary | Repeating full panel chrome inside an equivalent parent panel |
| Card | A repeated self-contained entity or concise summary needs independent scanning | Turning every label/value or form field into a card |
| Table/list | Users compare repeated records, dates, amounts or states | A card wrapper around every table row without a mobile reading need |
| Form | Users edit or confirm a coherent operation; use the shared field/feedback/action layout | A decorative panel for every field group or an exterior width different from its siblings |

### Responsive and accessibility acceptance

The **nine document-overflow observations are verified baseline debt**, not responsive approval. AUX-1–AUX-5 must resolve them without hiding necessary information or applying indiscriminate document-level `overflow-x:hidden`.

| Surface | Baseline viewport(s) | Document overflow |
| --- | --- | --- |
| Purchase create | 320 / 360 | +55px / +15px |
| Purchase detail | 320 / 360 / 390 / 393 / 412, desktop-mode Chromium | +36px at each width |
| Purchase detail | 1280 / 1920 | +144px at each width |

Pixel 7 emulation did not reproduce those overflows; it remains an independent required check, not a reason to dismiss the desktop-mode findings. Validate **320, 360, 390 and 393, Pixel 7, 1280 and 1920** during the responsible slices and final integration. Recheck purchase create/detail, hidden attachment input, tables, navigation rows and forms explicitly. No responsive-approved claim is permitted while these known document overflows remain.

Require keyboard navigation, visible focus, persistent labels and associated errors/hints, AA text contrast, discernible interactive boundaries, accessible dialog focus/escape/return, and icon-plus-text states rather than color alone. Interactive targets must be at least 44px and remain reachable above mobile navigation and safe areas. A successful HTTP response or screenshot is not a substitute for interaction/persistence/accessibility tests.

## Shared component strategy

| Approach | Files / limits |
| --- | --- |
| Reuse unchanged behavior | Button/Input/Label/Card/Dialog/Sheet/Alert primitives; existing `components/breadcrumbs.tsx`; exact inventory/recipe/management formatters and feature request contracts |
| Add minimal shared compositions | Proposed `components/shared/admin-page-shell.tsx`, `admin-section-navigation.tsx`, `admin-empty-state.tsx`; names provisional until approval. Panel composition should wrap existing Card only if adoption demonstrates real duplication. |
| Extend opt-in presentation | Operational semantic tokens/utilities in `resources/css/app.css`; additive button/control size or scoped class; semantic HTML table styling instead of a configurable grid library |
| Adapt feature owners | Existing period filters, transaction history, wallet grid, attachment section, inventory dialogs, recipe form and pagination; do not relocate business behavior into the shell |
| Preserve special layouts | No global default change in `AdminLayout`, `SettingsLayout`, `Button`, `Input`, `.dark`, `.public-theme` or `:root`. Any additive layout hook must affect only the named operational routes and their portals. |

Do not add dependencies, generic repositories, a form engine, a second design-token source, a generic data-fetching abstraction or a new pricing calculator.

## Operational UX improvements

- **Management:** separate period summary from movement filters visually as well as functionally. Keep current/initial/final balance, operational result and personal withdrawals distinct. Group manual actions by intent; preserve transfers/distributions, versioned rules, pending allocations, reversals, private attachments and audit drill-down.
- **Purchases:** one clear create action, visible wallet/date/supplier context, readable line totals and separate shipping, concise financial/physical impact, stable list/detail return and a secondary danger zone. Fix the narrow-screen form and attachment overflow without hiding content or exposing private files.
- **Inventory:** lead with item name/kind/stock/valuation status; show category/purchase-presentation configuration as a secondary task. Contextual links lead to Purchases, Recipes or Adjustments as appropriate. Physical movements remain distinct from financial movements.
- **Recipes/Production:** lead with recipe identity, produced item, active version, ingredients and expected output. Then current estimated total/unit cost and coverage. Put auxiliary reference-mass explanations in secondary details. Production detail retains historical snapshots and clearly states actual output; never show a current recipe estimate as historical batch cost.
- **Adjustments:** distinguish physical count, waste and signed manual adjustment, retain preview/consequences/confirmation. Stale-preview correction is a separate functional work unit, not a visual workaround.
- **Costing:** product identity and current reliability first; then selling price, direct cost, packaging, estimated overhead, estimated total and estimated margin. Keep compatible empanada presentations; unit products do not acquire half-dozen/dozen rules. Group common missing prerequisites once, retain presentation-specific warnings and provide real configuration links.
- **Policies:** user enters a percentage with at most two decimals, transformed exactly from a string into integer basis points (10% → 1000; 10,25% → 1025). Reject excess precision/range rather than silently rounding. Formula remains overhead on direct cost + packaging; inactive/absent assignments stay explicit. Packaging uses item + exact quantity, not a duplicate price.
- **Product configuration:** explain missing_config / no_inventory / consume in business language, retain explicit no-physical-consumption as known zero, and provide a route back to the selected product's costing. Do not infer physical behavior from category or policy.

The intended route is item → canonical purchase → updated stock/valuation → recipe → production → product consumption → packaging policy → assignment → current economics. Use contextual links and prerequisite CTAs, not a giant wizard or invented routes.

Terminology: Costo directo; Costo estimado; Margen estimado; Costo histórico; Stock actual; Conteo físico; Merma; Presentación de compra; Presentación de venta; Costos indirectos estimados. Internal enums remain unchanged.

## Implementation units and approval gates

These approved boundaries do not authorize automatic implementation of later units. Before each implementation, record exact files, acceptance tests, candidate identity and its own review outcome. Obtain local commit authorization at closure. No previous native approval transfers to a new candidate. **AUX-FIX-0 precedes all AUX-1 implementation.**

| Unit | Bounded scope / affected surfaces | Verification and review boundary |
| --- | --- | --- |
| AUX-FIX-0 | `resources/js/features/inventory/adjustments/form.tsx`, focused real-interaction regression tests and its responsible fix evidence. No cosmetic normalization, backend/domain/schema/pricing/authorization changes. | The 12 acceptance cases below, observed RED → GREEN, AOB-4 regression, focused browser, exact candidate/new review and separate functional commit authorization. Complete only after AUX-0 closes; stop before committing. |
| AUX-1 | `resources/css/app.css`; new shared shell/navigation/empty-state compositions; additive reuse of existing primitives/breadcrumbs; minimal shell/header adoption in `pages/admin/management/index.tsx` and `pages/admin/inventory/index.tsx` only. Responsible design-system documentation. No financial or stock content restructuring. | Shell alignment, scoped font/button tokens, 44px controls, keyboard/contrast, mobile navigation, no Public/POS style leakage. Render primitives through real adopted pages, not a production-only test route. Separate shell/tokens from additional primitive work if needed; assess/review each bounded candidate. |
| AUX-2a | Management index/audit and `features/management/components/` period filter, wallet grid, transaction history, audit dashboard. URL-active navigation correction separately bounded if it touches shared sidebar logic. | Existing operational-management E2E; period independence, URL/back-forward/reload, audit links, administrator-only backend regression. No query or financial formula changes. |
| AUX-2b | Existing Management operation dialogs/detail sheets/distribution rules and private attachment presentation only. | Manual operations, transfers/distributions, pending/reversal states, focus management, private attachment access. Preserve existing payloads and server validation. |
| AUX-2c | `pages/admin/management/purchases/{index,create,show}.tsx`, reused attachment UI, purchase E2E. No second purchase flow. | Canonical list/create/detail/reverse, prerequisites CTA, shipping separate, exact quantities, list-context return and overflow reproductions. Each subunit gets its own candidate/authorized local commit. |
| AUX-3a | `pages/admin/inventory/index.tsx`, `features/inventory/components/` category/item/presentation dialogs and physical history. | Inventory foundation E2E, exact quantity formatter tests, labels/errors/stock semantics, next-step navigation. |
| AUX-3b | Recipe/production pages, recipe form/pagination/presentation. | Current/retired URL views, expected vs actual output, integer-peso example, current estimate vs historical batch, production/reversal E2E. |
| AUX-3c | Adjustment pages/form presentation only; preserve the prior AUX-FIX-0 correction. | Adjustment workflow/reversal regression, waste/Management isolation and stale-preview regression. Do not duplicate or silently change the earlier functional fix inside a visual candidate. |
| AUX-4a | Current-costing and policy pages, costing presentation/types only where needed, existing exact formatters. | Empanada 1/6/12 vs unit family; unknown/unreliable margin, exact percentage round trips/range, packaging readable quantities and no stock/financial side effect. |
| AUX-4b | Product-consumption page and narrow presentation-only adoption in `pages/admin/products/index.tsx` / relevant Products feature components. Preserve SettingsLayout editor and backend pricing/physical contracts. | Three physical states, policy assignment, valid catalog/consumption/costing links, selected context, authorization, no category-based pricing. |
| AUX-5 | Integration tests, affected E2E adaptations and small **named** cross-surface defects only. No new design framework or accumulated giant polish candidate. | Complete workflow, all target viewports, keyboard/focus/dialogs, no document overflow, link checks, exact formats, zero new lint errors, full backend/static/build and related POS regression. Split any functional defect into its responsible unit before review. |

Target small coherent candidates; ~400 authored changed lines is a planning signal, not a reason to omit tests or split an indivisible behavior. If one subunit still exceeds native context capacity, split by actual behavior before START. Do not ask for a tooling exception in advance. No push, PR, merge or deployment.

### AUX-FIX-0 functional acceptance boundary

User requirement: **“Un preview sólo puede habilitar confirmación cuando corresponde exactamente a los datos vigentes del formulario.”**

Use verifiable request identity/input revision and the effective relevant input state. Changing quantity, adjustment type, InventoryItem, explicit cost, unit, effective date or another submitted field that influences preview must invalidate the prior result and confirmation, reflect dirty/pending state and require fresh validation. A stale success, error, body-parser completion or callback must not replace the current state or enable confirmation. AbortController alone is insufficient.

The focused tests must cover at least:

1. A valid current preview enables confirmation.
2. Quantity changes invalidate it.
3. An old response cannot restore it after an edit, including delayed body parsing.
4. Out-of-order responses cannot overwrite the newest request/result.
5. InventoryItem changes invalidate it.
6. Adjustment-type changes invalidate it.
7. Explicit-cost changes invalidate it.
8. Confirmation is blocked during loading.
9. A new current response permits confirmation again.
10. Errors keep confirmation disabled; stale errors cannot replace current feedback.
11. Confirmation still reaches the authoritative backend, which validates, locks inside a transaction, recalculates the observed count against real stock and applies the correct adjustment.
12. No stock changes until confirmation.

Use real interaction tests where applicable, not helper-only proof. Preserve adjustment semantics, historical costing, reversals, PostgreSQL invariants and authorization. Maintain the existing backend authority even when the browser preview is current. Record exact RED/GREEN results and an immutable candidate identity; new native review follows the current AGENTS.md policy, including its candidate-scoped tooling fallback only if genuinely needed.

### Defect backlog ownership

| Item | Disposition / owner |
| --- | --- |
| R3-stale-preview-body | Mandatory priority AUX-FIX-0, immediately after documentary AUX-0 closure; no fix during the documentary candidate |
| Nine purchase overflow observations | Open verified responsive baseline; AUX-2 owns local form/attachment fixes, AUX-1 supplies scoped sizing primitives, AUX-5 verifies closure |
| Navigation/CTA/query-context inconsistencies | Relevant AUX-2–AUX-4 owner; URL-authoritative behavior and regression tests required |
| Raw basis-point editing and technical costing hierarchy | AUX-4 presentation adapters, preserving integer precision and backend economics |
| POS-2 R3 geometry quantization / R4 dining-area creation retry-idempotency | Carried forward, explicitly excluded from this work |

## Verification inventory and baseline

- Backend was rerun in private PostgreSQL `aux0_testing`: **709 passed / 5,364 assertions**, 112.79s, on unchanged AOB-7 source. An initial environment-mismatched run used database sessions instead of PHPUnit's array sessions and had a missing `.env`; it produced 7 failures / 688 warnings / 14 passes. Correcting only the external runner and empty injected environment resolved it. Those failures are not classified as source regressions.
- Private browser database: `aux0_playwright_testing`, separate from backend suite and development data. Fixtures use existing actions for purchases/production/adjustments/policies; no source changes or development DB writes.
- Browser audit: **147 route/viewport observations** (21 surfaces × 320/360/390/393/412/1280/1920), all HTTP 200 and no page errors. Nine document-overflow observations, confined to purchase create/detail. **21 Pixel 7 emulation observations**, HTTP 200/no page errors/no document overflow. These are an audit, not 168 full workflow E2E passes.
- Selected costing states (complete empanada, explicit no-inventory unit, missing configuration) were additionally checked across the seven widths: **21 observations**, all HTTP 200/no page errors/no overflow. Unreliable coverage still needs targeted behavioral/browser verification during AUX-4.
- Contrast figures were calculated from the actual sRGB token values. Screenshots were inspected for Management-related detail, recipe costs, costing and policy configuration. No pixel-perfect acceptance rule was introduced.
- Check-only ESLint **9.39.5** initially reported **69 errors / 0 warnings**, in 37 files before local Wayfinder generation. After the first local build, the measured inherited baseline/final is **64 errors / 0 warnings**, in 32 files; **0 new findings**. Five import-order findings in untouched authentication/passkey files disappeared when generated routes/actions became available to resolution; no tracked source changed and this is not claimed as a lint cleanup. The previous AOB-7 report of 218 is not substituted for these actual measurements. Use the post-generation 64-error baseline and the same runtime/configuration for subsequent slices.
- Fresh assets were built from this isolated worktree using `npm run build -- --configLoader native` (**exit 0**, about 10 seconds); browser observations were repeated against that local build after it completed. The ordinary config-bundling attempt failed because it tried writing `.vite-temp` inside read-only mounted dependencies; the native loader avoids that environment write without changing repository configuration. No formatter or source normalization was run.
- Type checking, comprehensive keyboard/assistive-technology checks, all dialogs, submissions and the historical monolithic E2E suite are not claimed as completed by this initial inventory. Backend/build and browser audit results are baseline evidence, not acceptance of an implemented redesign.

### Existing E2E coverage and adaptation plan

`tests/e2e/management.spec.ts` contains **five** historical scenarios: private attachment/reversal, manual ledger/wallet archive, transfers, versioned distribution rules, order payments/pending/audit. Its helpers assume operations and histories appear together; do not run it unchanged as proof that the current sectioned UI regressed.

Adapt still-relevant scenarios to URL sections and accessible locators. Replace the legacy purchase-creation UI path with the canonical Rich Purchase flow; retain legacy financial history/reversal and private-file authorization coverage in the appropriate existing tests. Do not delete valuable financial assertions to make visual tests pass. The monolith was inventoried, not executed in AUX-0; no inherited failures are asserted without a baseline reproduction.

Keep and extend the focused suites: `operational-management.spec.ts`, `purchase-recovery.spec.ts`, `inventory-foundation.spec.ts`, `recipe-production-recovery.spec.ts`, `adjustment-recovery.spec.ts`, `order-consumption-recovery.spec.ts`, `current-product-costing.spec.ts`, and `features/inventory/presentation.test.mjs`.

For behavior changes with deterministic runners, observe RED → GREEN → REFACTOR. AUX-0 is an audit/proposal with no product behavior change, so there is no fabricated TDD cycle. Run suites serially on each shared database; builds must not mutate assets served by browser checks. Do not use `npm run lint` or `npm run format` as check-only commands: they mutate files.

## Known risks and limits

1. Global CSS or Button changes can leak into POS/public screens; use opt-in tokens and explicit portal scoping. Preserve existing specialized layouts and sidebar behavior.
2. Losing query parameters can silently change financial periods, selected costing product or list context. Preserve feature-owned parameter semantics and test direct links/back-forward/reload.
3. Percent/quantity formatting can corrupt scale or round exact values. Use string/integer adapters and round-trip tests; never reinterpret persisted micro-units or divide monetary pesos by 100.
4. Reordering a form can change submission/validation/focus/idempotency unintentionally. Keep backend payloads/actions authoritative, with focused workflow tests and role rejection coverage.
5. **AOB-4 R3-stale-preview-body reproduced on this base:** delay `response.json()`, request waste=100g, edit to 200g, then release the old body. The old 100g preview reappears and confirmation is enabled. Only the read-only preview POST was sent; no adjustment was confirmed. The revision guard runs before body parsing, not after it. Its separately authorized implementation unit is AUX-FIX-0, before AUX-1; backend recalculation is not a substitute for an honest confirmation preview. No fix was made in AUX-0.
6. POS-2 R3 geometry quantization and R4 dining-area creation retry/idempotency remain tracked and excluded. No unrelated investigation or fix is included here.
7. Desktop/mobile document-overflow and touch findings are current-baseline evidence, not normalized-UI passes. All dialog states, keyboard coverage, error/loading/empty/filter states and submission persistence still require the responsible implementation slices.
8. Management remains financial truth: waste ≠ automatic expense; overhead ≠ financial expense; historical COGS ≠ cashflow; transfers ≠ operating income; withdrawals remain outside operating result; Rich/legacy purchase read models must not double count. Costing remains a current estimate, not net profit or physical packaging consumption. No backend change is planned.

## Worktree and handoff

- Isolated worktree: `/home/tuki/workspaces/tepinta3.0-worktrees/admin-operational-ux-normalization`.
- Branch/base: `feat/admin-operational-ux-normalization` / `ea526d8bd8bdebf8bc130c662ed3025a8d549f5f`; base tree `7dbdc810219310b175ac04431c2652d619cb0d14`.
- AOB-1–AOB-7 and POS-0–POS-2 ancestry was checked before work. The reconstruction worktree and historical dirty checkout are read-only; no stash/reset/clean/stage/commit was used there.
- Historical dirty checkout fingerprints remain: porcelain-z SHA256 `5c2d2d7c746a160519ad116f197beac2aafcf5de9ac46ecd718871bbde53976d`; tracked binary HEAD diff SHA256 `bf9d7b98b1059678ce4923aa86aa186fa0f0a4cccaa802d8c032f4dae4aae6d4`.
- Own CodeGraph index initialized in the new worktree; prior indexes were not copied or reused. Dependencies were mounted read-only from the reconstruction worktree; runtime databases and audit scripts/logs are private and separate.
- Execution mode: `solo`, `delegation_unverifiable` (effective auxiliary runtime model/effort metadata unavailable). No worker report is substituted for primary verification. Engram persistence is pending because no authoritative registered runtime session identity is available; no identity was invented or reused.
- User design approval and the conditions above are recorded in this document. Documentary review/commit closure is a separate step; this is not native implementation approval or a review tooling exception. No approvals/receipts from AOB are reused. No application source edits, AUX-1 implementation, commit or remote operation are authorized merely by this document's existence.
- Documentary closure candidate scope: this audit/approved contract only. No application source changes. Generated assets, the local CodeGraph index and runtime cache remain ignored setup artifacts. Primary structural readback checks the document, links and scope; it is not independent or native implementation approval. Commit identity and actual review outcome must be reported from observed evidence, not pre-written here.

**Execution gate: only after AUX-0 documentary review and authorized local commit close, implement AUX-FIX-0 and stop before its functional commit for review/authorization. Only after AUX-FIX-0 closes, prepare AUX-1; do not implement it automatically.**
