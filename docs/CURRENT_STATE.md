# Current implementation state

Te Pinta 3.0 currently has a complete ordering vertical slice: catalog and configuration feed one backend-authoritative pricing engine, and both the administrative POS and public checkout persist the same historical order snapshots.

## Ready now

| Capability        | Current behavior                                                                                                                   |
| ----------------- | ---------------------------------------------------------------------------------------------------------------------------------- |
| Products          | Categories, product CRUD, availability, images, raw prices, and cooking surcharges                                                 |
| Settings          | Combined-dozen raw/cooked prices, delivery fee, WhatsApp number, and toppings                                                      |
| Customers         | CRUD, normalized optional phone, private notes, order history, effective spend, soft delete, trash, and safe restore               |
| Admin orders      | Creation, safe full editing, search/filtering, quick status/payment controls, ticket preview/copy, discounts, trash, and restore   |
| Admin Home        | Product-first Quick Order POS with a two-column mobile catalog, local draft, backend preview, continuation sheet, and confirmation |
| Public checkout   | Menu, session cart, backend preview, safe customer matching, idempotent persistence, and WhatsApp handoff                          |
| Pricing           | Unit, half-dozen, dozen, combined dozen, toppings, shipping, savings, discounts, and explicit cooking breakdown                    |
| Historical safety | Product/customer/delivery/topping/base/cooking values are snapshotted; legacy cooking is never reconstructed from current settings |
| Mobile access     | Installable admin manifest and icons, without a service worker; isolated production-like runtime prepared on loopback `8082`       |

## Orders UI

- `/admin` is the operational Home: catalog cards increment real empanada quantities, while the fixed order panel renders the authoritative backend preview.
- **Continuar** opens the customer/condition/addons/fulfillment/payment sheet without persisting. Only **Confirmar pedido** calls the existing admin order store and `CreateOrder` boundary.
- The same draft hook and field components power `/admin/orders/create`, which remains the complete fallback flow. Dashboard is available at `/admin/dashboard`.
- `/admin/orders` uses a Figma-aligned two-column operational workspace.
- Selecting an order opens a compact receipt dialog through `/admin/orders?order=TP-*` without leaving the filtered list. It exposes only Copy, Edit, and close; direct `/admin/orders/{public_id}` remains the complete detail page.
- The list changes operational status through the existing typed endpoint. Payment is a binary switch for positive totals; turning it on requires a payment method and turning it off clears the method. A zero-total order is displayed as **No requiere pago** instead of recording a false payment.
- The full detail keeps `OrderDetailContent`; the compact dialog and Quick Order success reuse the same backend `formatAdmin` summary and clipboard action instead of rebuilding copy text in React.
- Both detail surfaces link to `/admin/orders/{public_id}/edit`. Active orders open the shared full form directly; delivered/cancelled orders require an explicit, reasoned, version-bound single-edit grant and keep their existing status.
- Edit hydration reconstructs real product/addon quantities from structured item, composition, and topping rows. It never parses display labels such as “combined dozen.” The page shows the persisted total and the current backend preview with their difference before confirmation.
- Economic edits rerun the existing pricing engine under current catalog/settings and atomically replace snapshots for that order only. Non-economic corrections retain the historical quote without unnecessary repricing.
- The right column shows real most-ordered products plus separate delivery and raw/cooked distributions.
- Visible Orders copy and centralized status labels are Spanish; internal enum values remain English.
- Desktop keeps both columns at viewport height and scrolls only the report body.
- Mobile uses a thumb-reachable bottom navigation with primary destinations plus a “Más” sheet; Quick Order renders two product cards per row with 44px quantity targets.

## Administrative lifecycle

- Orders and customers use recoverable soft deletion. Their trash pages are administrator-only and expose restore, not permanent deletion.
- Restoring an order preserves its `public_id` and snapshots. Deleting or restoring a customer never cascades to orders.
- A customer restore is rejected without mutation when another active customer already owns the same normalized phone; the administrator must resolve that active record and retry. Records are never silently merged or stripped of phone data.
- Payment methods are `cash`, `bank_transfer`, and `debit_card`. A paid order requires one; an unpaid order stores none. Backend-calculated zero totals use `not_required` with no method. Public checkout cannot submit discounts or payment methods.

## Pricing invariants

- The backend is the only authority for totals.
- `items_subtotal` remains the applied cooked/raw merchandise total.
- `items_base_subtotal + cooking_surcharge = items_subtotal` for new orders.
- Combined dozen remains `$16,000` raw and `$17,500` cooked, so cooking is `$1,500`.
- Adding the breakdown never changes the final total.
- Administrative percentage or fixed-amount discounts apply to items (including cooking) plus toppings, never shipping. The backend caps them at that discountable subtotal.
- A confirmed economic edit replaces the edited order's quote snapshots using current rules. Merely opening an order, cancelling, or editing only non-economic fields does not rewrite pricing history.

## Runtime status

- The admin surface is installable from its stable HTTPS origin with `start_url: /admin`, standalone display, icons, and a create-order shortcut. There is deliberately no service worker or offline order cache.
- `deploy/tailscale` can build and stage a production-like image on `127.0.0.1:8082`, verify built assets, public product media and health, and perform confirmation-gated cutover or rollback.
- Tailscale Serve now exposes the verified Te Pinta 3.0 runtime at `https://tuki-notebook.tail2b1906.ts.net` through `127.0.0.1:8082`. Te Pinta 2.0 remains reachable locally on `127.0.0.1:8080` as the tested rollback target.
- Order calendar validation uses the configured Argentina business timezone (`America/Argentina/Buenos_Aires` by default); application timestamps and database storage remain UTC.

## Intentionally deferred

- Analytics and Inventory remain route/page boundaries without invented business behavior.
- Online payment is not part of the product; checkout confirmation uses WhatsApp.

## Review path

1. Read [ARCHITECTURE.md](ARCHITECTURE.md) for module boundaries.
2. Read [ORDER_PRICING.md](ORDER_PRICING.md) before changing grouping or totals.
3. Use [PROJECT_MAP.md](PROJECT_MAP.md) to find the owner of each rule.
4. Run the gates documented in [TESTING.md](TESTING.md).
