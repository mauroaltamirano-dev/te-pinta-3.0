# Operations

Local development, commands, testing, logging, deployment and backups.

## Local development

Laravel Sail (Docker) provides PHP 8.5, PostgreSQL 18 and Node; PHP, Composer and Node on the host are optional. Prerequisites: Docker with Compose and GNU Make.

```bash
cp .env.example .env
# Set a non-empty local DB_PASSWORD and DB_HOST=pgsql-dev (the Sail database service) in .env.
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

### Browser data isolation

Browser scenarios import `test` and `expect` from `tests/e2e/support/test.ts`; type-only Playwright imports stay in `@playwright/test`. The automatic fixture recreates the explicitly test-named PostgreSQL database schema and runs `PlaywrightDatabaseSeeder` before each scenario, including retries and mobile/desktop projects. A scenario must not depend on another scenario's writes. Pure database-safety checks deliberately use the base Playwright test without the reset fixture.

The current browser harness has one application server and one database, so `workers: 1` is intentional. A parallel `--workers` override is rejected before global setup can reset data; the per-scenario reset repeats that guard. Separate servers/databases would be required before enabling parallel execution. Run only one suite per database; simultaneous runs or other worktrees need distinct database names and server ports. Per-scenario reseeding costs runtime but prevents global totals, settings and catalog edits from leaking between files. Database names must contain a standalone `test`/`testing` segment, use lowercase letters/digits/underscores and fit PostgreSQL's 63-character identifier limit. No reset uses `DB_URL` or a development/production database name.

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

- Production media live in `~/tepinta-data/storage` (since 2026-10-24), outside every checkout: pass `APP_STORAGE_ROOT=$HOME/tepinta-data/storage` to `deploy/release.sh`. The development checkout's `storage/` is development-only; never point production at it.
- Mutations refuse to run without the exact confirmation variable; the Makefile never supplies it. Rollback only re-routes Serve; it does not revert migrations or restore data.
- Tailscale Serve stays tailnet-only. The only public exception is the Funnel handler on HTTPS `:8443` for `/media/instagram` (`make media-route-status`, `TAILSCALE_FUNNEL_CONFIRM=yes make media-route-cutover`). Never publish `/`, `/admin` or `/api`.
- The release image loads `deploy/tailscale/php-security.ini` after the Sail defaults: session upload progress and fatal error backtraces off (CVE-2026-109619 mitigation until the Sail base ships PHP 8.5.12), `upload_max_filesize=10M`, `post_max_size=12M`. Verify inside a container with `php -r 'echo ini_get("session.upload_progress.enabled");'`. A body over `post_max_size` gets `413` with "El archivo es demasiado grande." (JSON for API and Inertia requests, shown as a toast); a file over `upload_max_filesize` fails validation with the translated `uploaded` message.
- `TRUSTED_PROXIES=REMOTE_ADDR` trusts only the direct loopback proxy so Laravel honors the forwarded HTTPS scheme.
- `APP_URL` is the WebAuthn/passkey origin: passkeys registered on `localhost` do not work on the `*.ts.net` origin; register a new one there.
- Mobile: open the HTTPS tailnet URL in Chrome, log in, **Add to Home screen**. The manifest starts `/admin` standalone; there is no service worker or offline cache.

### Print station

Kitchen tickets and bills are stored server-side in `print_jobs` (module `Printing`) and printed by the shop PC. Every route needs `orders.manage`.

| Route | Effect |
| --- | --- |
| `GET /admin/print-station` | The station page (Vender → Impresión) |
| `GET /admin/print-jobs/status` | `pending`, `oldest_pending_seconds`, `unconfirmed` (claimed, never acknowledged), `oldest_unconfirmed_seconds` |
| `GET /admin/print-jobs` | Last 30 jobs, for reprints |
| `POST /admin/print-jobs/claim` | Up to 10 pending jobs, oldest first, each with a claim token (`FOR UPDATE SKIP LOCKED`: two stations never get the same job) |
| `POST /admin/print-jobs/{id}/printed` | Acknowledge with the claim token (repeatable); without a token a person confirms an unconfirmed job |
| `POST /admin/print-jobs/{id}/release` | A person decides an unconfirmed job did not print; it returns to the queue |
| `POST /admin/print-jobs/{id}/reprint` | New pending job with the same frozen payload, marked as a reprint |

- One job per business event: the dedupe key is unique per restaurant and the listener inserts with `ON CONFLICT DO NOTHING`, inside the business transaction (a rolled-back round stores no job).
- A claimed job is never claimed again automatically, so a print whose acknowledgement was lost is not repeated; it appears as unconfirmed until someone confirms or releases it.
- PostgreSQL rejects changing a job's content, deleting it, or moving a printed job back to the queue.
- Every station read and write starts from `PrintJob::queue()`, scoped to the current restaurant.

#### Station page

- Only the PC whose "Activar esta PC como estación" is on prints (remembered in that browser's `localStorage`); any other device that opens the page only watches the queue. It polls every 3 seconds, prints each claimed job through a hidden iframe and acknowledges it; an acknowledgement that cannot reach the server is kept in `localStorage` and retried before the next claim.
- On the active PC every admin page prints, not only the station page: the admin layout runs the same loop (`features/printing/use-background-printing.ts`) for users with `orders.manage`, so the cashier can work on Mesas or Mostrador in the same window. Only a tab on screen claims jobs (Chrome holds `print()` in a background tab until it is shown); a hidden tab leaves them to a visible one, and the server hands each job to one claim. Outside the station page a failed print shows a toast; connection and session alerts stay on the station page.
- Red alerts, always on screen, with the pending counter (also in the window title as `(N) Estación de impresión`): the session closed (401/419, with a link to log in again), no connection to the server, the station is off while jobs wait, pending jobs older than 30 seconds, jobs claimed more than 30 seconds ago and never confirmed, and a job that failed to print (it goes back to the queue). Unconfirmed jobs are listed with "Salió" (confirm) and "No salió" (back to the queue); the last 30 jobs can be reprinted.
- Kitchen tickets carry table or order type, order number, round, time, waiter, "agregado por", customer and schedule for orders, notes and big quantities, never prices. Bills carry units per product (with the pack price, for example "1 docena × $ 18.000"), subtotal, discount, rounding, total and "Documento no válido como factura". Both are 80 mm HTML (`@page` 80 mm wide, zero margins).

#### Shop PC setup (Windows)

1. Install the Waggs 80 mm USB driver. In *Printing preferences* choose the 80 mm roll paper and turn on the automatic cut after each document. In Windows *Printers & scanners*, turn off "Let Windows manage my default printer" and set the Waggs as the default printer.
2. Install Tailscale on the PC and sign in to the shop tailnet (see "Device access" below).
3. Create a desktop shortcut with this target (Chrome; for Edge use `C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe`):

   ```text
   "C:\Program Files\Google\Chrome\Application\chrome.exe" --user-data-dir="%LOCALAPPDATA%\TePintaStation" --kiosk-printing --app=<private app URL>/admin/print-station
   ```

   `--kiosk-printing` sends `print()` straight to the default printer without a dialog. Chrome ignores flags when a window with the same profile is already open, so the shortcut uses its own profile (`--user-data-dir`). `--app` opens the station in its own window. Copy the shortcut to `shell:startup` so it opens when Windows starts.
4. Open the shortcut, log in with "Recordarme", press "Activar esta PC como estación" and then "Imprimir prueba": the ticket must come out complete and cut. If a URL or date is printed at the top or bottom, open the page once without `--kiosk-printing`, print the test with "Headers and footers" unchecked, close every window of that profile and reopen the shortcut.
5. Do all the cashier work in this same window (any admin page prints) and keep it on screen, never minimized: a hidden tab does not print, and with no admin page on screen the tickets wait (the station page warns when it was hidden). A normal Chrome window without `--kiosk-printing` must not be activated as station: it would open the print dialog. Turn off sleep in Windows power settings. Polling keeps the session alive; after a restart "Recordarme" logs it in again.

#### Before opening (owner checklist)

- **Inventory consumption for every product.** Charging a table marks its order delivered, and delivery is refused for a product without consumption configuration ("Missing inventory configuration for …"). Configure each product (recipe or "no inventory") in Inventario before the first service; the table page lists the table's products that still lack it.
- **Default wallet for order payments**, so a charge records its payment.
- **Floor.** Tables come from the floor editor; only active tables of active areas can be opened. The layout can change during service: deactivating a table with an open session keeps it on the board until it is charged or cancelled.
- **Members.** Each waiter needs an active `waiter` membership; owner, manager and cashier can take any table and are the only ones who print the bill, charge and cancel.
- **Devices.** Each phone and the shop PC on the tailnet (see "Device access"), the station shortcut open and active, test print done.

#### Real printer acceptance test

Run on the shop PC with the Waggs printer, the station active, one waiter phone and the cashier on the PC. Every check passes only if each document prints once, complete, 80 mm wide, and cut.

| # | Action | Expected |
| --- | --- | --- |
| P1 | "Imprimir prueba" | Test ticket, no URL or date header/footer, cut |
| P2 | Counter order ("Mostrador") with 2 products and a note | One kitchen ticket within 5 s: "Mostrador", order number, time, big quantities, note, no prices |
| P3 | Phone: open M1, 6 salteñas + 1 bebida, confirm | Ticket "Mesa M1 · Ronda 1", waiter's name, only those lines |
| P4 | Phone: add 6 salteñas more, confirm | Ticket "Ronda 2" with only the 6 new salteñas; phone total = dozen price + drink |
| P5 | Cashier (PC) adds 1 bebida to M1 | Ticket "Ronda 3" with "agregado por Cajero <name>" |
| P6 | Phone: void 1 bebida | Void ticket for that line; total drops by one drink |
| P7 | Phone: "Pedir la cuenta"; PC: "Imprimir cuenta" | Board shows "Pide la cuenta"; bill with "12 × Salteña", "1 docena × $ …", drinks, total and "Documento no válido como factura" |
| P8 | PC: "Cobrar" → Efectivo | Table free; nothing printed |
| P9 | Public web order pending → confirm in Pedidos | One kitchen ticket for it |
| P10 | Station list: "Reimprimir" the bill | Same bill marked "REIMPRESIÓN" |
| P11 | Reload the station window and close/reopen the shortcut during a queue of 3 rounds | Every ticket once; none twice |
| P12 | Printer off (or out of paper), send a round, turn it on | Ticket comes out when the printer returns (Windows spooler); if not, "Reimprimir" from the list and note it |
| P13 | Unplug the PC network for 30 s, send a round from the phone, plug back | Red "Sin conexión" alert while offline; the ticket prints after reconnecting |
| P14 | Deactivate the station, send a round, wait 35 s | Alert "pendientes hace más de 30 segundos" and the counter in the title |
| P15 | Log out on another tab | "Se cerró la sesión" alert with the login link |
| P16 | In the station window open Mesas, send a round from a phone; then minimize the window for 1 min while sending rounds | The ticket prints while on Mesas; while minimized nothing prints and the tickets come out when the window is restored |
| P17 | Two phones confirm rounds on two tables at the same time; then 10 quick rounds | Every ticket once, in confirmation order |
| P18 | Restart Windows | Station opens at startup, still logged in and active |
| P19 | Phone: turn on airplane mode right after "Confirmar y enviar a cocina", turn it off | "Sin conexión" toast; the round history shows whether it was saved: if it is there, do not confirm again (a retry after the table refreshed is a new round); if not, confirm again and one ticket prints |

Record the result of each row (pass/fail, delay, photo of P3–P7 tickets) in the release log before the opening.

#### Device access

Phones (the waiter screens) and the shop PC reach the operational app through Tailscale Serve on the notebook (`make route-status` shows the HTTPS URL; it proxies to `127.0.0.1:8082`). It is tailnet-only: every device must have Tailscale installed, signed in to the shop tailnet and connected, and the notebook must be on. A device without Tailscale gets no answer; the station then shows "Sin conexión con el servidor". Do not change Serve or Funnel to work around a missing device: add the device to the tailnet instead.

### Public storefront

`https://tepinta.com.ar` is served by a scoped Cloudflare named tunnel (user systemd unit `tepinta-public-cloudflared.service`, configuration outside the repo) → gateway `8083` → public app `8084`. The gateway allows only storefront pages, catalog, cart, checkout endpoints, product images and required assets; admin, auth, API, health, Instagram media, generic storage and the admin manifest are denied. It trusts `CF-Connecting-IP` only from its loopback peer.

