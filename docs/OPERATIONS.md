# Operations

Local development, commands, testing, logging, deployment and backups.

## Local development

Laravel Sail (Docker) provides PHP 8.5, PostgreSQL 18 and Node; PHP, Composer and Node on the host are optional. Prerequisites: Docker with Compose and GNU Make.

```bash
cp .env.example .env
# Set a non-empty local DB_PASSWORD in .env.
make setup    # composer install (container), sail up, APP_KEY if empty, migrate, npm ci
make admin    # interactive `artisan admin:create`
make dev      # sail up, migrate, Vite dev server
```

`make setup` stops if `DB_PASSWORD` is empty. It never runs `migrate:fresh`, deletes volumes or creates an administrator. Never commit `.env`, `APP_KEY` or credentials. There is no public admin registration.

After first login, configure `/admin/settings/general`: delivery fee (seeded `0`), WhatsApp number (seeded empty; checkout cannot build the link until set), combined-dozen prices, raw discounts and add-ons. Run `make artisan ARGS="storage:link"` once for product images; `make artisan ARGS="db:seed --class=ProductCatalogSeeder"` seeds sample products only when no product exists.

The Sail checkout (`APP_PORT`, default 80) is for development. Do not route Tailscale to it: with `public/hot` present, pages reference the Vite server on `localhost:5173`.

Dependency policy: prefer Laravel, React, Tailwind, shadcn/ui and platform features. Add a production dependency only with a documented responsibility, maintenance/security cost and reason existing tools are insufficient.

## Commands

The root `Makefile` is the supported interface; it wraps Sail, Composer/npm scripts and the guarded `deploy/tailscale/` scripts. `make help` lists targets.

| Command | Runs |
| --- | --- |
| `make up` / `stop` / `down` / `restart` / `status` | Sail lifecycle (`down` keeps the PostgreSQL volume) |
| `make logs ARGS="--tail=100 laravel.test"` | Follow container logs |
| `make shell` | Shell in the app container |
| `make migrate` | Pending migrations on the local database |
| `make format` | Pint + Prettier (writes files) |
| `make lint-check` | Pint and ESLint checks |
| `make types` | PHPStan + `tsc --noEmit` |
| `make test` | `composer test`: config clear, Pint check, PHPStan, `php artisan test` |
| `make check` | `composer ci:check` (ESLint, Prettier check, tsc, `composer test`) + Vite production build |
| `make build` | Vite production build |
| `make e2e` | Full Playwright suite |
| `make audit` | Composer and npm dependency audits |
| `make artisan ARGS="…"`, `make npm ARGS="…"`, `make composer ARGS="…"` | Pass-through inside the app container |

There is intentionally no root target for `down --volumes`, `db:wipe` or development `migrate:fresh`.

## Testing

The backend suite runs **only on PostgreSQL**; CI (`.github/workflows/`) starts a `postgres:18-alpine` service with database `testing` and runs `composer setup` then `composer ci:check` on PHP 8.5 and Node 22.

Local test database (once):

```bash
cp .env.testing.example .env.testing   # set DB_PASSWORD to the Sail PostgreSQL password
./vendor/bin/sail exec -T pgsql-dev sh -lc 'psql -U "$POSTGRES_USER" -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname = '\''testing'\''" | grep -qx 1 || createdb -U "$POSTGRES_USER" testing'
make artisan ARGS="migrate:fresh --env=testing"   # from-scratch migration check
```

Safety guards:

- `App\Core\Support\TestingDatabaseGuard` refuses to boot with `APP_ENV=testing` unless the effective database name (including one from `DB_URL`) contains a standalone `test` or `testing` segment. It exists because Laravel falls back to `.env` when `.env.testing` is missing.
- `playwright.config.ts` and its global setup apply the same rule before `migrate:fresh`. Playwright uses its own database (`PLAYWRIGHT_DB_DATABASE`, default `testing_playwright`), serves on `127.0.0.1:${PLAYWRIGHT_PORT:-8081}` with `SESSION_DRIVER=database`, and seeds deterministic data through `tests/Support/PlaywrightDatabaseSeeder.php`.
- With `APP_ENV=testing` the local disk points to `storage/framework/testing/disks/local`; tests never write development media.

