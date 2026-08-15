# Security

Laravel is the authority for authentication, authorization, validation, catalog eligibility, prices, totals, and order state. UI visibility and disabled buttons are not controls.

## Administrative boundary

Every `/admin` module route uses `auth`, `verified`, `admin`, and `admin.request.log`. Customer/order/settings requests also authorize through policies or explicit administrator checks. Laravel web middleware retains CSRF protection.

For each administrative write:

1. Resolve the resource on the backend.
2. Authorize the action.
3. Validate an allowlisted payload.
4. Execute the focused action/transaction.
5. Return an explicit resource or redirect.

Paid administrative orders require an enum-backed payment method (`cash`, `bank_transfer`, or `debit_card`); unpaid orders prohibit and clear it. A backend-calculated zero total is canonicalized as `not_required` with no method, so a 100% discount never fabricates a payment. Admin discount inputs contain only a validated type/value. The backend calculates and persists `discount_amount`; public requests prohibit discount and payment-method fields.

## Safe administrative order editing

- Edit, enable-edit, and update routes resolve `Order` by immutable `public_id`, require the admin middleware stack, authorize the order policy, and use CSRF-protected web requests.
- `UpdateAdminOrderRequest` reuses the checkout allowlist and adds enum-validated operational status plus required `expected_version`. IDs, timestamps, revision metadata, edit version, and all client-supplied pricing fields are prohibited.
- `UpdateOrder` locks the row and rejects a stale version. Status-only and payment-only actions also advance `edit_version`, closing the silent-overwrite gap between surfaces.
- Delivered/cancelled orders are denied by default. Enabling editing requires a 3–300 character reason and creates an authenticated-session grant bound to admin, order, and version. It authorizes one successful edit only and never changes status by itself.
- An edit updates the order address snapshot only; it does not overwrite `customer.address`. Changing customer updates the order's customer reference/snapshots without mutating that customer's private profile.
- `order_revisions.changed_fields` stores field names and an economic-change flag, not values. It does not contain complete phone, address, or note content. The closed-order reason is bounded but must remain brief and must not contain unnecessary private information.

## Public checkout boundary

| Control                 | Implementation                                                                              |
| ----------------------- | ------------------------------------------------------------------------------------------- |
| Catalog identifiers     | Stable product/addon slugs; internal IDs are rejected                                       |
| Product eligibility     | Products must be available, not soft-deleted, and belong to an active, non-deleted category |
| Price authority         | `OrderPricingEngine` loads server prices/settings again on create                           |
| State/payment authority | Public status is `pending_confirmation`; payment is always `unpaid`                         |
| Customer privacy        | Phone matching happens only inside `CreateOrder`; no lookup endpoint exists                 |
| Duplicate protection    | Required idempotency key, keyed HMAC at rest, payload fingerprint, unique constraint        |
| Abuse control           | Preview: 20/minute; creation: 5/minute via Laravel throttle middleware                      |

Public requests cannot submit `customer_id`, `status`, `payment_status`, `total`, `shipping_fee`, or `combined_price`. Extra internal catalog IDs are rejected by strict array key validation. Quantities and array sizes are bounded.

The checkout normalizes the required phone and may attach the order to an existing active customer. It never returns that customer's name, address, notes, history, or internal ID; never preloads private profile data; and never overwrites the matched profile. The order stores visitor-provided snapshots separately.

Exact idempotent retries return the existing order. Reusing a key for a changed payload fails validation. The stored HMAC prevents raw checkout tokens from being persisted.

## Private and historical data

- Customer notes are administrative only. They are not copied to orders, public resources, or WhatsApp summaries.
- Order notes are separate and may appear in the order summary/WhatsApp message.
- Do not log complete phone numbers, addresses, notes, request payloads, tokens, cookies, or secrets.
- Public checkout returns only Order ID, state, payment state, nullable backend base/cooking breakdown, backend total/summary, and WhatsApp URL.
- Historical displays use order snapshots; current customer/catalog data cannot silently rewrite them.

## Recoverable deletion

- Order and customer deletion is soft deletion. Trash listing and restore are protected by the normal administrator middleware and policies.
- There is no force-delete route or UI. Customer deletion/restoration never cascades, deletes, or rewrites linked orders.
- Restoring a customer rechecks active normalized-phone uniqueness on the backend. A conflict is rejected without mutation; the system does not merge records or clear either phone to make the restore pass.
- Restore resources expose only the fields required by their administrative trash screens.

## Mobile installation and origin security

The web manifest makes the authenticated admin UI installable but does not weaken its normal session, CSRF, authorization, or secure-cookie boundaries. There is intentionally no service worker: Te Pinta does not cache authenticated HTML, order data, or writes for offline use.

Install only from the stable HTTPS Tailscale origin after the route is deliberately cut over. `SESSION_SECURE_COOKIE=true`, an encrypted database session, `HttpOnly`, and `SameSite=Lax` are part of the operational template. “Remember me” may keep the browser session convenient, but it is not a substitute for device lock or explicit logout on a shared/lost phone.

The Tailscale runtime trusts only its direct caller as a reverse proxy (`TRUSTED_PROXIES=REMOTE_ADDR`). The service remains bound to loopback, so Laravel can honor Tailscale Serve's forwarded HTTPS scheme without extending that trust to the LAN-facing development runtime.

Fortify derives the passkey relying-party ID and allowed origin from `APP_URL`. Passkeys registered on `localhost` belong to a different origin and cannot authenticate at the `*.ts.net` URL; after password login, register a passkey on the stable HTTPS origin instead of broadening allowed origins.

## WhatsApp

The business destination number comes from `whatsapp_order_phone`. No real phone is hardcoded. The initial value is empty: checkout persists the order, then link generation returns an explicit “not configured” validation error until an administrator sets the number.

The application generates a `wa.me` URL only. It does not send a message, authenticate the visitor, or infer that the order was confirmed.

## Production checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, secure cookies.
- [ ] PostgreSQL is not publicly reachable and uses least privilege.
- [ ] Secrets come from the deployment platform, not Git.
- [ ] WhatsApp destination and delivery fee are deliberately configured.
- [ ] Logs/monitoring are access-controlled and do not contain PII payloads.
- [ ] Guest/admin, tampered-price, public-field, idempotency, and rate-limit tests pass.
- [ ] The operational `APP_URL` is the stable HTTPS origin and session-cookie settings are secure.
- [ ] Tailscale Serve cutover is verified explicitly; staging port `8082` alone is not proof of exposure.
