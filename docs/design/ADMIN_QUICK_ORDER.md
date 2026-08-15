# Admin Home quick-order implementation

The administrative Home at `/admin` is the fastest path to create an order. Dashboard remains available at `/admin/dashboard`; Home is the product-first POS.

## Target experience

```text
Sidebar | Product catalog | Current order
                         └─ Continue → customer and fulfillment panel
```

1. Home lists the real available product varieties with 12, 6, and 1-unit shortcuts.
2. Each shortcut increments that product in an in-progress administrative order.
3. The fixed right column shows lines, quantities, applied preview prices, savings, and subtotal.
4. **Continuar** opens the second-stage panel over a darkened catalog.
5. That stage captures the existing admin fields: customer, toppings, raw/cooked condition, delivery/pickup, date/period/time/address, notes, optional discount, and payment status/method.
6. Confirming calls the existing administrative order creation boundary; cancelling returns without persisting an order.

## Visual reference

| Element           | Reference decision                                                     |
| ----------------- | ---------------------------------------------------------------------- |
| Frame             | 1194 × 834, canvas `#252836`                                           |
| Sidebar           | Existing global 104px rail; do not duplicate it inside the page        |
| Catalog column    | Reference width 633px after the rail, 24px top/side rhythm             |
| Order column      | 409px wide, 24px padding, `#1F1D2B`, 8px radius                        |
| Product card      | 192 × 260; body begins 34px from top; `#1F1D2B`; 16px radius           |
| Product artwork   | 132 × 132 circular image, centered at the top of the card              |
| Product rows      | Three cards per 632px reference row with 28px gaps                     |
| Order action      | 361 × 48 coral button with the existing accent shadow                  |
| Stage-two overlay | `rgba(0,0,0,.7)` over the catalog; panel enters from the right         |
| Stage-two panel   | Reference popup is 814px wide and split into two 361px content columns |

Primary screenshots:

- `docs/design/reference/Captura de pantalla 2026-08-13 131709.png` — Home/product selection.
- `docs/design/reference/Captura de pantalla 2026-08-13 132047.png` — second-stage overlay.

The CSS exported by the user is measurement evidence, not production CSS. Implement with responsive layout and semantic tokens rather than absolute-positioning the full page.

## Architecture constraints

- Reuse `CreateOrder`, the admin price-preview endpoint, validation, snapshots, and pricing DTOs.
- Extract reusable form state/content from the current admin create page instead of building a second order form.
- Product cards may display catalog prices, but selected-line grouping and totals must come from backend preview.
- Do not create an order on product click; persistence happens only after final confirmation.
- Payment method and discount controls reuse the same typed draft, validation, and backend preview used by full Create/Edit; the visual template never defines domain values or totals.
- Preserve `/admin/orders/create` as a direct/fallback full-page creation route.
- Use real product categories, images, names, availability, and prices; never copy template dishes.
- Keep Home draft state local to the administrative flow unless an explicit persistence requirement is added.

## Implemented ownership

| Responsibility                                                             | Owner                                                                    |
| -------------------------------------------------------------------------- | ------------------------------------------------------------------------ |
| In-memory draft, quantity/addon mutations, preview sequencing, submit lock | `features/orders/hooks/use-admin-order-draft.ts`                         |
| Shared customer, condition, addons, fulfillment, notes, and payment fields | `features/orders/components/admin-order-details-fields.tsx`              |
| Backend economic breakdown                                                 | shared `PriceSummary` calling `POST /admin/orders/price-preview`         |
| Final persistence                                                          | existing `POST /admin/orders` → `StoreAdminOrderRequest` → `CreateOrder` |
| Quick presentation                                                         | `pages/admin/home.tsx` plus Quick Order catalog/current-order components |
| Full fallback presentation                                                 | `pages/admin/orders/create.tsx` using the same hook and shared fields    |

The store negotiates a JSON confirmation for Quick Order and preserves the Inertia redirect for full create. Both responses execute the same validated transaction. The JSON confirmation includes the backend administrative copy ticket so the success state can copy it and open the persisted Order ID without rebuilding detail in React. Closing/cancelling only changes React state; explicit discard resets that state; neither performs a persistence request.

## Navigation and state

- `/admin` is the canonical quick-order entry.
- Stage one and stage two are states of the same draft, not separate persisted orders.
- Browser back/close must dismiss stage two without losing selected lines; leaving the flow may request confirmation when the draft is non-empty.
- Desktop/tablet keeps the full POS split. On mobile, the catalog uses two columns, the order action remains above the bottom navigation, and the order/details panels may occupy the viewport as sheets.

## Acceptance checklist

- [x] Product shortcuts add 12, 6, or 1 units and update the backend preview.
- [x] Customer matches appear while typing and can be selected immediately.
- [x] Delivery reuses a selected customer's address without replacing a per-order edit.
- [x] Quantities can be changed and lines removed from the order panel.
- [x] Continue is unavailable for an empty draft.
- [x] The details stage reuses every field and rule from admin order creation.
- [x] Raw/cooked base and cooking surcharge remain explicit without changing total.
- [x] Percentage/fixed discounts preview through the backend, exclude shipping, and remain capped to the eligible subtotal.
- [x] Paid requires cash, transfer, or debit; returning to unpaid clears the method.
- [x] Cancel never persists an order; confirm persists exactly one.
- [x] Success can copy the backend ticket or open the persisted order.
- [x] Direct `/admin/orders/create` still works.
- [x] Keyboard, tablet, mobile, long-name, missing-image, and long-order states are covered by responsive/accessibility behavior and browser tests.
- [x] Mobile renders two product cards per row and keeps primary navigation inside the thumb zone.
- [x] Playwright captures stage one and stage two for comparison with the supplied screenshots.
