# Design system

> **Current rule (2026-10-16):** the admin is light-only. Restaurant branding replaces the former dark/coral theme. Te Pinta defaults to `#b84f3f`; changing the restaurant accent does not require an asset rebuild.

shadcn/ui is the accessible primitive layer, not the visual identity. Public and admin experiences share token mechanics but differ in composition and density: the public site is mobile-first; admin/POS is tablet/desktop-first and must remain usable on phones.

## Layers

| Layer | Location | Rule |
| --- | --- | --- |
| Tokens and Tailwind theme | `resources/css/app.css` | Single source for color, type, spacing, radius, shadow and state decisions |
| shadcn primitives | `resources/js/components/ui/` | Low-level accessible primitives; no business behavior |
| Shared Te Pinta UI | `resources/js/components/shared/` | Reusable compositions: `admin-sidebar`, `admin-module-navigation`, `admin-page-shell`, `admin-section-navigation`, `admin-empty-state` |
| Feature UI | `resources/js/features/<feature>/components/` | Business components owned by one feature |
| Layouts | `resources/js/layouts/` | `public-layout`, `admin-layout`, `settings-layout` (full-height panel for Productos and General), `auth-layout` |

## Rules

- Consume semantic tokens (`background`, `foreground`, `primary`, `card`, `muted`, `border`, `ring`, `destructive`, `admin-*`, `public-*`), never raw colors in components. Add a token only for a reusable design decision.
- Before adding a component: check `components/ui`, then `components/shared`, then the owning feature. Add a wrapper only when it centralizes a real visual or behavioral policy. Do not duplicate buttons, dialogs, inputs, product cards, money presentation or status badges under new names.
- Preserve visible focus, contrast, keyboard navigation, reduced-motion preferences and touch targets of at least 44 px.
- Responsive behavior is recomposition, not proportional shrinking: tables become rows or cards on phones, without page-level horizontal scroll.
- Prices and totals displayed in React come from backend previews; components format, they never calculate.
- Visible text is Spanish (Argentina); enums, identifiers and code are English.

## Theme and branding contract

`restaurants.accent_color` stores a lowercase six-digit hex color. `Core/Branding/AccentPalette` validates it and derives nine `--brand-*` variables. `BrandingServiceProvider` injects the current `RestaurantContext` palette into the root HTML; shared Inertia `brand` props and `useRestaurantBranding` update the same variables after SPA navigation. Neither cookies, local storage nor the OS can enable dark mode.

Edit **Configuración → General → Color de acento** with `catalog.manage`, the same permission as General settings (active owners and managers). Both route middleware and the Form Request enforce it. The preview is local until saved; backend validation remains authoritative. Accents must contrast at least 3:1 against white for boundaries and 4.5:1 with white action labels. Text and hover shades mix 85% accent with black; pressed text uses 70%; subtle, muted and border shades mix 8%, 15% and 50% accent with white. The database checks shape; invalid direct-SQL colors fall back to the safe default when rendered.

## Tokens

Public palette (`.public-theme`, applied by `PublicLayout`):

| Token | Value | Use |
| --- | --- | --- |
| `public-canvas` | `--brand-accent` (default `#b84f3f`) | Home and navigation canvas; product cards on the menu |
| `public-earth` | `#f1f1f1` | Menu canvas |
| `public-earth-ink` | `--brand-accent-pressed` (default `#81372c`) | Small text on earth; actions on red cards |
| `public-ink` | `#1b1b1b` | Text on earth |

White text on canvas reaches 4.97:1; ink on earth 15.25:1. Brand assets live in `public/images/brand/` (wordmark in the header, monogram in the footer).

Admin roles are defined on `:root`; Tailwind v4 exposes them as semantic utilities. Shared primitives use `background`, `foreground`, `primary`, `primary-foreground`, `muted`, `border` and `ring`. Admin aliases consume the same source, including portalled dialogs and menus.

| Token | Value | Use |
| --- | --- | --- |
| `admin-canvas` | `#f7f8fa` | Workspace |
| `admin-surface` | `#ffffff` | Sidebar, panels, dialogs and cards |
| `admin-surface-raised` | `#eef1f5` | Nested sections and neutral controls |
| `admin-border` / `admin-control-border` | `#dce1e7` / `#7a8594` | Decorative / interactive boundaries |
| `admin-text-primary` / `-secondary` / `-tertiary` | `#202936` / `#4b5768` / `#596577` | Text hierarchy |
| `admin-accent` / `admin-on-accent` | Restaurant accent / white | Filled action and contrasting label |
| `admin-accent-text` / `admin-accent-hover` | Derived darker shade | Links / hovered filled actions |
| `admin-selection` / `admin-action` | Derived subtle / muted shade | Selected rows and secondary emphasis |
| `success` / `success-subtle` | `#166534` / `#e7f5ec` | Delivered, paid, active |
| `warning` / `warning-subtle` | `#854d0e` / `#fff4db` | Awaiting confirmation, attention |
| `info` / `info-subtle` | `#075985` / `#e8f2fc` | Confirmed, processing |
| `prepared` / `prepared-subtle` | `#5b21b6` / `#f0ecfc` | Prepared |
| `danger` / `danger-subtle` | `#991b1b` / `#fdecec` | Cancelled, error |
| `overlay` / `on-overlay` | `#17212b` / white | Scrims and content over photographs |
| `ticket-paper` / `ticket-ink` / `ticket-muted` / `ticket-rule` | `#fff8ed` / `#2f2925` / `#6f6259` / `#d9cbbd` | Receipt dialog |

