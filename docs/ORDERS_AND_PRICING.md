# Orders and pricing

`Orders` owns the order: creation, editing, snapshots, revisions, operational status and payment status. Prices come from `Products` through `ProductPriceProvider`; money effects belong to `Management` ([FINANCE.md](FINANCE.md)); physical consumption belongs to `Inventory` ([INVENTORY.md](INVENTORY.md)).

Code: `app/Modules/Orders/`. Key entry points: `Actions/CreateOrder.php`, `Actions/UpdateOrder.php`, `Actions/UpdateOrderStatus.php`, `Actions/UpdateOrderPaymentStatus.php`, `Pricing/OrderPricingEngine.php`.

## Sources and fulfillment

`source` (how the order was created) and `delivery_type` (fulfillment) are independent snapshots.

| `source` | Created by | Initial status |
| --- | --- | --- |
| `admin` | Admin quick order (`/admin`) or full form (`/admin/orders/create`) | `waiting` |
| `public_checkout` | Public storefront `POST /checkout/orders` | `pending_confirmation` |
| `pos` | Table service (`ConfirmTableRound`, first round of a table), counter orders (`POST /admin/orders` with `delivery_type=dine_in`, "Mostrador" in the quick order) and the internal `CreateOrder` command | `waiting` |

| Context | Customer / contact | Address | Schedule | Shipping |
| --- | --- | --- | --- | --- |
| Public delivery | Name and phone required | Required | Date and period required; date not in the past (business date) | `delivery_fee` setting |
| Public pickup | Name and phone required | Not required | As above | 0 |
| Admin delivery | Existing customer or new name; phone optional | Required | Date and period; past dates allowed (historical entry) | `delivery_fee` setting |
| Admin pickup | Existing customer or new name; phone optional | Not required | As above | 0 |
| POS `dine_in` | Customer, name and phone optional; no profile is created | Prohibited | Date, period and time null | 0 |

Public checkout rejects `dine_in` and the full admin form does not offer it; the admin store accepts it only as a counter order, which it records as `source=pos` (it never accepts a client-supplied `source`). Delivery periods: `midday`, `afternoon`, `night`; `delivery_time` is optional.

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

- Creating an order persists the full quote: line name, pricing mode, block count, units, applied price, cooked reference price, raw discount, regular price and savings (`order_items`); the flavors of each combined dozen (`order_item_compositions`, version-1 and version-2 orders); add-ons (`order_toppings`); and on `orders` the customer name/phone, address, shipping, subtotals, savings, discount and total.
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
| Empanada half dozen | `products.half_dozen_price`, **stored** as `6 × U` and recalculated with `U`; never entered and never used to price orders (6 units are priced as 6 × `U`) |
| Raw (cook at home) | `D_raw = D − raw_price_discounts.dozen`; `U_raw` = same formula on `D_raw`, at quote time |
| Combined dozen discount `X` | `order_settings.combined_dozen_discount_percent` (integer 0–100, seed 20), edited in Configuración |
| `unit` product | `products.unit_price`, entered by the operator |
| Add-on | Current `order_addons.price` of an available add-on |
| Shipping | `delivery_fee` for `delivery`; 0 otherwise |

`U` is recalculated only when `D` changes (product create/edit, or switching a product to `empanada`) or when `p` changes in Configuración (every empanada, soft-deleted included, `RecalculateEmpanadaUnitPrices`). It is never recalculated per order. Product requests reject `unit_price`, `half_dozen_price` and the retired `homogeneous_half_dozen_surcharge` for empanadas; the editor shows the unit and half dozen read-only. `EmpanadaUnitPrice` (`app/Modules/Products/Pricing/`) is the single formula.

Retired inputs (rows kept, never read): `order_settings.combined_dozen_cooked_price` / `combined_dozen_raw_price`, `raw_price_discounts.unit` / `half_dozen`. The half-dozen surcharge preview endpoint and `HomogeneousHalfDozenPrice` were removed; migration `2026_10_19_000001` rewrote every stored empanada half dozen as `6 × U` (its `down` keeps them).

