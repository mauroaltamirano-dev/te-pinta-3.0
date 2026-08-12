# Order pricing

`app/Modules/Orders/Pricing/OrderPricingEngine.php` is the only authoritative commercial grouping and total calculator. Both POS and public checkout call it; React only renders backend previews.

## Inputs and price sources

The engine receives final quantities per product, one order-wide condition (`raw` or `cooked`), topping quantities, and delivery type. It does not receive trusted prices.

| Value                       | Source                                               |
| --------------------------- | ---------------------------------------------------- |
| Unit/half-dozen/dozen price | `ProductPriceProvider` from Products                 |
| Cooked additions            | `cooking_surcharges` (`unit`, `half_dozen`, `dozen`) |
| Combined dozen              | `order_settings` fixed raw/cooked prices             |
| Topping                     | available `order_addons.price`                       |
| Shipping                    | `delivery_fee` for delivery; zero for pickup         |

Initial combined prices are 16000 raw and 17500 cooked. Cooked combined-dozen price is independent; do not derive it from the standard dozen surcharge.

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

## Persisted history

Order creation persists the quote, not a reference to a future calculation:

- normal lines: sold product name, mode, block quantity, unit count, applied price, subtotal;
- combined lines: fixed price, regular comparison total, savings, and product-name/quantity composition;
- toppings: sold name, quantity, applied unit price, subtotal;
- order: customer/contact snapshots, delivery address, shipping fee, subtotals, savings, total.

Opening an order never reruns pricing. Current catalog, customer, topping, or setting changes must not affect existing snapshots.

## Limits and rejection

- At least one product is required.
- Each product may appear once with quantity 1–120.
- Requests contain at most 50 product entries.
- Each topping may appear once with quantity 1–100; at most 20 entries.
- Missing, unavailable, or soft-deleted selections fail validation.
- Public selection uses slugs; admin selection uses validated internal IDs.

## Change checklist

1. Change grouping only in `OrderPricingEngine`.
2. Keep Products price access behind `ProductPriceProvider`.
3. Update the matrix in `tests/Feature/Orders/OrderPricingEngineTest.php`.
4. Update snapshot/constraint tests if persistence shape changes.
5. Update this document and `PROJECT_MAP.md` for any moved responsibility.
