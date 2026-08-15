# Order pricing

`app/Modules/Orders/Pricing/OrderPricingEngine.php` is the only authoritative commercial grouping and total calculator. POS, public checkout, and economic administrative edits call it; React only renders backend previews.

## Inputs and price sources

The engine receives final quantities per product, one order-wide condition (`raw` or `cooked`), topping quantities, delivery type, and optional administrative discount type/value. It does not receive trusted prices or a client-calculated discount amount.

| Value                       | Source                                               |
| --------------------------- | ---------------------------------------------------- |
| Unit/half-dozen/dozen price | `ProductPriceProvider` from Products                 |
| Cooked additions            | `cooking_surcharges` (`unit`, `half_dozen`, `dozen`) |
| Combined dozen              | `order_settings` fixed raw/cooked prices             |
| Topping                     | available `order_addons.price`                       |
| Shipping                    | `delivery_fee` for delivery; zero for pickup         |
| Discount                    | Backend enum/value applied by `OrderPricingEngine`   |

Initial combined prices are 16000 raw and 17500 cooked. Cooked combined-dozen price is independent; do not derive it from the standard dozen surcharge.

Every quote also carries an explicit cooking breakdown. For each commercial line, the backend snapshots the raw equivalent and calculates `cooking_surcharge = applied cooked price - raw equivalent`. Unit, half-dozen, and dozen lines compare the matching product tier. A combined dozen compares its independent settings, so the initial 16000 raw / 17500 cooked pair produces a 1500 cooking surcharge, not the standard dozen surcharge of 2000. This breakdown only explains the existing applied price; it never changes the charged total.

## Deterministic grouping

The algorithm is rule-driven, not a generic cheapest-price optimizer:

1. Sort requested products by internal product ID.
2. Consume every complete same-product dozen at that product's dozen price.
3. While at least 12 remaining units span more than one product, consume a combined dozen in product-ID order.
4. For each remainder, consume same-product half-dozens.
5. Price final remainders as units.

Product-ID order is a tie-breaker for equivalent large mixed compositions. It makes snapshots repeatable; it is not a merchandising preference.

## Normative examples

| Requested quantities | Commercial lines           |
| -------------------- | -------------------------- |
| 5 A                  | 5 units A                  |
| 6 A                  | 1 half-dozen A             |
| 7 A                  | 1 half-dozen A + 1 unit A  |
| 12 A                 | 1 dozen A                  |
| 18 A                 | 1 dozen A + 1 half-dozen A |
| 3 A + 3 B            | 3 units A + 3 units B      |
| 6 A + 3 B + 3 C      | 1 combined dozen           |
| 6 A + 6 B            | 1 combined dozen           |
| 24 varied            | 2 combined dozens          |

There is no mixed half-dozen. Same-product dozens run first, but mixed dozens run before half-dozens; this is why 6 A + 3 B + 3 C closes one combined dozen instead of consuming A as a half-dozen.

## Raw and cooked

Product prices stored in Products are raw. `DatabaseProductPriceProvider` adds the matching persisted cooking surcharge for cooked quotes:

- unit price + unit surcharge;
- half-dozen price + half-dozen surcharge;
- dozen price + dozen surcharge.

Condition belongs to the entire order. Item-level raw/cooked combinations are not supported.

## Combined-dozen savings

For each combined dozen, the engine prices the same composition using normal product grouping and compares it with the configured fixed combined price:

```text
savings = max(0, normal composition price - combined price)
```

Only a positive result is displayed/persisted as savings. A combined price above the normal calculation never creates a misleading negative “discount”; the charged combined price still remains the configured fixed price.

## Toppings and delivery

Toppings are not Products. Each available addon is priced as `quantity × current addon price`; quantity is bounded to 1–100 and duplicate addon rows are rejected.

- `pickup`: shipping is always zero.
- `delivery`: uses current `delivery_fee` and requires a non-blank delivery address during creation.

