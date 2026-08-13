# Current implementation state

Te Pinta 3.0 currently has a complete ordering vertical slice: catalog and configuration feed one backend-authoritative pricing engine, and both the administrative POS and public checkout persist the same historical order snapshots.

## Ready now

| Capability        | Current behavior                                                                                                                           |
| ----------------- | ------------------------------------------------------------------------------------------------------------------------------------------ |
| Products          | Categories, product CRUD, availability, images, raw prices, and cooking surcharges                                                         |
| Settings          | Combined-dozen raw/cooked prices, delivery fee, WhatsApp number, and toppings                                                              |
| Customers         | CRUD, normalized optional phone, private notes, order history, and effective spend                                                         |
| Admin orders      | Creation, safe full editing, search/filtering, operational list, detail drawer, full detail, status/payment changes, copy, and soft delete |
| Admin Home        | Product-first Quick Order POS with local draft, backend preview, continuation sheet, and final confirmation                                |
| Public checkout   | Menu, session cart, backend preview, safe customer matching, idempotent persistence, and WhatsApp handoff                                  |
| Pricing           | Unit, half-dozen, dozen, combined dozen, toppings, shipping, savings, and explicit cooking breakdown                                       |
| Historical safety | Product/customer/delivery/topping/base/cooking values are snapshotted; legacy cooking is never reconstructed from current settings         |

## Orders UI

- `/admin` is the operational Home: catalog cards increment real empanada quantities, while the fixed order panel renders the authoritative backend preview.
- **Continuar** opens the customer/condition/addons/fulfillment/payment sheet without persisting. Only **Confirmar pedido** calls the existing admin order store and `CreateOrder` boundary.
- The same draft hook and field components power `/admin/orders/create`, which remains the complete fallback flow. Dashboard is available at `/admin/dashboard`.
- `/admin/orders` uses a Figma-aligned two-column operational workspace.
- Selecting an order opens a right drawer through `/admin/orders?order=TP-*`; direct `/admin/orders/{public_id}` remains the full detail page.
- `OrderDetailContent` is shared by drawer and full detail.
- Both detail surfaces link to `/admin/orders/{public_id}/edit`. Active orders open the shared full form directly; delivered/cancelled orders require an explicit, reasoned, version-bound single-edit grant and keep their existing status.
- Edit hydration reconstructs real product/addon quantities from structured item, composition, and topping rows. It never parses display labels such as “combined dozen.” The page shows the persisted total and the current backend preview with their difference before confirmation.
- Economic edits rerun the existing pricing engine under current catalog/settings and atomically replace snapshots for that order only. Non-economic corrections retain the historical quote without unnecessary repricing.
- The right column shows real most-ordered products plus separate delivery and raw/cooked distributions.
- Visible Orders copy and centralized status labels are Spanish; internal enum values remain English.
- Desktop keeps both columns at viewport height and scrolls only the report body.

## Pricing invariants

- The backend is the only authority for totals.
- `items_subtotal` remains the applied cooked/raw merchandise total.
- `items_base_subtotal + cooking_surcharge = items_subtotal` for new orders.
- Combined dozen remains `$16,000` raw and `$17,500` cooked, so cooking is `$1,500`.
- Adding the breakdown never changes the final total.
- A confirmed economic edit replaces the edited order's quote snapshots using current rules. Merely opening an order, cancelling, or editing only non-economic fields does not rewrite pricing history.

## Intentionally deferred

- Analytics and Inventory remain route/page boundaries without invented business behavior.
- Online payment is not part of the product; checkout confirmation uses WhatsApp.

## Review path

1. Read [ARCHITECTURE.md](ARCHITECTURE.md) for module boundaries.
2. Read [ORDER_PRICING.md](ORDER_PRICING.md) before changing grouping or totals.
3. Use [PROJECT_MAP.md](PROJECT_MAP.md) to find the owner of each rule.
4. Run the gates documented in [TESTING.md](TESTING.md).
