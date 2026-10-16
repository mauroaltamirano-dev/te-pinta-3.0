# Orders and pricing

`Orders` owns the order: creation, editing, snapshots, revisions, operational status and payment status. Prices come from `Products` through `ProductPriceProvider`; money effects belong to `Management` ([FINANCE.md](FINANCE.md)); physical consumption belongs to `Inventory` ([INVENTORY.md](INVENTORY.md)).

Code: `app/Modules/Orders/`. Key entry points: `Actions/CreateOrder.php`, `Actions/UpdateOrder.php`, `Actions/UpdateOrderStatus.php`, `Actions/UpdateOrderPaymentStatus.php`, `Pricing/OrderPricingEngine.php`.

## Sources and fulfillment

`source` (how the order was created) and `delivery_type` (fulfillment) are independent snapshots.

| `source` | Created by | Initial status |
| --- | --- | --- |
| `admin` | Admin quick order (`/admin`) or full form (`/admin/orders/create`) | `waiting` |
| `public_checkout` | Public storefront `POST /checkout/orders` | `pending_confirmation` |
| `pos` | Internal `CreateOrder` command only; no HTTP endpoint yet | `waiting` |

| Context | Customer / contact | Address | Schedule | Shipping |
| --- | --- | --- | --- | --- |
| Public delivery | Name and phone required | Required | Date and period required; date not in the past (business date) | `delivery_fee` setting |
| Public pickup | Name and phone required | Not required | As above | 0 |
| Admin delivery | Existing customer or new name; phone optional | Required | Date and period; past dates allowed (historical entry) | `delivery_fee` setting |
| Admin pickup | Existing customer or new name; phone optional | Not required | As above | 0 |
| POS `dine_in` | Customer, name and phone optional; no profile is created | Prohibited | Date, period and time null | 0 |

Public checkout and admin forms reject `dine_in` and never accept a client-supplied `source`. Delivery periods: `midday`, `afternoon`, `night`; `delivery_time` is optional.

## Lifecycle

| Status | Meaning |
| --- | --- |
| `pending_confirmation` | Public order not yet confirmed by staff |
| `waiting` | Confirmed, not yet prepared |
| `prepared` | Ready |
| `delivered` | Handed over; triggers inventory consumption |
| `cancelled` | Cancelled; stays in history |

Rules enforced in `Orders/Support/OrderPhysicalFulfillment.php`:

