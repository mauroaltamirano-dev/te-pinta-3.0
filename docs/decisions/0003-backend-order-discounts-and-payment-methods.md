# 0003: Backend order discounts and payment methods

Status: accepted
Date: 2026-08-14

## Context

Administrators need percentage or fixed-peso discounts and must record whether a paid order used cash, bank transfer, or debit card. Create, Edit, Quick Order, and price preview already share one backend pricing boundary. Combined-dozen savings are commercial grouping metadata, not an administrator discount.

## Decision

`OrderPricingEngine` receives only an optional `DiscountType` and integer value. The eligible base is items after commercial grouping, including cooking surcharge, plus toppings; shipping is excluded. Percentage results use whole-peso half-up rounding and both discount types are capped at the eligible base. The quote returns `discountable_subtotal` and the applied `discount_amount`; clients cannot submit either the amount or a total.

Orders snapshot `discount_type`, `discount_value`, and `discount_amount`. PostgreSQL enforces the discount formula and the final invariant:

```text
total = items_subtotal + toppings_subtotal + shipping_fee_snapshot - discount_amount
```

`PaymentMethod` is `cash`, `bank_transfer`, or `debit_card`. Paid requires one method; unpaid stores none. Returning to unpaid clears the method atomically. Public checkout remains unpaid and may submit neither discounts nor payment methods.

## Consequences

- Create/Edit/Quick Order preview and persistence cannot diverge on discounts.
- Shipping is visible in the total but never reduced by an order discount.
- Existing paid history is not assigned a fabricated payment method; the database check enforces the invariant on every new or subsequently updated row.
- Changing a discount is economic and triggers complete current-rule repricing during edit. Changing only payment status/method does not reprice.

## Alternatives considered

- **Calculate discounts in React:** rejected because clients do not own monetary results.
- **Reuse combined-dozen savings:** rejected because that value explains a commercial closure and has different semantics.
- **Discount shipping:** rejected by the business rule confirmed for Te Pinta.
- **Store a payment method on unpaid orders:** rejected because it conflates an intended method with a completed payment.
