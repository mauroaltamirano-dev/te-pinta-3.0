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
