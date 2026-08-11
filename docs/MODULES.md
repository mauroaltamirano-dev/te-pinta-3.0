# Backend modules

Each module represents a business capability and owns its delivery and application behavior. Create only the folders needed by implemented behavior.

## Initial modules

| Module       | Current responsibility                                                                    | Deferred                                               |
| ------------ | ----------------------------------------------------------------------------------------- | ------------------------------------------------------ |
| `Identity`   | User identity and administrator status boundary                                           | Additional roles/permissions not yet required          |
| `PublicSite` | Public home, menu, and cart route/page boundary                                           | Complete catalog/cart/WhatsApp workflow                |
| `Dashboard`  | Administrative landing boundary                                                           | Recommendation, discounts, and combo management rules  |
| `Orders`     | Order/POS boundary and future order lifecycle                                             | Complete wizard, pricing, transitions, WhatsApp output |
| `Customers`  | Customer boundary                                                                         | Search, editing, history, historical spend             |
| `Products`   | Product/category administration, tags, images, catalog queries, and authoritative pricing | Tag administration UI and editable cooking surcharges  |
| `Analytics`  | Navigation and contract boundary                                                          | Metrics and reporting definitions                      |
| `Inventory`  | Navigation and contract boundary                                                          | Stock and replenishment rules                          |
| `Settings`   | Administrative configuration boundary                                                     | Advanced operational settings                          |

## Module anatomy

A module may contain only what it uses:

```text
app/Modules/Orders/
  Http/
    Controllers/
    Requests/
    Middleware/
  Actions/
  DTOs/
  Models/
  Policies/
  Services/
  routes.php
```

Do not reproduce this tree mechanically. For example, a placeholder route needs neither a service nor a DTO.

## Adding behavior

1. Place the route in the owning module's `routes.php`.
2. For admin behavior, keep it below `/admin` and enforce `auth`, `verified`, and `admin` on the server route/controller.
3. Validate input with a Form Request or an equally explicit Laravel mechanism.
4. Put a multi-step use case in a named action; use a service only for cohesive reusable behavior.
5. Keep persistence and transactions inside the module boundary.
6. Add feature tests beside the behavior's public boundary.
7. Update `PROJECT_MAP.md` and this document if ownership changes.

## Cross-module rules

- Prefer IDs, small immutable data, and contracts over passing mutable models across boundaries.
- Do not call another module's controller or Form Request.
- Extract behavior only after the same responsibility is genuinely shared.
- A shared abstraction needs a clear owner, reason, and tests.
- Database foreign keys may cross module tables where the domain requires it; application behavior still goes through an explicit boundary.

## Products

`Products` owns the first complete vertical slice. Its page remains a thin Inertia composition point; HTTP controllers coordinate, Form Requests validate/authorize, and named actions perform mutations.

```text
app/Modules/Products/
  Actions/             # product/category writes and price calculation
  Data/                # immutable validated command data
  Enums/PriceTier.php
  Http/
    Controllers/
    Requests/
    Resources/         # separate admin and public response shapes
  Models/              # Product, Category, Tag, CookingSurcharge
  Policies/
  Support/UniqueSlug.php
  routes.php
```

Ownership rules:

- A product belongs to one category and may have many independent tags. `Clásicas` and `Especiales` are categories; `Vegetariana` is a tag.
- Category/product ordering is explicit through `sort_order`; queries never infer business order from name or creation time.
- `CalculateProductPrice` reads integer base prices and persisted cooking surcharges. Callers do not calculate authoritative prices in React.
- `PublicProductCatalogController` exposes only available products in active categories through `PublicProductResource`.
- Category deletion is intentionally absent from normal administration. Deactivate a category instead, preserving products and history.
- Product and category forms save individually in focused dialogs. Cancel/close discards local form state; there is no misleading page-wide Save/Discard buffer.