Strategy:

| Layer | Location | Proves |
| --- | --- | --- |
| Feature (PHPUnit) | `tests/Feature/<Module>/` | HTTP authorization, actions, read models, query counts, PostgreSQL constraints and triggers, concurrency with separate connections |
| Unit | `tests/Unit/` | Pure logic (testing guard, derived pricing allocator) |
| Browser | `tests/e2e/*.spec.ts` | Real desktop and mobile flows; screenshots go to `storage/app/` (not versioned) |
| Frontend unit | `tests/frontend/*.test.mjs` | Isolated presentation helpers |
| Deploy | `tests/deploy/public-gateway/test.sh`, `tests/deploy/public-app/test.sh` | Gateway allow/deny rules and public image overlay |

Rules: frontend validation never counts as security or pricing coverage; tests go through the public interface and assert every observable effect (response, persisted rows, logs without private values); mutable browser scenarios create their own namespaced records instead of relying on a first global row; never call a check passed if it did not run.

Focused runs:

```bash
make artisan ARGS="test tests/Feature/Orders"
make npm ARGS="run test:e2e -- tests/e2e/orders.spec.ts"
make npm ARGS="exec -- playwright install chromium"   # once
```

## Logging

- `AssignRequestId` adds `request_id` to the log context for every web request; `admin.request.log` (`LogAdminRequest`) emits `admin.request.completed` (module, route name, admin ID, status, duration) for unsafe methods and any response ≥ 400, at `info`/`warning`/`error` by status.
- Business events use stable names `[Module] entity.action` with structured context, for example `[Orders] order.status_changed` with `module`, public `order_id`, `admin_id`, `from`, `to`. Existing families: `[Customers] customer.*`, `[Orders] order.*` / `public_order.created` / `closed_order_edit_enabled`, `[Marketing] featured_product.*`, `[Management] wallet.*`, `transaction.*`, `outflow.*`, `transfer.*`, `distribution.*`, `order_payment.*`, `attachment.*`.
- Never log full phone numbers, addresses, customer or order notes, WhatsApp text, checkout payloads, idempotency keys, cookies, tokens, secrets, amounts in financial events, attachment names/paths, or serialized models and requests. Use internal customer IDs or public order IDs for correlation.
- Durable evidence lives in tables (`order_revisions`, ledger, attachments metadata), not logs.
- Levels: `INFO` completed business action; `WARNING` recoverable anomaly (for example `attachment.file_missing`); `ERROR` failure needing intervention. Channels are in `config/logging.php`; `storage/logs/` is never committed.

## Deployment

Production runs on the owner's notebook with Docker and Tailscale; it must stay on, with both running.

