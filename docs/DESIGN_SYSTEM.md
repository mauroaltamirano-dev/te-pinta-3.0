# Design system

Te Pinta uses shadcn/ui as an accessible technical primitive layer, not as its visual identity. Public and admin experiences share tokens but have distinct composition and density.

## Visual directions

- **Public:** mobile-first food ordering, based on the supplied Food App reference: strong product imagery, clear category/product hierarchy, and direct ordering calls to action.
- **Admin/POS:** desktop/tablet-first operational interface, based on the supplied dark Food POS reference: dense but readable navigation, catalog on the left, current order on the right, and fast state recognition.

Template names, sample products, and fictitious content are never copied. The canonical Products implementation reference is `docs/design/references/admin-products.png`; its composition is adapted to Te Pinta rather than copied as a generic dashboard.

## Ownership

| Layer                     | Location                                      | Rule                                                                                    |
| ------------------------- | --------------------------------------------- | --------------------------------------------------------------------------------------- |
| Tokens and Tailwind theme | `resources/css/app.css`                       | Single source for color, type, spacing, radius, shadow, breakpoint, and state decisions |
| shadcn primitives         | `resources/js/components/ui/`                 | Accessible low-level primitives; avoid feature/business behavior                        |
| Te Pinta shared UI        | `resources/js/components/shared/`             | Branded reusable composition such as money or status display                            |
| Feature UI                | `resources/js/features/<feature>/components/` | Business-specific components owned by one feature                                       |
| Layouts                   | `resources/js/layouts/`                       | Public, admin/POS, and authentication shells                                            |

## Token rules

- Consume semantic variables (`background`, `surface`, `primary`, `destructive`, etc.) instead of raw colors in components.
- Add a token only when it represents a reusable design decision.
- Preserve visible focus, contrast, keyboard navigation, reduced-motion preferences, and touch target usability.
- Treat responsive behavior as recomposition, not proportional shrinking.
- Public pages start at mobile width; admin/POS starts with tablet/desktop workflows and remains usable at reasonable narrower widths.

## Component reuse

Before adding a component:

1. Check `components/ui` for an existing primitive.
2. Check `components/shared` for a Te Pinta composition.
3. Check the owning feature.
4. Add a wrapper only when it centralizes real visual or behavioral policy.

Do not duplicate buttons, dialogs, inputs, product cards, money displays, or status badges under different names.

## Figma implementation workflow

When the Figma integration and exact nodes are available:

1. Retrieve design context for the exact node.
2. Capture the exact frame screenshot.
3. Reuse exported/reference assets rather than approximating them.
4. Map visual decisions to semantic tokens and shared components.
5. Implement responsive composition.
6. Verify representative viewports and interactions with Playwright screenshots.
7. Record meaningful token or component decisions here.

## Products management composition

`resources/js/pages/admin/products/index.tsx` integrates with the global dark admin shell through `resources/js/layouts/settings-layout.tsx`, which owns the Settings-local navigation rail for General and Products. The Products feature preserves the reference's hierarchy: category filters and category management at the top, then a dense responsive product-card grid led by the Add product card.

Product cards use circular imagery, a consistent image placeholder, compact base-price rows, vegetarian/unavailable status badges, and one clear edit action. Product/category editing uses focused, accessible dialogs so validation errors stay beside their fields.

Changes save per product or category. Closing/canceling a dialog discards its unsaved local state; page-wide Save/Discard controls were intentionally not reproduced because the server persists each resource independently and a fake global buffer would misrepresent that behavior.

### Admin surface palette

The admin shell uses a small semantic palette sourced from the verified Figma nodes. Components consume the `admin-*` theme tokens defined in `resources/css/app.css`; they must not introduce near-duplicate dark or coral values.

| Verified Figma node | Responsibility                                       |
| ------------------- | ---------------------------------------------------- |
| `177:735`           | Global sidebar geometry and active state             |
| `196:1985`          | Full 1194 × 834 admin composition and canvas         |
| `201:2202`          | Settings panel hierarchy and surfaces                |
| `186:100`           | Product card surface, border, and action band        |
| `175:888`           | Navigation selection treatment                       |
| `177:2031`          | Settings row content and active indicator            |
| `207:40`            | Orders dashboard rhythm, summary cards, report table |
| `232:8962`          | Order-detail overlay and right-panel geometry        |
| `232:9613`          | Compact order-detail content hierarchy               |
| `175:798`           | Reference icon weight and geometry                   |
| `190:1280`          | Summary-card spacing and typography                  |