- Run `tests/deploy/public-gateway/test.sh` before starting the gateway.
- The public app shares the production database. Releases are pinned, locally built images from a clean worktree at an approved commit (see `deploy/cloudflare/public-app/README.md` for the current tag and rollback tag); never `docker compose build` from a working checkout.
- Never point the public hostname or gateway at the private `8082` runtime. To withdraw: stop the connector and confirm it is inactive, then stop the gateway, then the public app.

### Legacy database cutover

Production (`tepinta`) was migrated from an uncommitted checkout with a different migration history. `deploy/cutover/` imports it into a freshly migrated database in one transaction (`import-legacy-database.sh <container> <source_db> <target_db>`): copies shared columns through `postgres_fdw`, assigns every row to restaurant 1, keeps production's `NOT VALID` checks, restores sequences, verifies row counts, content hashes, foreign keys, wallet balances, stock and order totals, and rolls back on any mismatch. `rehearse-import.sh` proves a clean import, refusal of a second import and rollback of a broken source on restored copies. Full checklist: `deploy/cutover/README.md`.

Before cutover: the deployed public image predates `restaurant_id` and must be redeployed from this line (its inserts would fail NOT NULL); trigger re-validation of rows written after 2026-10-16 is not automated; stop all writers including the public storefront.

## Backups

A nightly `pg_dump` of the production database and an encrypted off-site copy (rclone to Google Drive, with the operator's own OAuth client) run from operator scripts kept outside this repository; credentials and paths are not stored here. Each dump is restored into a scratch database (`<database>_restore_check`, dropped afterwards) before it is published, so a backup that cannot be restored fails the job. Restore a backup into a scratch database before every migration or cutover, and rehearse there. Files under `storage/app` (product images, Instagram assets, Management attachments) are not in the database; the same nightly job archives them as `tepinta_files_<stamp>.tar.gz` and copies them off-site with the dump. n8n data has its own backup procedure ([STOREFRONT_AND_MARKETING.md](STOREFRONT_AND_MARKETING.md#runbook)).