- Status updates accept any enum value, except that a `delivered` order can only move to `cancelled`.
- The first transition to `delivered` emits `OrderDelivered`; `delivered → cancelled` emits `OrderDeliveryCancelled`. Both run synchronously in the order transaction ([INVENTORY.md](INVENTORY.md#order-consumption)).
- An order whose delivery consumption was reversed cannot be delivered again; create a new order.
- `delivered` and `cancelled` orders are "closed": editing requires `POST /admin/orders/{order}/enable-edit` with a 3–300 character reason. The permission is stored in the admin session, bound to the order and its `edit_version`, and consumed by one successful edit.

Payment status: `unpaid`, `paid`, `not_required`. Methods: `cash`, `bank_transfer`, `debit_card`. `paid` requires a method, `unpaid` forbids one, and a backend-computed zero total becomes `not_required` with no method. Public checkout cannot set payment status, method or discount.

## Snapshots and history

- Creating an order persists the full quote: line name, pricing mode, block count, units, applied price, cooked reference price, raw discount, regular price and savings (`order_items`); combined-dozen parts (`order_item_compositions`); add-ons (`order_toppings`); and on `orders` the customer name/phone, address, shipping, subtotals, savings, discount and total.
- Opening an order never re-runs pricing. Changes to products, customers, add-ons or settings never alter existing orders.
- Legacy fields `items_base_subtotal`, `cooking_surcharge`, `base_unit_price`, `base_line_total` are kept only to read old orders; new orders leave them null. New fields stay null on old orders.
- `order_revisions` records each successful edit: admin, status, totals before/after, names of changed fields with an economic flag, optional closed-edit reason. It never stores phone, address or note values.
- Every full edit and every status or payment change increments `orders.edit_version`. A full edit must send `expected_version`; stale submissions are rejected before any change.

### Editing

`UpdateOrder` shares `OrderCheckoutData`, `ResolveOrderCustomer`, `OrderPricingEngine` and `PersistOrderQuote` with creation. An **economic** change (products, quantities, condition, add-ons, delivery type, discount) re-prices the whole order with current rules and replaces only that order's snapshots, atomically. Non-economic changes (customer, address, schedule, notes, payment, status) keep the stored quote.

An economic edit is rejected when:

- the order is `paid` (reverse the financial movement first); or
- inventory consumption evidence exists for the order (including an explicit no-inventory delivery); or
- the selection still contains an unavailable or deleted product or add-on (historical selections remain visible and survive non-economic edits, but must be removed before re-pricing).

### Deletion

Orders are soft-deleted (`DELETE /admin/orders/{order}`), listed in `/admin/orders/trash` and restored with the same row, public ID, snapshots and revisions. There is no force delete.

## Payments (current model)

One order has at most one financial payment.

```text
Order payment_status -> paid
  -> OrderPaymentConfirmed(orderId, amount, confirmedOn, adminId)   (synchronous, same transaction)
  -> ManagementOrderPayment (unique per order_id) + FinancialTransaction(order_payment) + one positive LedgerEntry
  -> ManagementDistribution, if an active distribution rule exists
```

| Path that emits the event | When |
| --- | --- |
| `CreateOrder` (admin) | The new order is `paid` with a positive total |
| `UpdateOrder` | The locked order moves from a non-paid status to `paid` |
| `UpdateOrderPaymentStatus` (`PATCH /admin/orders/{order}/payment-status`) | Same transition from the quick control |

- `confirmedOn` is the business date at confirmation, not `created_at` or `delivery_date`.
- If Management cannot record the payment (for example, no active default wallet), the whole transition to `paid` fails.
- Moving back to `unpaid` or cancelling does not reverse the payment. Financial corrections use `ReverseFinancialTransaction` in Management; a reversed payment is not recreated by paying again.
- Orders marked `paid` before the Management integration have no payment record and are not backfilled.
- Not supported: partial or multiple payments per order, payment gateways, automatic reversal.

## Live pricing engine

`OrderPricingEngine::quote()` is the only authoritative calculator. Admin preview (`POST /admin/orders/price-preview`), public preview (`POST /checkout/price-preview`), creation and economic edits all call it. React only renders backend previews.

### Inputs and price sources

The engine receives final quantities per product, one condition for the whole order (`cooked` or `raw`), add-on quantities, delivery type and an optional admin discount type/value. It never receives prices or a discount amount from the client.

| Value | Source |
| --- | --- |
| Cooked unit / half-dozen / dozen price | `products.unit_price`, `half_dozen_price`, `dozen_price` via `ProductPriceProvider` |
| Raw price | Cooked price minus the matching positive `raw_price_discounts` tier |
| Combined dozen | `order_settings.combined_dozen_cooked_price` / `combined_dozen_raw_price` (independent values) |
| Add-on | Current `order_addons.price` of an available add-on |
| Shipping | `delivery_fee` for `delivery`; 0 otherwise |

### Pricing families

Each product has an explicit `pricing_family`: `empanada` or `unit`. It is never inferred from category, slug or name. `unit` products are priced `quantity × unit_price` with no grouping and no raw discount. Only `empanada` products participate in grouping.

### Grouping algorithm (empanada products)

1. Sort products by internal ID.
2. Take every full dozen of the same product at its dozen price.
3. While at least 12 units remain across more than one product, take one combined dozen, filling it in product-ID order.
4. Take half-dozens of the same product from what remains.
5. Price the rest as units.

There is no mixed half-dozen in the live engine.

| Requested | Lines |
| --- | --- |
| 5 A | 5 units A |
| 6 A | 1 half-dozen A |
| 7 A | 1 half-dozen A + 1 unit A |
| 12 A | 1 dozen A |
| 18 A | 1 dozen A + 1 half-dozen A |
| 3 A + 3 B | 3 units A + 3 units B |
| 6 A + 3 B + 3 C | 1 combined dozen |
| 6 A + 6 B | 1 combined dozen |
| 24 mixed | 2 combined dozens |

Combined-dozen savings: `max(0, normal price of the same composition − combined price)`; a combined price above the normal price never shows a negative saving.

### Admin discounts and totals

Available only in authenticated admin create, edit and quick order. Types: `percentage` (integer 0–100) or `fixed_amount` (integer pesos); type and value are sent together.

```text
discountable subtotal = items subtotal + add-ons subtotal     (shipping excluded)
discount amount       = min(percentage rounded half-up to pesos | fixed amount, discountable subtotal)
total                 = discountable subtotal − discount amount + shipping
```

For new orders `items_subtotal = products_cooked_subtotal − raw_discount`.

### Limits

At least one product; at most 50 products per request, each once, quantity 1–120; at most 20 add-on rows, each once, quantity 1–100. Unavailable, deleted or inactive-category selections fail. Public selections use slugs; admin selections use validated internal IDs.

## Derived pricing foundation (P0-A, inactive)

A replacement pricing model is implemented as pure classes and schema but **has no production caller**: `Pricing/DerivedTierAllocator.php`, `Pricing/DerivedPricingCalculator.php`, `Pricing/ExactOrderTotals.php`, migration `2026_10_15_000000_prepare_derived_pricing_foundation.php`, tests in `tests/Unit/Orders/DerivedPricingFoundationTest.php`. Checkout, quick order, persistence and costing still use the live engine. Activating it (P0-B) requires wiring into the shared engine, cooked/raw snapshots, version-aware database checks, preview/persist equality and regression of inventory and costing.

Rules as implemented:

- Only `empanada` products are allocated: `floor(N/12)` dozens, one six-pack when `N mod 12 ≥ 6`, then `N mod 6` units. 18 is a dozen plus a six, never three sixes.
- A dozen slot charges each member `D/12` with no surcharge.
- A homogeneous six charges the product's `H` (`products.half_dozen_price`). Its surcharge is derived as `R = (2H − D)/2`; there is no surcharge column. The admin product editor accepts either `H` or `R` (never both) and previews `H` through `POST /admin/settings/products/half-dozen-preview`.
- A mixed six (at least two flavors) charges `sum(q × D/12) + R_combined`, where `R_combined` is `order_settings.combined_half_dozen_surcharge` (seed 1500).
- The allocator searches homogeneous and mixed six candidates (mixed states keep flavor identity) and picks the cheapest; ties keep homogeneous-first, product-ID order.
- Arithmetic is exact: numerators over 12, discounts over 1200; `ExactOrderTotals` rounds once, half-up, to the nearest peso. Display projections use largest remainder and never feed pricing.
- Tier eligibility in the selected condition: dozen `D < 12U`; homogeneous six `H < 6U` with non-negative `R`; each mixed flavor `D + 2R_combined < 12U`. After rounding the basket must still cost less than its unit baseline; otherwise the quote is rejected (no silent fallback).
- Raw compatibility keeps existing discounts authoritative: `U_raw = U − discount_unit`, `D_raw = D − discount_dozen`, `H_raw = H − discount_half`, `R_raw = R + discount_dozen/2 − discount_half`. Mixed sixes use raw `D` plus `R_combined` once.
- The migration reserves `pricing_snapshot_version` (default 1) and exact numerator columns with shape checks, and extends the inventory-evidence guard to every new economic field. Rollback refuses once versioned history exists.

Worked fixtures (cooked; Matambre 2200/13000/22000, Caprese 1800/10500/18000, Salteña 2100/12000/20000, Pollo 2000/11500/19000, Humita 1800/10500/18000 as U/H/D; `R_combined` 1500):

| Basket | Total |
| --- | ---: |
| 6 Matambre | 13000 |
| 3 Matambre + 3 Caprese | 11500 |
| 3 Matambre + 2 Salteña + 1 Caprese | 11833 (exact 142000/12) |
| 2 Matambre + 2 Humita + 2 Pollo + 2 Salteña | 15267 |
| 12 Matambre + 6 Caprese | 32500 |
| 8 Matambre + 8 Salteña + 8 Caprese | 40000 |

Before raw activation the owner must correct prices or surcharges that make raw presentations invalid (for example the seeded Caprese and Humita raw mixed shares are not below their raw unit price with `R_combined` 1500).

## Admin quick order

`/admin` is the quick-order screen ([ADR 0001](decisions/0001-admin-home-is-quick-order.md)); `/admin/orders/create` is the full-page alternative.

- Product cards add 12, 6 or 1 unit to an in-memory draft; nothing is persisted until final confirmation.
- Both screens share `features/orders/hooks/use-admin-order-draft.ts` (draft state, preview sequencing, stale-response protection, submit lock), `admin-order-details-fields.tsx` and `PriceSummary`, which calls `POST /admin/orders/price-preview`.
- Confirmation posts to `POST /admin/orders` (`StoreAdminOrderRequest` → `CreateOrder`). Quick order receives a JSON confirmation with the backend-generated ticket; the full form receives an Inertia redirect.

## Change checklist

1. Change grouping only in `OrderPricingEngine` and update `tests/Feature/Orders/OrderPricingEngineTest.php`.
2. Keep product price access behind `ProductPriceProvider`.
3. Update snapshot and constraint tests when persistence changes.
4. Update this document.
