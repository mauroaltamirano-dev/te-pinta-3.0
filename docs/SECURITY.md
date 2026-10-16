# Security

Laravel is the authority for authentication, authorization, validation, catalog eligibility, prices, totals and order state. UI visibility and disabled buttons are never security controls. PostgreSQL constraints back the critical invariants.

## Administrative boundary

- Every route under `/admin` is registered in one group with `auth`, `verified`, `admin` (`EnsureUserIsAdministrator`: `users.is_admin = true`) and `admin.request.log`. Web middleware keeps CSRF protection.
- Form Requests authorize through policies (`Gate::policy` registrations in `AppServiceProvider`) and validate allow-listed payloads. Actions re-resolve and lock rows before deciding.
- Write path: resolve the resource on the backend → authorize → validate → run the transactional action → return an explicit resource or redirect.
- Staff profiles (`waiter`, `cashier`) give `use-pos` / `operate-cashier` capabilities only; they never grant `/admin` access and are not mass-assignable. Future staff endpoints must take the actor from the session, never from the body.

| Identity | `administrator` | `use-pos` | `operate-cashier` |
| --- | --- | --- | --- |
| `is_admin = true` | yes | yes | yes |
| `cashier`, not admin | no | yes | yes |
| `waiter`, not admin | no | yes | no |
| no profile, not admin | no | no | no |

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

All floor, inventory and costing reads and writes require `administrator`. Floor saves must reference all and only the existing tables of the locked area; unknown keys and malformed geometry are rejected; positions and bounds are enforced by the backend and PostgreSQL. Inventory physical commands are internal actions, not public stock-edit endpoints.

## Origin, sessions and mobile install

- The admin PWA manifest does not weaken sessions, CSRF or cookies; there is no service worker or offline cache of authenticated pages.
- The operational runtime binds to loopback and trusts only its direct proxy (`TRUSTED_PROXIES=REMOTE_ADDR`).
- Fortify derives the passkey relying party from `APP_URL`; register passkeys on the stable HTTPS origin instead of widening allowed origins.
- The public gateway exposes only storefront paths and never falls back to the private runtime ([OPERATIONS.md](OPERATIONS.md#public-storefront)).

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
