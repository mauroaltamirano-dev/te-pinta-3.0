# Storefront and marketing

Covers the public site and checkout (`PublicSite`, `Products`, `Orders`), featured products (`Marketing`) and Instagram Story automation (`Instagram`). Marketing and Instagram are sibling domains: Marketing decides **what** to feature on the storefront; Instagram decides **how and when** a Story is published. Neither calls the other ([ADR 0005](decisions/0005-marketing-and-instagram-are-separate-domains.md)).

## Public site

| Route | Purpose |
| --- | --- |
| `GET /` | Home (`PublicHomeController`): hero and the currently visible featured product, if any |
| `GET /menu` | Menu page; data from `GET /menu/catalog` |
| `GET /menu/{slug}` | Product detail |
| `GET /cart` | Cart page |
| `GET /checkout/options` | Available add-ons and today's business date |
| `POST /checkout/price-preview` | Backend quote (throttle 20/min) |
| `POST /checkout/orders` | Create the order (throttle 5/min) |

- Products are publicly sellable only when available, not soft-deleted, and in an active, non-deleted category. Public product data: slug, name, pricing family, description, long description, public ingredients, image URL, category and tags (slug and name), and prices (unit; half-dozen, stored as six units, and dozen only for `empanada`). No internal IDs.
- `long_description` and `public_ingredients` are editorial text managed in Products; they are not derived from recipes or stock.
- Product image URLs are same-origin `/storage/products/...` paths built only from validated basenames, so the public and private origins each serve images from their own host.
- The cart lives in the browser's `sessionStorage` (`features/orders/cart-context.tsx`). Prices shown are informative; the backend re-prices on preview and creation.

## Public checkout

1. The visitor sends product and add-on **slugs**, quantities, condition, delivery or pickup, schedule, name, phone, optional address and notes, plus an idempotency key.
2. Slugs are resolved to internal IDs only after sellability checks (`ResolvePublicCheckoutSelections`); `OrderPricingEngine` prices the order.
3. `CreateOrder` runs one transaction: normalizes the phone, matches an active customer by normalized phone or creates one, persists the order with status `pending_confirmation` and payment `unpaid`, and stores all snapshots.
4. The response contains the public order ID, status, payment status, backend price breakdown, summary and a `wa.me` link to `order_settings.whatsapp_order_phone`.

- Idempotency: the key is stored as an HMAC (`app.key`) plus a payload fingerprint with a unique index. An exact retry returns the same order; reusing a key with a different payload is rejected.
- The public request cannot set customer ID, status, payment status or method, discount, total, shipping or combined price, and cannot choose `dine_in`.
- Matching a customer never returns or overwrites that customer's private data; the order stores the visitor-supplied snapshots.
- An empty WhatsApp setting makes link generation fail with an explicit "not configured" error after the order is saved. Opening WhatsApp only hands off to the customer; it does not prove a message was sent.
- No online payment exists.

