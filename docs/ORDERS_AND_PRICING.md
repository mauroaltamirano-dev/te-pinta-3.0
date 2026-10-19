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

- Creating an order persists the full quote: line name, pricing mode, block count, units, applied price, cooked reference price, raw discount, regular price and savings (`order_items`); combined-dozen parts of version-1 orders (`order_item_compositions`, none for new orders); add-ons (`order_toppings`); and on `orders` the customer name/phone, address, shipping, subtotals, savings, discount and total.
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
| Empanada dozen `D` | `products.dozen_price`, the only empanada price an operator enters |
| Empanada unit `U` | `products.unit_price`, **stored**: `U = D / 12 × (1 + p%)` rounded half-up to ARS 100, `p` = `order_settings.empanada_unit_surcharge_percent` (seed 10) |
| Raw (cook at home) | `D_raw = D − raw_price_discounts.dozen`; `U_raw` = same formula on `D_raw`, at quote time |
| `unit` product | `products.unit_price`, entered by the operator |
| Add-on | Current `order_addons.price` of an available add-on |
| Shipping | `delivery_fee` for `delivery`; 0 otherwise |

`U` is recalculated only when `D` changes (product create/edit, or switching a product to `empanada`) or when `p` changes in Configuración (every empanada, soft-deleted included, `RecalculateEmpanadaUnitPrices`). It is never recalculated per order. Product requests reject `unit_price` for empanadas; the editor shows it read-only. `EmpanadaUnitPrice` (`app/Modules/Products/Pricing/`) is the single formula.

Retired inputs (rows kept, never read): `order_settings.combined_dozen_cooked_price` / `combined_dozen_raw_price`, `raw_price_discounts.unit` / `half_dozen`. `half_dozen_price` is still stored and edited but does not price orders.

### Pricing families

Each product has an explicit `pricing_family`: `empanada` or `unit`. It is never inferred from category, slug or name. `unit` products are priced `quantity × unit_price` with no grouping and no raw discount.

### Fixed dozen algorithm (empanada products)

Per flavor, independently: every 12 units are one `dozen` line at `D`; the remainder is a `unit` line at `U`. There is no combined dozen, no half-dozen tier and no cap — 11 units can cost more than the dozen, on purpose (the restaurant explains it to the customer).

Required examples (D: dulce 20000, JyQ 22000, salteña 18000 → U 1800, 2000, 1700), `tests/Feature/Orders/FixedDozenOrderPricingTest.php`:

| Basket | Total |
| --- | ---: |
| 12 dulce | 20000 |
| 11 dulce | 19800 |
| 10 dulce | 18000 |
| 13 dulce | 21800 |
| 24 dulce | 40000 |
| 11 dulce + 1 JyQ | 21800 |
| 6 dulce + 3 JyQ + 3 salteña | 21900 |
| 11 salteña | 18700 |
| Raw dulce: dozen / unit | 18000 / 1700 |

### Admin discounts and totals

Available only in authenticated admin create, edit and quick order. Types: `percentage` (integer 0–100) or `fixed_amount` (integer pesos); type and value are sent together.

```text
discountable subtotal = products subtotal + add-ons subtotal     (shipping excluded)
discount              = min(exact percentage | fixed amount, discountable subtotal)
net                   = discountable subtotal − discount + shipping
total                 = net rounded once, half-up, to ARS 100     (ExactOrderTotals)
rounding adjustment   = total − net                                (−49 … +50 for a whole-peso net)
```

The percentage discount is kept exact (numerator over 1200) until the single rounding; `discount_amount` stores it rounded to pesos for display. Previews, WhatsApp and admin summaries show the adjustment as a `Redondeo` line. Products subtotal = cooked products subtotal − raw discount.

### Snapshot versions

| Version | Orders | Rules |
| --- | --- | --- |
| 1 | Created before fixed dozen pricing | Original columns only; their constraints are kept verbatim and nothing is recalculated. |
| 2 | Every new quote (`PersistOrderQuote`) | Whole-peso lines whose exact numerators are 12 × the pesos (`applied/cooked/raw_discount_exact_numerator_12`, surcharge 0); order numerators for products, discount (over 1200), pre-round net and rounding adjustment. Database checks (`2026_10_19_000000_activate_fixed_dozen_pricing`) only accept `unit`/`dozen` lines with a product and `total = net + adjustment`. |

Rollback of that migration refuses once version-2 history exists; it never rewrites unit prices back.

### Limits and errors

At least one product; at most 50 distinct products per request, each once, quantity 1–120; at most 20 add-on rows, each once, quantity 1–100. Unavailable, deleted or inactive-category selections fail. Public selections use slugs; admin selections use validated internal IDs. Invalid price configuration (raw dozen discount missing or above a flavor's dozen) and requests above the limits return validation errors, never a server error.

## Derived pricing classes (P0-A, inactive)

`Pricing/DerivedTierAllocator.php` and `Pricing/DerivedPricingCalculator.php` (formula `Σ q·D/12 + R_combined` with homogeneous and mixed sixes) have **no production caller** and are not the live model; `order_settings.combined_half_dozen_surcharge` only feeds them and is no longer shown in Configuración. Their tests stay in `tests/Unit/Orders/DerivedPricingFoundationTest.php`. Of that foundation, only `ExactOrderTotals` and the reserved snapshot columns are used, by version 2.

## Admin quick order

`/admin` is the quick-order screen ([ADR 0001](decisions/0001-admin-home-is-quick-order.md)); `/admin/orders/create` is the full-page alternative.

- Product cards add 12, 6 or 1 unit to an in-memory draft; nothing is persisted until final confirmation.
- Both screens share `features/orders/hooks/use-admin-order-draft.ts` (draft state, preview sequencing, stale-response protection, submit lock), `admin-order-details-fields.tsx` and `PriceSummary`, which calls `POST /admin/orders/price-preview`.
- Confirmation posts to `POST /admin/orders` (`StoreAdminOrderRequest` → `CreateOrder`). Quick order receives a JSON confirmation with the backend-generated ticket; the full form receives an Inertia redirect.

## Change checklist

1. Change line building only in `OrderPricingEngine` and keep `tests/Feature/Orders/FixedDozenOrderPricingTest.php` (required examples) green.
2. Keep product price access behind `ProductPriceProvider` and the unit formula in `EmpanadaUnitPrice` (mirrored by the migration SQL and `features/products/utils.ts` for display).
3. Never recalculate stored snapshots; a new persisted shape needs a new snapshot version, constraints and tests.
4. Update this document.
