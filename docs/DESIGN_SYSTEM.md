# Design system

> **Decision 2026-10-15:** the dark admin theme and coral `#EA7C69` are superseded by a light theme with a tenant-configurable accent (Te Pinta: `#b84f3f`). Implementation pending (roadmap A-02). The admin tokens below describe the code as it is today; do not extend the dark palette in new work beyond what a change strictly needs.

shadcn/ui is the accessible primitive layer, not the visual identity. Public and admin experiences share token mechanics but differ in composition and density: the public site is mobile-first; admin/POS is tablet/desktop-first and must remain usable on phones.

## Layers

| Layer | Location | Rule |
| --- | --- | --- |
| Tokens and Tailwind theme | `resources/css/app.css` | Single source for color, type, spacing, radius, shadow and state decisions |
| shadcn primitives | `resources/js/components/ui/` | Low-level accessible primitives; no business behavior |
| Shared Te Pinta UI | `resources/js/components/shared/` | Reusable compositions: `admin-sidebar`, `admin-page-shell`, `admin-section-navigation`, `admin-empty-state` |
| Feature UI | `resources/js/features/<feature>/components/` | Business components owned by one feature |
| Layouts | `resources/js/layouts/` | `public-layout`, `admin-layout`, `settings-layout`, `auth-layout` |

## Rules

- Consume semantic tokens (`background`, `foreground`, `primary`, `card`, `muted`, `border`, `ring`, `destructive`, `admin-*`, `public-*`), never raw colors in components. Add a token only for a reusable design decision.
- Before adding a component: check `components/ui`, then `components/shared`, then the owning feature. Add a wrapper only when it centralizes a real visual or behavioral policy. Do not duplicate buttons, dialogs, inputs, product cards, money presentation or status badges under new names.
- Preserve visible focus, contrast, keyboard navigation, reduced-motion preferences and touch targets of at least 44 px.
- Responsive behavior is recomposition, not proportional shrinking: tables become rows or cards on phones, without page-level horizontal scroll.
- Prices and totals displayed in React come from backend previews; components format, they never calculate.
- Visible text is Spanish (Argentina); enums, identifiers and code are English.

## Current tokens

Public palette (`.public-theme`, applied by `PublicLayout`):

| Token | Value | Use |
| --- | --- | --- |
| `public-canvas` | `#b84f3f` | Home and navigation canvas; product cards on the menu |
| `public-earth` | `#f1f1f1` | Menu canvas |
| `public-earth-ink` | `#7d291f` | Small text on earth; actions on red cards |
| `public-ink` | `#1b1b1b` | Text on earth |

White text on canvas reaches 4.97:1; ink on earth 15.25:1. Brand assets live in `public/images/brand/` (wordmark in the header, monogram in the footer).

Admin palette (to be replaced by A-02):

| Token | Value | Use |
| --- | --- | --- |
| `admin-canvas` | `#252836` | Root admin workspace |
| `admin-surface` | `#1F1D2B` | Sidebar, panels, dialogs, cards |
| `admin-surface-raised` | `#2D303E` | Raised sections inside drawers and forms |
| `admin-border` | `#393C49` | Decorative borders and dividers |
| `admin-accent` | `#EA7C69` | Active controls, focus, emphasis |
| `admin-selection` / `admin-action` | accent at 16 % / 24 % | Selected navigation / card actions |
| `admin-text-primary` / `-secondary` / `-tertiary` | `#FFFFFF` / `#ABBBC2` / `#889898` | Text levels |
| `ticket-paper` / `ticket-ink` / `ticket-muted` / `ticket-rule` | `#FFF8ED` / `#2F2925` / `#796E66` / `#D9CBBD` | Order receipt dialog |

Interactive boundaries use `#7A7F91` for at least 3:1 contrast on admin surfaces. Admin typography is Barlow 400/500/600, bundled in `resources/fonts/` (OFL licence in `resources/fonts/OFL.txt`) and applied with `font-orders`.

## Admin compositions

- **Page shell.** `AdminPageShell` owns the 80rem maximum width, centered exterior and 24 px vertical rhythm; `AdminLayout` alone owns the gutters (20 px mobile, 24 px from 640 px). Order: optional breadcrumbs, `AdminPageHeader` (one H1), optional `AdminSectionNavigation`, content. Simple forms use `admin-form-content` (48rem). Never add another padded outer container.
- **Section navigation** uses real Inertia links with `aria-current="page"` derived from the URL (query parameters preserved), never local state; on narrow screens it scrolls inside its own container.
- **`admin-ui`** is an explicit local opt-in that adapts Button/Input/Select/Textarea to 44 px targets and local focus tokens. It is not global; portalled Dialog/Sheet content must opt in separately.
- **Panels.** Use `Card className="admin-ui admin-panel"` (12 px radius, 16/24 px padding) for grouped surfaces, `admin-panel-table` for edge-to-edge tables. Do not nest panels by default.
- **Empty states.** `AdminEmptyState` shows a short heading, explanation and caller-supplied real actions; never hard-coded metrics or fictitious data.
- **Spacing tokens:** 4/8/12/16/20/24/32 px.
- **Navigation.** `admin-sidebar.tsx` owns global destinations and active-route logic: a 104 px rail on desktop/tablet, a bottom bar (Inicio, Pedidos, Clientes, Más) on phones. Marketing and Instagram are sibling entries; `/admin/marketing` uses exact matching so the legacy nested `/admin/marketing/instagram` URL does not activate both. **Gestión** links to `/admin/management`; Inventory is its own destination.
- **Order detail reuse.** Dashboard and Customers open `features/orders/components/order-detail-sheet.tsx`, which renders the same `OrderDetailContent` as the full page; the orders list opens the compact `OrderTicketDialog`. Do not build another order detail.
- **Quick order** (`pages/admin/home.tsx`): catalog column plus a current-order panel; continue opens a Sheet with the shared order detail fields. Phones get a two-column catalog with 44 px quantity targets and a bottom sheet for the order.
- **Floor editor** (`features/restaurant/`): proportional renderer that keeps logical aspect ratio, pointer drag and resize on desktop, labeled numeric geometry controls on mobile, explicit save with dirty and conflict feedback, and an `editable=false` read-only mode.
- **Brand mark.** `components/brand-logo.tsx` centralizes `public/icons/te-pinta-logo.svg` (sidebar, auth layout, favicon); PWA and Apple touch icons use the PNG derivatives in `public/icons/`.

## Public compositions

- `PublicLayout` applies the public theme to navigation, content and footer and shows a single floating cart button (with count) on Home, menu and product detail when the cart is not empty, never on `/cart`; it respects the mobile safe area.
- The menu has a full-width hero with a decorative illustration (`public/images/brand/bg-empanada.png`, hidden from assistive technology), serif editorial headings, and a full-width sticky category bar with filters aligned to the main container.
- `FeaturedProductCard` (in `features/marketing/`) applies the public theme locally because the admin Marketing preview reuses it. It receives only the public-safe view model and uses the existing `Button`, `formatPesos` and `CartProvider`. Without an eligible recommendation, Home shows a photographic link to the menu, never a placeholder offer.

## Verification

Browser specs capture desktop and mobile screenshots into `storage/app/` (not versioned). `tests/e2e/admin-visual-primitives.spec.ts` covers the shared admin primitives.
