# Current implementation state

Te Pinta 3.0 currently has a complete ordering vertical slice: catalog and configuration feed one backend-authoritative pricing engine, and both the administrative POS and public checkout persist the same historical order snapshots.

## Ready now

| Capability        | Current behavior                                                                                                                   |
| ----------------- | ---------------------------------------------------------------------------------------------------------------------------------- |
| Products          | Categories, product CRUD, availability, images, raw prices, and cooking surcharges                                                 |
| Settings          | Combined-dozen raw/cooked prices, delivery fee, WhatsApp number, and toppings                                                      |
| Customers         | CRUD, normalized optional phone, private notes, order history, and effective spend                                                 |
| Admin orders      | Creation, search/filtering, operational list, detail drawer, full detail, status/payment changes, copy, and soft delete            |
| Public checkout   | Menu, session cart, backend preview, safe customer matching, idempotent persistence, and WhatsApp handoff                          |
| Pricing           | Unit, half-dozen, dozen, combined dozen, toppings, shipping, savings, and explicit cooking breakdown                               |
| Historical safety | Product/customer/delivery/topping/base/cooking values are snapshotted; legacy cooking is never reconstructed from current settings |

## Orders UI

- `/admin/orders` uses a Figma-aligned two-column operational workspace.
- Selecting an order opens a right drawer through `/admin/orders?order=TP-*`; direct `/admin/orders/{public_id}` remains the full detail page.
- `OrderDetailContent` is shared by drawer and full detail.
- The right column shows real most-ordered products plus separate delivery and raw/cooked distributions.
- Visible Orders copy and centralized status labels are Spanish; internal enum values remain English.
- Desktop keeps both columns at viewport height and scrolls only the report body.

## Pricing invariants

- The backend is the only authority for totals.
- `items_subtotal` remains the applied cooked/raw merchandise total.
- `items_base_subtotal + cooking_surcharge = items_subtotal` for new orders.
- Combined dozen remains `$16,000` raw and `$17,500` cooked, so cooking is `$1,500`.
- Adding the breakdown never changes the final total.

## Intentionally deferred

- The current `/admin` dashboard page remains a placeholder until the quick-order Home slice described in [ADMIN_QUICK_ORDER.md](design/ADMIN_QUICK_ORDER.md) is implemented.
- Analytics and Inventory remain route/page boundaries without invented business behavior.
- Online payment is not part of the product; checkout confirmation uses WhatsApp.

## Review path

1. Read [ARCHITECTURE.md](ARCHITECTURE.md) for module boundaries.
2. Read [ORDER_PRICING.md](ORDER_PRICING.md) before changing grouping or totals.
3. Use [PROJECT_MAP.md](PROJECT_MAP.md) to find the owner of each rule.
4. Run the gates documented in [TESTING.md](TESTING.md).
