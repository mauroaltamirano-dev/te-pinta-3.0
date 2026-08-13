# Architecture

Te Pinta 3.0 is a modular Laravel monolith with an Inertia/React frontend. PostgreSQL owns transactional integrity; Laravel owns authorization, validation, pricing, and state changes; React composes the workflow and displays backend results.

## Request paths

```text
Administrative browser
  -> auth + verified + admin + admin.request.log
  -> module controller / Form Request / policy
  -> action or pricing service
  -> PostgreSQL transaction
  -> Inertia page or JSON preview

Public checkout
  -> public slug input + Laravel validation + rate limit
  -> internal slug resolution
  -> shared pricing engine
  -> atomic customer match/create + order snapshots
  -> backend summary + WhatsApp link
```

The public order is committed before the WhatsApp URL is generated. Opening WhatsApp is a client-side handoff, not proof that the customer sent the message.

## Backend boundaries

```text
app/
  Core/       stable application-wide contracts and value data
  Shared/     narrowly named reusable infrastructure
  Modules/    business capabilities and their delivery/application code
```

- `Products` owns catalog availability, raw base prices, and configurable cooking surcharges.
- `Orders` owns commercial grouping, final totals, snapshots, lifecycle, toppings, delivery, summaries, and checkout idempotency.
- `Customers` owns profiles, phone normalization, administrative search, and customer history.
- `Settings` provides the administrative write boundary for ordering settings, cooking surcharges, and existing toppings.
- `PublicSite` owns public page routes; its checkout endpoints delegate to `Orders`.
- `Core\Contracts\ProductPriceProvider` is the narrow Products-to-Orders seam. Orders must not reproduce catalog pricing queries.

Controllers coordinate HTTP. Form Requests authorize/validate. Actions execute use cases. Models persist relationships. Do not introduce generic repositories, base services, or placeholder folders.

## Frontend boundaries

```text
resources/js/
  layouts/             public, admin, authentication, and Settings shells
  pages/               thin Inertia route entry points
  features/            feature-owned components and types
  components/ui/       shadcn primitives
  components/shared/   Te Pinta reusable components
  lib/                 focused infrastructure helpers
```

Frontend price previews call Laravel. They are informative only: order creation reruns the same backend engine and persists its result.

## Dependency direction

1. HTTP delivery calls application actions or focused services.
2. Orders consumes Products through `ProductPriceProvider`.
3. Core never depends on a business module.
4. A module never calls another module's controller or request class.
5. Pages may compose feature/shared UI; shared UI must not import feature code.

## Durable decisions

| Decision                          | Consequence                                                                                            |
| --------------------------------- | ------------------------------------------------------------------------------------------------------ |
| Modular monolith                  | Customers, Products, Orders, Settings, and PublicSite keep ownership without distributed transactions. |
| PostgreSQL sequence for Order IDs | `TP-000001` allocation is concurrency-safe; rollbacks may leave harmless gaps.                         |
| One backend pricing engine        | Admin and public checkout cannot diverge on grouping or totals.                                        |
| Immutable order snapshots         | Catalog, address, topping, and setting changes never rewrite history.                                  |
| Active-phone partial uniqueness   | Matching is deterministic among active customers while a soft-deleted phone may be reused.             |
| Public slugs, internal IDs        | Public checkout selects catalog entries without exposing database IDs.                                 |
| Hash + fingerprint idempotency    | Exact retries return the same order; key reuse with another payload is rejected.                       |
| Soft deletion                     | Customers/orders remain recoverable and historical foreign keys remain intact.                         |

## Still out of scope

Payment methods/gateways, WhatsApp automation, promotions beyond combined-dozen savings, inventory, purchases, stock, analytics, and additional combo types are not implemented.
