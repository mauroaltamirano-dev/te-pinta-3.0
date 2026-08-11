# Architecture

Te Pinta 3.0 is a modular Laravel monolith with an Inertia/React frontend. It keeps deployment and transactions simple while using explicit feature boundaries so the codebase can grow without becoming a collection of global controllers and services.

## System shape

```text
Browser
  -> Laravel web middleware (session, cookies, CSRF, rate limiting)
  -> public or authenticated module route
  -> controller / Form Request / authorization
  -> action or focused service
  -> Eloquent model / PostgreSQL transaction
  -> Inertia response
  -> React page and feature components
```

The backend is authoritative. React may improve usability, but it never decides permissions, roles, prices, totals, state transitions, or resource ownership.

## Backend boundaries

```text
app/
  Core/       stable application-wide contracts and support
  Shared/     narrowly scoped, reusable implementation
  Modules/    business capabilities
```

- A module owns its routes and the layers its behavior actually needs.
- Modules do not contain placeholder directories.
- Cross-module calls use small contracts or explicit application actions when direct coupling would obscure ownership.
- `Core` is not a dumping ground. It contains stable concepts with application-wide relevance.
- `Shared` contains concrete reusable behavior with a specific name and responsibility; generic `Helpers` buckets are prohibited.
- Composition is preferred over base controllers, base services, and deep inheritance.

## Frontend boundaries

```text
resources/js/
  app/                 application composition
  layouts/             public, admin, and authentication shells
  pages/               Inertia route entry points
  features/            feature-owned components, hooks, types, and utilities
  components/ui/       shadcn primitives
  components/shared/   Te Pinta reusable components
  hooks/                application-wide hooks
  lib/                  focused infrastructure helpers
  types/                shared TypeScript contracts
```

Pages compose features and layouts. Business-rich UI belongs to its feature rather than a global component folder. `components/ui` remains the primitive layer; Te Pinta-specific wrappers live in `components/shared` or the owning feature.

## Dependency direction

1. Framework delivery code calls module application behavior.
2. Module behavior may depend on `Core` contracts and narrow `Shared` utilities.
3. `Core` must not depend on a business module.
4. One module must not reach into another module's internal controllers, requests, or persistence details.
5. Frontend feature code may use shared primitives; shared primitives must not import feature code.

## Current architectural decisions

| Decision                                                   | Reason                                                                                   |
| ---------------------------------------------------------- | ---------------------------------------------------------------------------------------- |
| Modular monolith                                           | Clear ownership without distributed-system overhead.                                     |
| Laravel session authentication with an administrative flag | Native CSRF, cookie, rate-limit, guard, and server-side admin middleware integration.    |
| PostgreSQL-only application database                       | Strong constraints, transactions, and a clean schema independent from the legacy backup. |
| Inertia instead of a separate API SPA                      | One authorization and routing boundary with typed React pages.                           |
| Soft deletion for customers and orders                     | Preserve history and referential integrity while supporting administrative removal.      |
| Internal and public order identifiers                      | Keep database identity separate from stable human-facing references.                     |

## Intentionally deferred

Promotion rules, analytics calculations, inventory/stock behavior, combo rules, advanced discounts, and complete ordering workflows are not defined in this foundation. Add them only from explicit requirements and record consequential decisions in `docs/decisions/`.
