# Security

Laravel is the authority for authentication, authorization, validation, catalog eligibility, prices, totals and order state. UI visibility and disabled buttons are never security controls. PostgreSQL constraints back the critical invariants.

## Administrative boundary

- Every route under `/admin` is registered in one group with `auth`, `verified`, `restaurant.member` (`EnsureActiveMembership`: an active `restaurant_user` row for the current restaurant) and `admin.request.log`, and each route adds its own `can:<permission>` middleware. `AdminRoutePermissionCoverageTest` fails when an admin route has no permission. Web middleware keeps CSRF protection.
- Form Requests authorize through policies (`Gate::policy` registrations in `AppServiceProvider`) that check the same permissions, and validate allow-listed payloads. Actions re-resolve and lock rows before deciding.
- Write path: resolve the resource on the backend → authorize → validate → run the transactional action → return an explicit resource or redirect.
- Password login accepts only users with an active membership in the current restaurant; anyone else receives the same failure as a wrong password. Passkey login applies the same rule (`Passkeys::authorizeLoginUsing`). A deactivated member is rejected on the next request.
- `users.is_admin` and `users.staff_profile` are legacy columns kept one release for rollback; no code reads them, and they are not mass-assignable or serialized.

### Roles and permissions

Roles and permissions are code-defined ([ADR 0013](decisions/0013-code-defined-roles-and-permissions.md)); `Identity\Enums\Role::permissions()` is the only mapping. Each permission is a Gate ability of the same name.

| Permission | owner (Dueño) | manager (Encargado) | cashier (Cajero) | waiter (Mozo) | kitchen (Cocina) | accountant (Contador) |
| --- | --- | --- | --- | --- | --- | --- |
| `dashboard.view` (dashboard) | ✓ | ✓ | ✓ | | | ✓ |
| `orders.view` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `orders.manage` (create, edit, status, payment, restaurant floor reading) | ✓ | ✓ | ✓ | ✓ | | |
| `customers.view` | ✓ | ✓ | ✓ | ✓ | | ✓ |
| `customers.manage` | ✓ | ✓ | ✓ | | | |
| `catalog.view` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `catalog.manage` (products, categories, tags, ordering settings) | ✓ | ✓ | | | | |
| `inventory.view` | ✓ | ✓ | | | ✓ | ✓ |
| `inventory.manage` | ✓ | ✓ | | | | |
| `finance.view` (management overview, audit, purchases, attachments) | ✓ | ✓ | | | | ✓ |
| `finance.manage` | ✓ | ✓ | | | | |
| `marketing.manage` (featured products, Instagram) | ✓ | ✓ | | | | |
| `floor.manage` (restaurant floor layout) | ✓ | ✓ | | | | |
| `settings.manage` (reserved; no route uses it yet) | ✓ | | | | | |
| `users.manage` | ✓ | | | | | |

GET pages of a module require its `*.view` permission; every mutating route (and the create/edit form pages) requires `*.manage`. `/admin` (quick order) redirects members without `orders.manage` to `/admin/orders`. `RoleRouteMatrixTest` proves the matrix for representative routes of every module.

Updating the restaurant accent uses `catalog.manage`, matching General settings. Both the branding route and `UpdateBrandingRequest` enforce the active membership permission; UI visibility is not an authorization boundary.

| Capability gate | Granted to | Use |
| --- | --- | --- |
| `use-pos` | `orders.manage` holders | `CreateOrder` for the internal `pos` source |
| `operate-cashier` | owner, manager, cashier | Required to create a POS order already marked paid, and to print a table bill, charge or cancel a table |

Table service (`Orders\Support\TableSessionGuard`): confirming rounds, voids and requesting the bill need `use-pos`; a `waiter` acts only on tables they opened, while owner, manager and cashier act on any table. Kitchen and accountant cannot take tables. Rounds are immutable in PostgreSQL; an open table's order cannot be edited, re-statused, paid or trashed through the generic order routes. The table screens (`/admin/tables`, `/admin/table-sessions/*`) sit behind `orders.manage`; printing the bill, charging and cancelling additionally require a cashier role in `TableSessionGuard` (charging also in its Form Request).

### User management

`/admin/users` (`users.manage`) lists the restaurant's members, creates a user (name, email, temporary password, role; the email is marked verified), changes a role and deactivates or reactivates a membership. Nobody can change their own role or deactivate themselves, and the last active owner cannot be demoted or deactivated; `MembershipGuard` locks the target and the active owner rows in one ordered statement so concurrent changes cannot bypass it. Inertia shares `auth.role` and `auth.permissions`; the sidebar shows only modules the user can open, but the routes are the control. `php artisan admin:create` creates a verified user with an `owner` membership.

## Orders

- Edit, enable-edit and update routes bind `Order` by its immutable `public_id`. `UpdateAdminOrderRequest` forbids IDs, timestamps, revision metadata, edit version and all client price fields; `expected_version` is mandatory.
- Closed orders (`delivered`, `cancelled`) require a 3–300 character reason that creates a session permission bound to admin, order and version, consumed by one edit.
- Editing the order address never overwrites `customers.address`. `order_revisions` stores changed field names, not values.
- Paid orders require an enum payment method; unpaid orders clear it; a zero total becomes `not_required`, so a 100 % discount never invents a payment. The backend computes `discount_amount`.

## Public checkout