### Pricing families

Each product has an explicit `pricing_family`: `empanada` or `unit`. It is never inferred from category, slug or name. `unit` products are priced `quantity × unit_price` with no grouping and no raw discount.

### Dozen algorithm (empanada products)

1. Per flavor, every 12 units are one `dozen` line at `D` (or `D_raw`).
2. While the flavors' remainders add up to 12 or more, they fill a **combined dozen**: the flavor with the highest `U` (of the order's condition) enters first, and with equal `U` the lower product ID first; it takes up to its whole remainder, then the next flavor, until 12 units. Every remainder is under 12, so a combined dozen always mixes more than one flavor. Its price is `Σ(quantity × U) × (1 − X%)`; `U` is a multiple of ARS 100, so the price stays in whole pesos.
3. Whatever is left sells as `unit` lines at `U`. There is no half-dozen tier and no cap — 11 units of one flavor can cost more than its dozen.

A combined dozen is one `combined_dozen` line without a product (quantity 1, 12 units): `regular_price_total` = `Σ(quantity × U)`, `savings` = the `X%` discount, flavors in `order_item_compositions`; the quote's savings go to `orders.combined_dozen_savings`. Raw orders price it with `U_raw` and keep the cooked reference (`cooked_line_total` from the cooked `U`, `raw_discount` = cooked − applied). Lines keep product order (dozen, then unit, per product); combined dozens come last, so a basket without one quotes exactly as before. `unit` products never join a combined dozen. This replaces fixed-dozen S3 ("no combined dozen"); the retired fixed prices 17500/16000 stay unused.

Required examples (`X` 20%, surcharge 25%; D: dulce 20000, JyQ 22000, salteña 18000 → U 2100, 2300, 1900), `tests/Feature/Orders/CombinedDozenPricingTest.php`:

| Basket | Total |
| --- | ---: |
| 12 dulce | 20000 |
| 11 dulce + 1 JyQ | 20300 (combined 20320) |
| 6 dulce + 6 JyQ | 21100 |
| 6 dulce + 3 JyQ + 3 salteña | 20200 |
| 5 dulce + 5 JyQ | 22000 (no dozen) |
| 13 dulce + 11 JyQ | 41900 |
| 8 dulce + 8 JyQ | 29800 (combined 8 JyQ + 4 dulce = 21440; 4 dulce at U) |
| 8 dulce + 8 JyQ + 8 salteña | 40300 |
| 11 salteña + 1 dulce | 18400 |
| Raw 6 dulce + 6 JyQ / raw 6 + 3 + 3 | 19200 / 18200 |

Single-flavor examples at the 10% seed (U 1800, 2000, 1700) stay in `tests/Feature/Orders/FixedDozenOrderPricingTest.php`: 12 dulce 20000, 11 dulce 19800, 13 dulce 21800, 24 dulce 40000, 11 salteña 18700, raw dulce dozen / unit 18000 / 1700.

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
| 2 | Every new quote (`PersistOrderQuote`) | Whole-peso lines whose exact numerators are 12 × the pesos (`applied/cooked/raw_discount_exact_numerator_12`, surcharge 0); order numerators for products, discount (over 1200), pre-round net and rounding adjustment. Database checks (`2026_10_19_000000_activate_fixed_dozen_pricing`, widened by `2026_10_22_000100_allow_combined_dozen_snapshots`) accept `unit`/`dozen` lines with a product or `combined_dozen` lines without a product of quantity 1 and 12 units, and `total = net + adjustment`. |

Rollback of the fixed-dozen migration refuses once version-2 history exists; rollback of the combined-dozen widening refuses once a version-2 combined dozen exists. Neither rewrites prices.

### Limits and errors