| Runtime | Bind | Purpose |
| --- | --- | --- |
| Operational app (`deploy/tailscale/`) | `127.0.0.1:8082` | Private admin/POS; scheduler container runs `schedule:work` |
| Public app (`deploy/cloudflare/public-app/`) | `127.0.0.1:8084` | Storefront-only release image; no scheduler, no migrations, no private media |
| Public gateway (`deploy/cloudflare/`) | `127.0.0.1:8083` | Default-deny nginx in front of the public app |
| n8n (`ops/n8n/`) | `127.0.0.1:5678` | Instagram automation ([STOREFRONT_AND_MARKETING.md](STOREFRONT_AND_MARKETING.md#runbook)) |
| Te Pinta 2.0 | `127.0.0.1:8080` | Previous system, kept as route rollback target |

### Private runtime (Tailscale)

The operational image uses compiled assets, `APP_DEBUG=false`, and contains no project `.env`, storage, logs or tests. It joins the existing PostgreSQL Docker network and mounts public product media and private Instagram media (group-shared, `umask 0007`, setgid directory).

```bash
cp deploy/tailscale/.env.example deploy/tailscale/.env   # new APP_KEY, DB credentials; ignored by Git
make ops-prepare                                         # build image, no service change
OPERATIONAL_RUNTIME_CONFIRM=yes make ops-start
OPERATIONAL_RUNTIME_CONFIRM=yes make ops-migrate         # take a verified backup first
make ops-verify                                          # app + scheduler + assets + /up
make route-status                                        # authority on what Serve points to
TAILSCALE_ROUTE_CONFIRM=yes make route-cutover           # Serve -> 8082
TAILSCALE_ROUTE_CONFIRM=yes make route-rollback          # Serve -> Te Pinta 2.0 on 8080
OPERATIONAL_RUNTIME_CONFIRM=yes make ops-stop
```

- Mutations refuse to run without the exact confirmation variable; the Makefile never supplies it. Rollback only re-routes Serve; it does not revert migrations or restore data.
- Tailscale Serve stays tailnet-only. The only public exception is the Funnel handler on HTTPS `:8443` for `/media/instagram` (`make media-route-status`, `TAILSCALE_FUNNEL_CONFIRM=yes make media-route-cutover`). Never publish `/`, `/admin` or `/api`.
- `TRUSTED_PROXIES=REMOTE_ADDR` trusts only the direct loopback proxy so Laravel honors the forwarded HTTPS scheme.
- `APP_URL` is the WebAuthn/passkey origin: passkeys registered on `localhost` do not work on the `*.ts.net` origin; register a new one there.
- Mobile: open the HTTPS tailnet URL in Chrome, log in, **Add to Home screen**. The manifest starts `/admin` standalone; there is no service worker or offline cache.

### Public storefront

`https://tepinta.com.ar` is served by a scoped Cloudflare named tunnel (user systemd unit `tepinta-public-cloudflared.service`, configuration outside the repo) → gateway `8083` → public app `8084`. The gateway allows only storefront pages, catalog, cart, checkout endpoints, product images and required assets; admin, auth, API, health, Instagram media, generic storage and the admin manifest are denied. It trusts `CF-Connecting-IP` only from its loopback peer.

- Run `tests/deploy/public-gateway/test.sh` before starting the gateway.
- The public app shares the production database. Releases are pinned, locally built images from a clean worktree at an approved commit (see `deploy/cloudflare/public-app/README.md` for the current tag and rollback tag); never `docker compose build` from a working checkout.
- Never point the public hostname or gateway at the private `8082` runtime. To withdraw: stop the connector and confirm it is inactive, then stop the gateway, then the public app.

### Legacy database cutover

Production (`tepinta`) was migrated from an uncommitted checkout with a different migration history. `deploy/cutover/` imports it into a freshly migrated database in one transaction (`import-legacy-database.sh <container> <source_db> <target_db>`): copies shared columns through `postgres_fdw`, assigns every row to restaurant 1, keeps production's `NOT VALID` checks, restores sequences, verifies row counts, content hashes, foreign keys, wallet balances, stock and order totals, and rolls back on any mismatch. `rehearse-import.sh` proves a clean import, refusal of a second import and rollback of a broken source on restored copies. Full checklist: `deploy/cutover/README.md`.

Before cutover: the deployed public image predates `restaurant_id` and must be redeployed from this line (its inserts would fail NOT NULL); trigger re-validation of rows written after 2026-10-16 is not automated; stop all writers including the public storefront.

## Backups

A nightly `pg_dump` of the production database and an encrypted off-site copy (rclone to Google Drive) run from operator scripts kept outside this repository; credentials and paths are not stored here. Restore a backup into a scratch database before every migration or cutover, and rehearse there. Files under `storage/app` (product images, Instagram assets, Management attachments) are not in the database and need their own copy. n8n data has its own backup procedure ([STOREFRONT_AND_MARKETING.md](STOREFRONT_AND_MARKETING.md#runbook)).