Deployment of the public storefront (`tepinta.com.ar` through a Cloudflare tunnel and a default-deny gateway) is described in [OPERATIONS.md](OPERATIONS.md#public-storefront).

## Featured products (Marketing)

Admin: `/admin/marketing` (create, edit, preview, disable). The product settings page reads one product's schedule through `GET /admin/marketing/products/{product}/featured` and uses the same Marketing commands.

`marketing_featured_products` keeps every past, current, future and disabled recommendation: `product_id`, optional plain-text `headline` (≤ 80) and `short_copy` (≤ 240), `starts_at` (inclusive, required), `ends_at` (exclusive, optional), `is_enabled`. It never copies catalog fields or prices.

| Derived status | Rule |
| --- | --- |
| Inactive | `is_enabled = false` |
| Finished | enabled and `ends_at <= now` |
| Scheduled | enabled and `starts_at > now` |
| Active | enabled, `starts_at <= now`, and `ends_at` null or `now < ends_at` |

- Windows are `[starts_at, ends_at)`; adjacent windows are valid. Enabled windows cannot overlap: a partial GiST exclusion constraint (per restaurant) is the concurrency authority, and SQLSTATE `23P01` is translated into the same validation error as the application's pre-check. No cron is needed for status changes.
- Instants are stored in UTC; admin input and display use the Orders business time zone, never the browser's.
- Public visibility additionally requires the product to be sellable. An unavailable product keeps its history and regains visibility if it becomes sellable again within the window. New recommendations can only select currently sellable products.
- One public-safe view model (`Queries/BuildFeaturedProductPublicView.php`) feeds both the home card and the admin preview: product slug, name, description, image URL, current public prices, headline and copy. No internal IDs, admin identity or storage paths. The card's call to action adds one unit to the existing cart.

### Dashboard summary

`Queries/BuildMarketingDashboardSummary.php` returns, in order of preference: the active publicly visible recommendation; an active but not publicly visible one with its reason; the first scheduled enabled one; or an empty state. Dashboard renders it and links to `/admin/marketing`.

Not implemented: discounts, promotions, coupons, combos, banners, Marketing-to-Instagram flows.

## Instagram Story automation

Laravel keeps assets, schedules, the queue and publication history. n8n Community Edition (deployed separately from `ops/n8n/`) claims work through a bearer-protected API and performs the official Meta container/publish calls. n8n never reads PostgreSQL.

```text
Laravel scheduler -> pending publication rows
n8n -> GET /api/automation/instagram/stories/pending (atomic claim) -> signed JPEG URL
n8n -> Meta POST /{ig-user-id}/media -> POST /media_publish
n8n -> POST .../{publication}/published | .../failed
```

### Data and rules

| Table | Purpose |
| --- | --- |
| `instagram_automation_settings` | Operator switch (default off) |
| `instagram_story_assets` | Private normalized JPEGs and metadata |
| `instagram_story_schedules` | Name, local time, ISO weekdays `1..7`, asset, active flag, `sort_order` 1–999 |
| `instagram_story_publications` | Queue and immutable history: unique `(schedule_id, scheduled_for)`, `queue_order` snapshot, attempts, Meta IDs, failure code, `requires_manual_review`, `archived_at` |

- `instagram:generate-publications` runs every minute and creates `pending` rows that are due in `INSTAGRAM_AUTOMATION_TIMEZONE`; the unique key makes repeated runs harmless. `instagram:fail-stale-publications` runs every five minutes.
- The claim locks one eligible row with `FOR UPDATE SKIP LOCKED` (ordered by due instant, queue order, ID), moves it `pending → processing` and increments attempts. Callbacks accept only `processing` rows.
- Work is claimable only when both `INSTAGRAM_AUTOMATION_ENABLED` and the UI switch are on.
- A retryable failure before publishing may return to `pending` until `INSTAGRAM_AUTOMATION_MAX_ATTEMPTS`. An ambiguous `media_publish` result (`ambiguous: true`) or a row left in `processing` past the timeout becomes `failed` with `requires_manual_review`; it is never re-queued automatically. The system prefers missing a Story over publishing a duplicate.
- Admin retry requires a failed, retryable row with attempts left, no manual-review flag and no Meta media ID. Cancel is limited to unclaimed pending or non-ambiguous failed rows. Archiving sets `archived_at`; the 30-day bulk archive skips pending, processing and manual-review rows.
- Uploads: real JPEG or PNG up to 8 MiB; PNG is flattened on white and normalized to JPEG. Files live on the private disk under `instagram/stories/` with generated names; 1080 × 1920 is recommended and other ratios show a warning. Assets referenced by schedules or publications cannot be deleted; an asset used by an active schedule cannot be deactivated.
- Media are served at `/media/instagram/{asset}/story.jpg` through a temporary signed URL (TTL `INSTAGRAM_AUTOMATION_MEDIA_URL_TTL_MINUTES`), built on `INSTAGRAM_AUTOMATION_PUBLIC_MEDIA_BASE_URL`. Meta must be able to download it from the public internet: the private Tailscale origin is not public, so only `/media/instagram` is exposed through a Tailscale Funnel handler on HTTPS `:8443` (`make media-route-status`, `TAILSCALE_FUNNEL_CONFIRM=yes make media-route-cutover`).
- Known issue: `/admin/marketing/instagram` returns HTTP 500 when the public media base URL is missing from the environment; it should degrade gracefully.

### Environment

| Variable | Meaning (default) |
| --- | --- |
| `INSTAGRAM_AUTOMATION_ENABLED` | Deployment kill switch (`false`) |
| `INSTAGRAM_AUTOMATION_TIMEZONE` | Schedule time zone (`America/Argentina/Cordoba`) |
| `INSTAGRAM_AUTOMATION_MAX_ATTEMPTS` | Claims allowed for retryable failures (`3`) |
| `INSTAGRAM_AUTOMATION_PROCESSING_TIMEOUT_MINUTES` | Age at which `processing` becomes manual review (`20`) |
| `INSTAGRAM_AUTOMATION_MEDIA_URL_TTL_MINUTES` | Signed URL lifetime (`90`) |
| `INSTAGRAM_AUTOMATION_MEDIA_DISK` | Private disk (`local`) |
| `INSTAGRAM_AUTOMATION_PUBLIC_MEDIA_BASE_URL` | Public HTTPS origin used only for media (`APP_URL`) |
| `N8N_AUTOMATION_TOKEN` | Bearer secret shared only with the n8n POS credential |

Generate the token with a password manager or `openssl rand -hex 32`; never reuse `APP_KEY` or the Meta token.

### Runbook

First publication:

1. Deploy, run `php artisan migrate --force` and `php artisan db:seed --class='Database\Seeders\InstagramStoryAutomationSeeder' --force` (idempotent: two inactive schedules without assets, "Pedidos abiertos" 11:00 and "Menú" 18:30, every day).
2. Set the environment above; make sure the scheduler runs (`schedule:work` service in the operational runtime, or `* * * * * php artisan schedule:run`). Verify with `php artisan schedule:list`.
3. Upload assets in **Marketing → Instagram**, assign them to schedules, then enable the schedules.
4. Deploy n8n: `cd ops/n8n && cp .env.example .env`, set `N8N_ENCRYPTION_KEY`, `POS_BASE_URL`, `META_GRAPH_API_VERSION`, `META_INSTAGRAM_USER_ID`, then `docker compose up -d`. n8n binds to `127.0.0.1:5678`; reach the editor through an SSH tunnel.
5. Import `ops/n8n/workflows/instagram-stories-publisher.json`. Create two Header Auth credentials: `Te Pinta POS Automation` (`Authorization: Bearer <N8N_AUTOMATION_TOKEN>`) for the claim and callback nodes, and `Meta Instagram Graph API` (`Authorization: Bearer <long-lived Meta token>`) for the container, status and publish nodes.
6. With automation off, run the workflow once and expect `204`. Then queue a controlled test with **Enviar a cola**, turn both switches on, run the workflow manually, and verify one Story, one `published` row with `attempts=1` and stored IDs. Only then activate the workflow (it polls every two minutes and claims at most one publication).

Meta setup: the Instagram account must be **Business** and linked to a Facebook Page; the app needs the publishing permissions of the chosen login model (`instagram_basic` + `instagram_content_publish` with Facebook Login on `graph.facebook.com`; `instagram_business_*` with Instagram Login on `graph.instagram.com` via `META_GRAPH_BASE_URL`). Pin an explicit Graph version. Re-check Meta's content-publishing documentation before each version change; API publishing is limited per 24 hours (check `content_publishing_limit`).

Shutdown: turn off the UI switch; for an incident also set `INSTAGRAM_AUTOMATION_ENABLED=false` and reload configuration. If credentials may be compromised: deactivate the n8n workflow, rotate `N8N_AUTOMATION_TOKEN` on both sides, revoke the Meta token, review history before re-enabling. Never delete pending or processing rows to hide an incident.

| Symptom | Safe action |
| --- | --- |
| Claim returns `401` | Token differs or is empty; rotate on both sides |
| Claim returns `204` | Check switches, due time, schedule/asset active flags, attempts |
| Meta cannot fetch `media_url` | Test the exact signed URL from outside the tailnet; check Funnel, DNS/TLS, expiry, clock |
| Publication stuck in processing | Wait for the timeout to flag manual review, then reconcile in Instagram and n8n |
| Published callback failed | Do not retry: Meta may have published; reconcile manually |
| Rate-limit error | Pause claims and wait for quota |

n8n backups: the `n8n-data` volume (SQLite, encrypted credentials, workflow state) is usable only with the matching `N8N_ENCRYPTION_KEY`. Stop n8n, archive the volume, store the archive and the key off-site and separately from the repository. Pin `N8N_IMAGE` to an exact version; back up before upgrading.

Browser automation, private Instagram APIs and device emulation are out of scope.