The initial delivery fee is the safe placeholder `0`. Configure the real value in `/admin/settings/general` before taking delivery orders.

## Administrative discounts

Discounts are available only to authenticated administrative create/edit/Quick Order flows. The supported types are `percentage` (whole percentage from 0–100) and `fixed_amount` (whole pesos). Type and value must be supplied together.

```text
discountable subtotal = items subtotal + toppings subtotal
discount amount       = min(calculated amount, discountable subtotal)
total                 = discountable subtotal - discount amount + shipping
```

`items subtotal` already contains the applied raw/cooked price, so cooking participates in the discount. Shipping never does. Percentage results round half-up to a whole peso; a fixed discount cannot consume shipping or make the commercial subtotal negative. Public preview/checkout prohibits all discount fields.

## Persisted history

Order creation persists the quote, not a reference to a future calculation:

- normal lines: sold product name, mode, block quantity, unit count, applied price, subtotal;
- every new line: raw-equivalent unit/base subtotal and cooking-surcharge snapshots alongside the applied values;
- combined lines: fixed price, raw-equivalent fixed price, cooking surcharge, regular comparison total, savings, and product-name/quantity composition;
- toppings: sold name, quantity, applied unit price, subtotal;
- order: customer/contact snapshots, delivery address, shipping fee, subtotals, savings, discount type/value/applied amount, payment method, and total.

New orders also persist aggregate base-product and cooking-surcharge values. The invariants are `items subtotal = base-product subtotal + cooking surcharge` and `total = items + toppings + shipping - discount`. Pre-existing orders keep additive cooking columns null because reconstructing their raw equivalent from current products or settings would rewrite history. Their summaries therefore omit the cooking line rather than inventing a value.

Opening an order never reruns pricing. Current catalog, customer, topping, or setting changes must not affect existing snapshots.

## Administrative editing and repricing

The edit page starts from two structured backend values:

- a draft rebuilt from persisted item/composition/topping records as real product and addon quantities;
- `original_quote`, built only from the order's current snapshots for the **Before** summary.

The shared draft hook calls the existing `/admin/orders/price-preview` whenever products, quantities, condition, toppings, delivery type, or discount change. It compares that current backend quote with the persisted total; React does not calculate a replacement price. Payment method is not economic and does not invalidate the preview.

`UpdateOrder` independently classifies those same fields as economic. If any changed, it runs the complete order through `OrderPricingEngine` with configuration and sellability valid at confirmation time. `PersistOrderQuote` then replaces item, composition, topping, aggregate pricing, shipping, and savings snapshots for that order in the same transaction. Other historical orders are never recalculated.

Customer, address, date/period/time, notes, payment, and operational-state-only corrections do not trigger quote replacement. They still pass backend validation and optimistic-concurrency checks; the persisted quote remains unchanged.

### Unavailable historical selections

An unavailable, inactive-category, or soft-deleted product/addon already present in an order remains visible with its historical quantity. It may survive a non-economic correction only while the complete economic selection is unchanged. It cannot be incremented or newly selected. Before any economic edit can be confirmed, unavailable historical selections must be removed or replaced, because current-rule repricing cannot authoritatively price an unsellable selection.

## Limits and rejection

- At least one product is required.
- Each product may appear once with quantity 1–120.
- Requests contain at most 50 product entries.
- Each topping may appear once with quantity 1–100; at most 20 entries.
- Missing, unavailable, or soft-deleted selections fail current pricing. Edit hydration may display an unchanged historical selection under the explicit policy above.
- Public selection uses slugs; admin selection uses validated internal IDs.

## Change checklist

1. Change grouping only in `OrderPricingEngine`.
2. Keep Products price access behind `ProductPriceProvider`.
3. Update the matrix in `tests/Feature/Orders/OrderPricingEngineTest.php`.
4. Update snapshot/constraint tests if persistence shape changes.
5. Update this document and `PROJECT_MAP.md` for any moved responsibility.
