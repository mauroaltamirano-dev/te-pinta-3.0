# Database

PostgreSQL is the application database and the final integrity boundary. Migrations are versioned; existing development data must be preserved.

## Money and identifiers

- Money is stored as exact non-negative whole-peso values (decimal columns cast to integers by the models).
- Internal primary keys are never customer-facing identities.
- `orders.public_id` defaults from PostgreSQL sequence `order_public_id_seq` as `TP-` plus six padded digits.
- Sequence allocation is concurrency-safe. Values are stable and unique, but transaction rollbacks and sequence refresh can create gaps. Gaps are valid and must never be “repaired” with `max(id) + 1`.

## Customers

| Column/rule        | Purpose                                 |
| ------------------ | --------------------------------------- |
| `id`               | Stable internal identity                |
| `name`             | Required, indexed, duplicates allowed   |
| `phone`            | Optional display value                  |
| `phone_normalized` | Optional digits-only match/search value |
| `address`          | One optional editable address           |
| `notes`            | Optional private administrative notes   |
| `deleted_at`       | Soft deletion                           |

`customers_active_phone_normalized_unique` is a PostgreSQL partial unique index for non-null phone values where `deleted_at IS NULL`. This makes public matching deterministic among active profiles while allowing a phone from a soft-deleted profile to be reused. Application validation mirrors the constraint; the database remains authoritative under concurrency.

## Orders and snapshots

`orders` stores customer reference plus customer name/phone snapshots; source; operational/payment state; raw/cooked condition; delivery type/date/period/time; delivery address snapshot; order notes; subtotal/savings/shipping/total values; and optional public idempotency hash/fingerprint. New orders also snapshot `items_base_subtotal` and `cooking_surcharge`; both remain null together on legacy rows that predate the explicit breakdown.

| Table                     | Historical responsibility                                                                                                                                                                           |
| ------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `order_items`             | Product reference when applicable, sold name, commercial mode, block count, unit count, raw-equivalent price/subtotal, cooking surcharge, applied price, normal comparison price, savings, subtotal |
| `order_item_compositions` | Product reference, sold name, and quantity for each combined-dozen part                                                                                                                             |
| `order_toppings`          | Optional addon reference plus sold name, quantity, applied unit price, subtotal                                                                                                                     |

Combined-dozen lines have no single `product_id`; normal lines require one. Product/catalog foreign keys restrict deletion, while soft deletion keeps historical references usable. Order-owned item, composition, and topping rows cascade only when the physical order row is deleted; normal administration soft-deletes orders.

Changing a product name/price/availability, customer address, topping price, or delivery fee does not change existing orders. Detail/resources/formatter read snapshots rather than current catalog/settings.

Cooking snapshots follow the same rule. New line totals satisfy `line_total = base_line_total + cooking_surcharge`; the order aggregate satisfies `items_subtotal = items_base_subtotal + cooking_surcharge`. Cooking may not be backfilled on legacy orders because current product/settings values are not historical evidence.

## Database constraints

PostgreSQL checks enforce:

- valid status, payment status, source, condition, delivery type, delivery period, and pricing mode;
- positive bounded quantities and non-negative monetary values;
- `total = items_subtotal + toppings_subtotal + shipping_fee_snapshot`;
- pickup has zero shipping; delivery has a non-blank address snapshot;
- combined-dozen item shape and `savings = regular_price_total - line_total`;
- exactly one typed value per `order_settings` row;
- idempotency hash and fingerprint are both null or both present.

The unique `public_checkout_idempotency_hash` resolves concurrent duplicate submissions.

## Configuration tables

`order_settings` uses the closed `OrderSettingKey` enum rather than arbitrary runtime keys. Each row has exactly one integer or string value. Initial values are 16000 raw combined dozen, 17500 cooked combined dozen, zero delivery fee, and an empty WhatsApp phone.

`order_addons` is the small configurable topping catalog. It has a stable public slug, name, integer price, availability, manual order, and soft deletion. Initial rows are Lactonesa (500) and Yasgua (200).

Products retain `unit_price`, `half_dozen_price`, and `dozen_price` as raw base prices. `cooking_surcharges` stores the three configurable cooked additions.

## Migration workflow

```bash
./vendor/bin/sail artisan migrate
```

Never run `migrate:fresh` against the current development database. Test clean migrations only in the isolated test database.

## Migration checklist

- [ ] Foreign-key behavior preserves required history.
- [ ] Search and uniqueness requirements have indexes.
- [ ] Money is exact and server-computed.
- [ ] Multi-record writes are transactional.
- [ ] PostgreSQL checks mirror important domain invariants.
- [ ] Upgrading preserves existing administrators, products, customers, and orders.
- [ ] Rollback behavior is safe in the intended environment.