At least one product; at most 50 distinct products per request, each once, quantity 1–120; at most 20 add-on rows, each once, quantity 1–100. Unavailable, deleted or inactive-category selections fail. Public selections use slugs; admin selections use validated internal IDs. Invalid price configuration (raw dozen discount missing or above a flavor's dozen) and requests above the limits return validation errors, never a server error.

## Derived pricing classes (P0-A, inactive)

`Pricing/DerivedTierAllocator.php` and `Pricing/DerivedPricingCalculator.php` (formula `Σ q·D/12 + R_combined` with homogeneous and mixed sixes) have **no production caller** and are not the live model; `order_settings.combined_half_dozen_surcharge` only feeds them and is no longer shown in Configuración. Their tests stay in `tests/Unit/Orders/DerivedPricingFoundationTest.php`. Of that foundation, only `ExactOrderTotals` and the reserved snapshot columns are used, by version 2.

## Table service

Code: `Actions/ConfirmTableRound.php`, `RequestTableBill.php`, `IssueTableBill.php`, `ChargeTableSession.php`, `CancelTableSession.php`, `Support/TableSessionGuard.php`, `Support/OrderDocuments.php`. Tables come from the floor editor (`Restaurant`); `Restaurant\Queries\ServiceTables` locks one without exposing geometry.

```text
free table --first round--> open session + POS order (dine_in, cooked, waiting, unpaid)
open session --round / void round--> same order re-priced over all rounds; kitchen ticket with only that round
open session --request bill--> flag only (cleared by the next round)
open session --print bill--> bill of the current order version
open session --charge--> paid (method) + delivered, session closed: the order is frozen
open session --cancel--> order cancelled, session cancelled
```

- One open session per table (partial unique index `table_sessions_one_open_per_table`). A session belongs to one order (`table_sessions.order_id` unique). The member who confirms the first round is the session's waiter.
- **Rounds are immutable.** `table_rounds` and `table_round_items` reject `UPDATE`/`DELETE` (trigger `table_service_reject_history_mutation`). A correction is a `void` round with positive quantities that subtract; the running quantity of a product can never go below zero, and a void cannot empty the table (cancel it instead).
- **Pricing.** Every round re-quotes the still-open order with `OrderPricingEngine` over the summed quantities of all rounds and replaces only that order's snapshots (`PersistOrderQuote`, one `order_revisions` row per re-quote). Dozen pricing therefore applies across rounds: 6 salteñas in one round and 6 in another are one dozen (18000), not 12 × 1700, and mixed remainders of different rounds can form a combined dozen. The order total is the table's provisional total. Engine limits apply to the sums (120 per product).
- **Stale screens.** A round sends the session the client was showing (`null` for a free table). If that session was charged, cancelled, or the table was opened meanwhile, the round is rejected; it never silently opens a new session. A round key (HMAC-stored, unique) makes a retried confirmation return the same round; the same key with other lines is rejected.
- **Generic order actions** (`UpdateOrder`, `UpdateOrderStatus`, `UpdateOrderPaymentStatus`, trash) refuse an order whose table session is open (`Support/OpenTableOrders`). After the charge the order follows the regular closed-order rules.
- **Charge** sets `paid` with the method and `delivered` in one transaction; the payment and consumption listeners run inside it, so a missing wallet or a product without consumption configuration leaves the table open and unpaid. `Inventory\Queries\ProductsWithoutConsumption` lists the products that would block it. Charging prints nothing.
- **Documents.** Each round emits `PrintableDocumentIssued` (`kitchen_ticket`, dedupe key `table-round:{id}`): title `Mesa <code>`, order, round number, void flag, date and time (business time zone), waiter, `added_by` (role label and name when the confirming member is not the waiter, for example "Cajero Lu"), note and lines (name, quantity) — no prices. Printing the bill emits `bill` with key `order:{id}:bill:v{edit_version}`: lines with dozen detail, unit price and total, add-ons, subtotal, discount, rounding, total and the legend "Documento no válido como factura".
- **Screens** (`Http/Controllers/TableServiceController.php`, `TableSessionController.php`, read model `Queries/TableServiceBoard.php`; all routes need `orders.manage`, actions re-check roles through `TableSessionGuard`):

  | Route | Page / effect |
  | --- | --- |
  | `GET /admin/tables` | `admin/tables/index`: active tables of active areas plus any deactivated table that still has an open session, in natural code order (M1, M2, M10); waiter, provisional total and bill flag per open table. Polls every 10 s. |
  | `GET /admin/tables/{table}` | `admin/tables/show`: provisional total, current lines, rounds (time in the business time zone, "Agregado por <Rol> <Nombre>"), round composer (order or void), and for owner/manager/cashier the missing-consumption warning for the table's products. |
  | `POST /admin/tables/{table}/rounds` | `ConfirmTableRound` with `expected_session_id`, `kind`, `items`, `addons`, `note` (≤ 300) and a client-generated `round_key` (UUID) reused when the same draft is retried. |
  | `POST /admin/table-sessions/{session}/bill-request` | `RequestTableBill` (waiter of the table or a cashier role). |
  | `POST /admin/table-sessions/{session}/bill`, `/charge` (`payment_method`), `/cancel` | `IssueTableBill`, `ChargeTableSession`, `CancelTableSession`: owner, manager and cashier only. |

  Rejections (stale session, inactive table, void beyond the table) come back as validation errors on `table`/`items`; role failures are 403. Successful actions flash a `toast`. "Vender" groups Mostrador, Mesas and Impresión, and the POS home shows those tabs.

## Kitchen tickets and bills

Orders builds every printable document in `Support/OrderDocuments.php` and emits `PrintableDocumentIssued` inside the business transaction; `Printing` stores it (see [OPERATIONS.md](OPERATIONS.md#print-station)).

| When | Kind | Dedupe key | Content |
| --- | --- | --- | --- |
| A table round or void round is confirmed | `kitchen_ticket` | `table-round:{id}` | Only that round's lines (see Table service) |
| An admin or counter (`pos`, no table) order is created | `kitchen_ticket` | `order:{id}:kitchen` | Title `Delivery`/`Retiro`/`Mostrador`, order, date and time, customer name, `Crudas (para cocinar en casa)` for raw orders, period and time, note, units per product (dozens are expanded to units) and add-ons — no prices |
| A public order leaves `pending_confirmation` for any status except `cancelled` (status control or full edit) | `kitchen_ticket` | `order:{id}:kitchen` | Same as above; later status changes print nothing |
| A cashier role prints a table bill | `bill` | `order:{id}:bill:v{edit_version}` | Units per product with their price (per unit, or per pack with its detail such as "1 docena"; a dozen line's `order_items.quantity` counts dozens, the bill prints `units_count`), totals and "Documento no válido como factura" |

## Admin quick order

`/admin` is the quick-order screen ([ADR 0001](decisions/0001-admin-home-is-quick-order.md)); `/admin/orders/create` is the full-page alternative.

- Product cards add 12, 6 or 1 unit to an in-memory draft; nothing is persisted until final confirmation.
- Both screens share `features/orders/hooks/use-admin-order-draft.ts` (draft state, preview sequencing, stale-response protection, submit lock), `admin-order-details-fields.tsx` and `PriceSummary`, which calls `POST /admin/orders/price-preview`.
- Confirmation posts to `POST /admin/orders` (`StoreAdminOrderRequest` → `CreateOrder`). Quick order receives a JSON confirmation with the backend-generated ticket; the full form receives an Inertia redirect.

## Change checklist

1. Change line building only in `OrderPricingEngine` and keep `tests/Feature/Orders/CombinedDozenPricingTest.php` and `FixedDozenOrderPricingTest.php` (required examples) green.
2. Keep product price access behind `ProductPriceProvider` and the unit formula in `EmpanadaUnitPrice` (mirrored by the migration SQL and `features/products/utils.ts` for display).
3. Never recalculate stored snapshots; a new persisted shape needs a new snapshot version, constraints and tests.
4. Update this document.
