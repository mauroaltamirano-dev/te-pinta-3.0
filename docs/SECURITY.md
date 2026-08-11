# Security

Security decisions are enforced by Laravel on the server. Frontend visibility and validation are usability features, not security controls.

## Authentication

- Use Laravel Fortify/session authentication rather than custom authentication.
- Administrator accounts are provisioned through an explicit trusted operator workflow.
- Public administrator registration is disabled.
- Login remains rate limited.
- Web requests retain Laravel CSRF protection and secure cookie/session defaults appropriate to the environment.
- Production uses HTTPS, `APP_DEBUG=false`, and secure session cookies.

## Authorization

Every administrative route and endpoint is grouped below `/admin` and must require `auth`, `verified`, and `admin` middleware, plus resource policies where the use case requires them. A hidden React link or button is never sufficient.

For every resource read or write:

1. Resolve the resource on the server.
2. Authorize the specific action against the authenticated administrator.
3. Validate the allowed input.
4. Execute the use case, using a transaction when required.
5. Return only fields needed by the page.

Changing a URL or resource ID must not bypass authorization. Avoid exposing internal incremental IDs in public URLs when a public identifier is available.

## Input and business integrity

- Use Form Requests or explicit Laravel validators.
- Do not accept roles, permissions, prices, totals, discounts, or state transitions as authoritative client data.
- Read prices and eligible states from server-owned data.
- Validate identifiers and resource relationships, not only their scalar format.
- Use explicit model fillable fields or explicit assignments; never indiscriminately mass assign request payloads.
- Public Inertia props and JSON responses expose only the necessary fields.

### Product catalog controls

- Every product/category write is inside the existing `/admin` middleware group and is authorized again by Product/Category policies or Form Requests.
- Product input uses an explicit allowlist. Categories and tags must exist; all three base prices and `sort_order` must be non-negative integers; availability must be boolean.
- Slugs and `image_path` are server-owned. Clients cannot submit arbitrary storage paths.
- Uploads are validated server-side as JPEG, PNG, or WebP, with matching MIME/extension checks and a 4 MiB maximum.
- Images are written through Laravel's `public` disk. A failed database transaction removes the newly uploaded file; a successful replacement/removal deletes the prior file and logs only the product ID if cleanup fails.
- `AdminProductResource` and `PublicProductResource` explicitly shape output. `GET /menu/catalog` omits internal IDs, availability flags, storage paths, timestamps, and soft-delete metadata.
- Public catalog queries require both an available product and an active category. Soft-deleted products/categories are excluded by Eloquent or explicit query constraints.
- Cooking prices come from integer product prices plus `cooking_surcharges` on the backend. React input or totals are never authoritative.

Uploaded files live under `storage/app/public/products/`; URLs are generated with `Storage::disk('public')->url(...)`. Create the public symlink per environment with `./vendor/bin/sail artisan storage:link`. Never expose arbitrary filesystem paths.

## Secrets and private data

Never commit `.env`, credentials, application keys, API tokens, cookies, or production data. Never log passwords, tokens, cookies, secrets, full payment-card data, or unnecessary complete addresses/phone numbers.

Use redaction or stable internal identifiers when correlation is needed. There is no online payment feature in the defined scope.

## Errors

- Production must not display stack traces or infrastructure details.
- Public error responses should be actionable without exposing internals.
- Log internal context separately and safely.

## Required security tests

- Guests cannot access admin pages or execute admin endpoints.
- URL changes do not bypass route protection.
- Resource ID changes cannot access or modify unauthorized data.
- Client-supplied roles do not grant privilege.
- Client-supplied prices/totals are ignored as sources of truth.
- Order and payment states reject unsupported values.
- Invalid inputs are rejected on the server.
- Login rate limiting is active.
- Public endpoints do not expose internal fields.

## Deployment checklist

- [ ] `APP_ENV=production` and `APP_DEBUG=false`.
- [ ] HTTPS and secure session cookie settings enabled.
- [ ] PostgreSQL is not publicly reachable.
- [ ] Application database user has minimum privileges.
- [ ] Secrets are supplied by the platform, not repository files.
- [ ] Logs and error monitoring are access controlled.
- [ ] Authorization and rate-limit tests pass.