Measured WCAG contrast ratios: default white-on-accent **4.97:1**; accent text on white **6.42:1**; status text on its own tinted background: success **6.34**, warning **6.27**, info **6.68**, prepared **7.75**, danger **7.28**. Secondary text on white reaches **7.34:1**; tertiary text on canvas **5.56:1**. Re-measure the actual rendered pair when adding roles or opacity.

`orderStatusLabels` and `orderStatusStyles` in `features/orders/presentation.ts` own the shared vocabulary: pending confirmation is **En espera**, waiting is **Confirmado**. Dashboard reuses these labels instead of maintaining another map. Chart fills, strokes and legend markers use the same semantic role colors.

Admin typography remains Barlow 400/500/600, bundled in `resources/fonts/` (OFL licence in `resources/fonts/OFL.txt`). The root uses `font-orders`; the public shell explicitly keeps `font-sans`.

## Admin compositions

- **Page shell.** `AdminPageShell` owns the 80rem maximum width, centered exterior and 24 px vertical rhythm; `AdminLayout` alone owns the gutters (20 px mobile, 24 px from 640 px). Order: optional breadcrumbs, `AdminPageHeader` (one H1), optional `AdminSectionNavigation`, content. Simple forms use `admin-form-content` (48rem). Never add another padded outer container.
- **Section navigation** uses real Inertia links with `aria-current="page"` derived from the URL (query parameters preserved), never local state; on narrow screens it scrolls inside its own container. Module tabs come from the navigation model: `AdminModuleNavigation` (rendered by `AdminLayout` above the page) shows the permitted sections of the active entry when it has two or more — Inventario (Insumos · Recetas · Producción · Ajustes de stock · Costos, with Políticas de costo inside Costos), Marketing (Producto destacado · Instagram) and Configuración (General · Plano del salón · Usuarios). Gestión still renders its own query-based sections. Breadcrumbs start at **Panel** (`adminBreadcrumbRoot`), omitted for users without `dashboard.view`.
- **`admin-ui`** is an explicit local opt-in that adapts Button/Input/Select/Textarea to 44 px targets and local focus tokens. It is not global; portalled Dialog/Sheet content must opt in separately.
- **Panels.** Use `Card className="admin-ui admin-panel"` (12 px radius, 16/24 px padding) for grouped surfaces, `admin-panel-table` for edge-to-edge tables. Do not nest panels by default.
- **Empty states.** `AdminEmptyState` shows a short heading, explanation and caller-supplied real actions; never hard-coded metrics or fictitious data.
- **Spacing tokens:** 4/8/12/16/20/24/32 px.
- **Navigation.** `features/admin-navigation/model.ts` is the single source of the admin information architecture: 9 entries in 6 groups (Operación: Vender, Pedidos, Clientes · Resumen: Panel · Catálogo: Productos, Marketing · Stock y costos: Inventario · Finanzas: Gestión · Configuración: Configuración). Each entry is visible only with the permission its route requires (Vender needs `orders.manage`; Configuración appears when any of its sections does), and each entry lists the path prefixes it absorbs for active-route matching. `admin-sidebar.tsx` renders it as a 104 px icon rail with tooltips on tablets and a 232 px sidebar with group headings and visible labels from `lg`; phones get a bottom bar (Vender, Pedidos, Panel, Más) whose sheet lists the remaining entries by group.
- **Order detail reuse.** Dashboard and Customers open `features/orders/components/order-detail-sheet.tsx`, which renders the same `OrderDetailContent` as the full page; the orders list opens the compact `OrderTicketDialog`. Do not build another order detail.
- **Quick order** (`pages/admin/home.tsx`): catalog column plus a current-order panel; continue opens a Sheet with the shared order detail fields. Phones get a two-column catalog with 44 px quantity targets and a bottom sheet for the order.
- **Floor editor** (`features/restaurant/`): proportional renderer that keeps logical aspect ratio, pointer drag and resize on desktop, labeled numeric geometry controls on mobile, explicit save with dirty and conflict feedback, and an `editable=false` read-only mode.
- **Brand mark.** `components/brand-logo.tsx` applies restaurant colors to the monochrome `public/icons/te-pinta-mark.svg` mask (sidebar and auth). The favicon uses the default-accent `te-pinta-logo.svg`; PWA and Apple touch PNG assets remain static.
- The split-auth brand panel intentionally remains `bg-zinc-900` in both color schemes; its logo and restaurant name use fixed white text for readable contrast.

## Public compositions

- `PublicLayout` applies the public theme to navigation, content and footer and shows a single floating cart button (with count) on Home, menu and product detail when the cart is not empty, never on `/cart`; it respects the mobile safe area.
- The menu has a full-width hero with a decorative illustration (`public/images/brand/bg-empanada.png`, hidden from assistive technology), serif editorial headings, and a full-width sticky category bar with filters aligned to the main container.
- `FeaturedProductCard` (in `features/marketing/`) applies the public theme locally because the admin Marketing preview reuses it. It receives only the public-safe view model and uses the existing `Button`, `formatPesos` and `CartProvider`. Without an eligible recommendation, Home shows a photographic link to the menu, never a placeholder offer.

## Verification

Browser specs capture desktop and mobile screenshots into `storage/app/` (not versioned). `tests/e2e/admin-visual-primitives.spec.ts` covers the shared admin primitives. `tests/e2e/light-theme.spec.ts` checks legacy dark preferences, rendered status contrast, local preview, persistence across reload/public navigation and backend error feedback on desktop/mobile. PHP branding tests cover palette derivation, validation, current-restaurant isolation and root/Inertia variables.
