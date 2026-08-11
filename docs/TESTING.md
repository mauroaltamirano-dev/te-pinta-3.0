# Testing

Tests prove backend authority, module behavior, and critical user flows. Frontend validation alone cannot prove security.

## Test layers

| Layer               | Location/tool                               | Purpose                                                                           |
| ------------------- | ------------------------------------------- | --------------------------------------------------------------------------------- |
| Feature/integration | `tests/Feature/`, Laravel test runner       | Routes, middleware, authorization, validation, persistence, Inertia props         |
| Unit                | `tests/Unit/`, Laravel test runner          | Focused domain behavior without unnecessary framework integration                 |
| Browser             | Playwright (`playwright.config.ts`)         | Functional navigation, responsive behavior, and visual regression of stable flows |
| Static/format       | Pint, PHPStan, ESLint, Prettier, TypeScript | Consistency and type/static correctness                                           |

## Commands

```bash
# PHP format, static analysis, and Laravel suite
./vendor/bin/sail composer test

# Focused Laravel test
./vendor/bin/sail artisan test --filter=AuthenticationTest

# Frontend quality
./vendor/bin/sail npm run lint:check
./vendor/bin/sail npm run format:check
./vendor/bin/sail npm run types:check
./vendor/bin/sail npm run build
./vendor/bin/sail npm run test:e2e
```

Use `./vendor/bin/sail npm run test:e2e:ui` for Playwright's interactive runner. A configured runner without relevant assertions is not feature coverage; report the specs actually executed.

### Products focused checks

```bash
./vendor/bin/sail artisan test tests/Feature/Products
./vendor/bin/sail npm run test:e2e -- tests/e2e/products.spec.ts
```

`tests/Feature/Products/` covers guest/admin CRUD boundaries, validation, category/tag relationships, ordering, soft deletion, image validation/replacement/removal, admin visibility, public filtering/output minimization, backend price sourcing, persisted cooking surcharges, and the repeatable initial seeder.

`tests/e2e/products.spec.ts` authenticates through the real admin login, opens Products, verifies seeded catalog content, creates a tagged product, edits it, disables it, and checks the visual unavailable state. `tests/e2e/global-setup.ts` prepares deterministic seeded data and the Playwright administrator.

## Minimum security coverage

Tests must demonstrate that:

- guests cannot open admin pages or call admin write endpoints;
- changing a URL does not bypass authentication/authorization;
- changing resource IDs does not expose or modify unauthorized resources;
- client-supplied roles cannot grant administrative privilege;
- client-supplied prices or totals are not authoritative;
- unsupported order/payment states are rejected;
- invalid server input is rejected;
- login is rate limited;
- public responses omit internal/sensitive fields.

## Test design rules

- Prefer behavior-oriented names that state the security or business invariant.
- Use `RefreshDatabase` for persistence-facing tests.
- Test the HTTP boundary for middleware, policies, validation, and Inertia props.
- Add lower-level tests only when they provide clearer failure diagnosis.
- Keep fixtures minimal and explicit; factories must not silently grant privileges.
- For visual tests, use deterministic data and representative mobile/public and tablet/admin viewports.

## Before delivery

1. Run the smallest relevant test while implementing.
2. Run the full affected backend/frontend suite.
3. Run formatting, lint, type checks, and production build.
4. Report exact commands and failures/skips; never describe an unexecuted check as passing.
