# Database

PostgreSQL is the application database. Te Pinta 3.0 starts with a new schema; the legacy database is retained separately for backup/reference and is not migrated automatically.

## Connection

Configuration comes from environment variables read by `config/database.php`:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=
DB_PORT=
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=
```

Do not expose PostgreSQL publicly. Local containers should use an internal Docker network or bind any required host port to loopback. Production credentials belong in the deployment secret store, never Git.

## Schema rules

- Migrations in `database/migrations/` are the versioned source of truth.
- Use foreign keys, check/unique constraints, appropriate nullability, and indexes that match real access paths.
- Use transactions when one operation changes multiple records.
- Explicitly define mass-assignable input; never pass arbitrary request data into model create/update calls.
- Use an application database role with only the privileges required at runtime.
- Do not use a person's name as a primary key.

## Defined identity and lifecycle

### Customers

- Stable internal primary key (`customer_id` conceptually; exact physical convention must remain consistent with the implemented migration).
- Searchable by name and phone when the customer feature is implemented.
- Soft deletion is preferred to preserve order history.
- Addresses and phone numbers are personal data; expose and log only what a use case requires.

### Orders

- Internal database primary key.
- Separate stable public/human order identifier such as `TP-000001`.
- Public routes must not rely on guessable internal IDs where that creates unnecessary exposure.
- Soft deletion is preferred.
- Order state and payment state are separate validated concepts.

Defined order states: `waiting`, `prepared`, `delivered`. Defined payment states: `unpaid`, `paid`. Any transition rules beyond validation are deferred until explicitly specified.

### Product catalog

| Table                | Purpose and lifecycle                                                                                                                                                                        |
| -------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `products`           | Stable internal ID, one category, name/slug, nullable description and relative image path, three integer base prices, availability, category-local `sort_order`, timestamps, and soft delete |
| `categories`         | Administrable name/slug, manual order, active flag, timestamps, and soft delete                                                                                                              |
| `tags`               | Independent, extensible product labels such as `Vegetariana`                                                                                                                                 |
| `product_tag`        | Many-to-many association with a composite primary key; deleting a referenced tag is restricted                                                                                               |
| `cooking_surcharges` | One integer peso surcharge for each `unit`, `half_dozen`, and `dozen` pricing mode                                                                                                           |

Prices are whole integer pesos in `unit_price`, `half_dozen_price`, and `dozen_price`; floats and client-calculated totals are not accepted. `CalculateProductPrice` adds the matching persisted surcharge only when cooked pricing is requested. The initial surcharge values are seeded—not hardcoded per product—so Settings can own an editing workflow later without changing the product schema.

Availability and deletion are different:

- `is_available=false` hides a product from the public catalog and future selling, while retaining it in administration for immediate reactivation.
- Soft-deleted products disappear from normal/admin/public queries but retain their row for historical order relationships.
- An inactive category and its products remain visible to administrators but are excluded from the public catalog.
- Normal administration provides no category delete route; categories with products are deactivated instead.

Public/catalog order is `categories.sort_order`, then `products.sort_order`, with internal ID only as a deterministic tie-breaker.

### Initial catalog

`database/seeders/ProductCatalogSeeder.php` initializes any missing records among the two categories, the `Vegetariana` tag, nine defined products, and three cooking surcharges. It is intentionally non-destructive: repeated runs preserve administrative edits and do not restore soft-deleted categories or products. Run it directly with:

```bash
./vendor/bin/sail artisan db:seed --class=ProductCatalogSeeder
```

`DatabaseSeeder` also calls it, so `./vendor/bin/sail artisan db:seed` is the full-database equivalent.

## Migration checklist

- [ ] The table belongs to a sufficiently defined feature.
- [ ] Nullability and defaults express the domain rather than convenience.
- [ ] Foreign-key delete behavior is deliberate.
- [ ] Search and uniqueness requirements have supporting indexes.
- [ ] Money uses an exact representation, never floating point.
- [ ] Rollback behavior is safe and tested.
- [ ] No legacy data import is hidden in the migration.