| Semantic token         | Value                      | Use                                                      |
| ---------------------- | -------------------------- | -------------------------------------------------------- |
| `admin-canvas`         | `#252836`                  | Root admin workspace                                     |
| `admin-surface`        | `#1F1D2B`                  | Global rail, Settings panels, dialogs, and product cards |
| `admin-surface-raised` | `#2D303E`                  | Drawer detail sections and form-like raised content      |
| `admin-border`         | `#393C49`                  | One-pixel product-card borders and internal dividers     |
| `admin-accent`         | `#EA7C69`                  | Active controls, icons, labels, and focus emphasis       |
| `admin-selection`      | `rgba(234, 124, 105, .16)` | Selected navigation rows                                 |
| `admin-action`         | `rgba(234, 124, 105, .24)` | Product-card actions and restrained accent surfaces      |
| `admin-text-primary`   | `#FFFFFF`                  | Primary text                                             |
| `admin-text-secondary` | `#ABBBC2`                  | Supporting text                                          |
| `admin-text-tertiary`  | `#889898`                  | Quiet metadata and placeholders                          |

Orders uses the locally bundled Barlow 400/500/600 family from `resources/fonts/` through `font-orders`, matching the verified Figma typography without a runtime font request. Its OFL license is stored at `resources/fonts/OFL.txt`. Visible Orders copy is Argentine Spanish; enum and code identifiers remain English.

## Orders composition and detail navigation

### Quick Order Home

`resources/js/pages/admin/home.tsx` follows the 1194 × 834 POS reference as a real responsive composition: the existing 104 px rail, a fluid catalog column resolving to 681 px, and a 409 px current-order panel. The catalog keeps 24 px insets; at the reference width this leaves 633 px for three 192 × 260 cards with 28 px gaps and 132 px circular artwork.

Category tabs, selected quantities, empty/loading/error states, and the coral action use the existing `admin-*` tokens. The continuation stage uses the shared Sheet primitive with a 70% black overlay and an 814 px maximum panel split into two content columns. It reuses Te Pinta fields rather than the template payment UI. Below the content-driven two-column threshold, the order becomes an accessible bottom sheet instead of shrinking the POS columns.

`use-admin-order-draft.ts` owns local in-memory behavior, backend preview coordination, stale-response protection, and submission locking. `AdminOrderDetailsFields`, `AdminProductSelector`, and `PriceSummary` are shared with `/admin/orders/create`; the Home-specific catalog and current-order panel remain separate presentations.

`resources/js/pages/admin/orders/index.tsx` adapts the Figma dashboard into an Orders workspace: compact summary cards lead into filters and the operational report table. It does not copy template restaurant data.

Selecting a TP identifier requests `/admin/orders?order=TP-*` through Inertia and opens `OrderDetailDrawer` while keeping the filtered list visible. Closing the drawer removes only the selection, so browser back/forward remains meaningful. Direct `/admin/orders/{public_id}` links still render the full page. Both surfaces compose the same `OrderDetailContent`; pricing, labels, and operations are not duplicated.

On wide viewports, Orders uses the Figma two-column proportion: a `646:372` primary-to-insights ratio with a 24px gap, expanded to the available viewport width. At the 1194px reference frame this resolves to 646px and 372px; wider screens preserve the ratio instead of leaving unused space. Both columns fill the available viewport height and the document remains fixed; only the report body scrolls vertically, with its table header kept visible. The three summary cards share the primary width equally, start from the reference size of 199×143px, and keep 24px gaps. `OrdersInsightsPanel` divides its height using the reference `429:333` ratio; its lower card presents delivery type and cooking condition as two separate analytical rings so the unrelated dimensions remain readable. Below desktop it rejoins the document flow. Product counts, catalog images, delivery distribution, and condition distribution come from backend aggregates rather than template data.

Settings panels use an opaque surface with an 8 px radius, no outer border or shadow, and a 24 px gap. Their header, filters, and content regions remain transparent over that surface. Product cards use the same 8 px surface with a one-pixel border and no shadow; the edit action is a 52 px high action band. Figma defines no hover variant for these surfaces, so hover must not invent a new fill.

## Global admin navigation

`resources/js/components/shared/admin-sidebar.tsx` owns the global administration destinations and active-route logic. `AdminLayout` only composes this rail with the administration header and page content; feature-local navigation remains inside its feature layout, such as `SettingsLayout`.

At desktop and tablet widths, the rail follows Figma node `177:735`: 104 px wide with the `#1F1D2B` surface, a 56 px brand tile with a 12 px radius and 40 px store glyph, six 102 px navigation slots, 24 px fine-stroke icon wrappers, and a bottom-aligned logout control. Desktop controls start at x=24. The active control uses `#EA7C69`, an 8 px radius, the specified coral shadow, and a `#252836` recessed right-edge treatment. At smaller widths, the same icon-only destinations recompose into a horizontally scrollable top rail; accessible Spanish labels remain available through link names and tooltips.

Playwright comparison captures are generated under `storage/app/` and are intentionally not versioned. The Figma nodes and the semantic contract in this document are the durable source of truth.