| Control | Implementation |
| --- | --- |
| Catalog identifiers | Product and add-on slugs; internal IDs rejected by strict array-key validation |
| Eligibility | Available, not deleted, active non-deleted category |
| Price authority | `OrderPricingEngine` reloads prices and settings on every preview and creation |
| State | Status `pending_confirmation`, payment `unpaid`; source, status, payment, discount, total, shipping and combined price cannot be sent |
| Customer privacy | Phone matching happens only inside `CreateOrder`; no lookup endpoint; matched profile data is never returned or overwritten |
| Duplicates | Required idempotency key stored as HMAC with a payload fingerprint and a unique index |
| Abuse | Throttle: preview 20/min, creation 5/min |
| Ordering hours | Creation is rejected outside the configured weekly window (`PublicOrderingHours`); the visitor cannot send date, period or time |

The public response contains only the public order ID, status, payment status, backend price breakdown, summary and WhatsApp URL.

## Private and historical data

- Customer notes are admin-only; they never reach orders, public resources or WhatsApp. Order notes may appear in the order summary.
- Historical views read snapshots; current customer or catalog data cannot silently rewrite them.
- Logging rules: [OPERATIONS.md](OPERATIONS.md#logging).

## Management

- The client sends a positive amount and an allowed type; the backend sets signs, entries, creator and reversal links. Archived wallets are rejected for new operations; only `ReverseFinancialTransaction` may compensate them.
- Confirmed financial facts have no update or delete routes, and PostgreSQL rejects direct mutation.
- The audit is read-only: no endpoint repairs ledger or semantic data.
- Attachments: PDF/JPEG/PNG/WEBP up to 10 MB with real MIME checks; UUID paths on the private `local` disk, never under public storage; nested routes re-check that the attachment belongs to the transaction; responses never expose `disk` or `path` and use `Cache-Control: private, no-store`, `X-Content-Type-Options: nosniff`, a sandbox CSP and a sanitized download name. PHP and proxy upload limits must allow 10 MB.

## Instagram automation

- n8n authenticates with a dedicated high-entropy bearer token (`N8N_AUTOMATION_TOKEN`); it never receives a user session, database credentials or the Meta token from the POS. The API is rate-limited (30/min per IP) before token authentication.
- Callbacks validate the current publication state and redact bearer tokens, access tokens and signed-query material before storing external errors.
- Media are decoded, size- and pixel-limited, normalized to JPEG, stored under a generated private path and served only through a temporary signed URL with the fixed filename `story.jpg`; the controller rejects paths outside `instagram/stories/`.
- Ambiguous or stale publications are never retried automatically.

## Marketing

Admin input is plain text only (headline ≤ 80, copy ≤ 240), product selection, enabled flag and business-local schedule converted to UTC on the server. The public view model contains no internal IDs, admin identity or storage paths. Marketing has no Instagram, n8n or Meta credentials.

## Restaurant floor and Inventory

Floor reads require `orders.manage` and floor writes `floor.manage`; inventory and costing reads require `inventory.view` and writes `inventory.manage`. Floor saves must reference all and only the existing tables of the locked area; unknown keys and malformed geometry are rejected; positions and bounds are enforced by the backend and PostgreSQL. Inventory physical commands are internal actions, not public stock-edit endpoints.

## Origin, sessions and mobile install

- The admin PWA manifest does not weaken sessions, CSRF or cookies; there is no service worker or offline cache of authenticated pages.
- The operational runtime binds to loopback and trusts only its direct proxy (`TRUSTED_PROXIES=REMOTE_ADDR`).
- Fortify derives the passkey relying party from `APP_URL`; register passkeys on the stable HTTPS origin instead of widening allowed origins.
- The public gateway exposes only storefront paths and never falls back to the private runtime ([OPERATIONS.md](OPERATIONS.md#public-storefront)).
- The public gateway sends, on every response: `Strict-Transport-Security: max-age=31536000`, `Content-Security-Policy` (`default-src 'self'`; scripts, fonts, images and API calls same-origin; inline styles allowed for the brand palette; `object-src 'none'`, `base-uri 'self'`, `form-action 'self'`, `frame-ancestors 'none'`, `upgrade-insecure-requests`), `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff` and `Referrer-Policy: strict-origin-when-cross-origin`. Origin copies are hidden so each header is sent once. A storefront change that loads a third-party script, font, image or API must update the policy in `deploy/cloudflare/nginx.conf` and its test.

## Tenancy

All rows carry `restaurant_id`, but reads are **not** isolated between restaurants yet. Do not onboard a second restaurant before row-level security is in place ([ADR 0012](decisions/0012-tenant-ready-schema.md)).

## WhatsApp

The destination number comes from `order_settings.whatsapp_order_phone`; no real number is hard-coded. The app only builds a `wa.me` URL; it sends nothing and authenticates no one.

## Production checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, `SESSION_SECURE_COOKIE=true`.
- [ ] PostgreSQL is not publicly reachable and uses least privilege.
- [ ] Secrets come from local, Git-ignored environment files or the platform, never from Git.
- [ ] WhatsApp destination and delivery fee configured deliberately.
- [ ] Logs are access-controlled and free of PII payloads.
- [ ] Guest/admin, price-tampering, public-field, idempotency and rate-limit tests pass.
- [ ] Tailscale Serve and Funnel routes verified explicitly (`make route-status`, `make media-route-status`).
- [ ] Known dependency advisories triaged (`make audit`).
- [ ] Release image runs a patched PHP and loads `deploy/tailscale/php-security.ini` (upload progress off, upload limits 10M/12M).
