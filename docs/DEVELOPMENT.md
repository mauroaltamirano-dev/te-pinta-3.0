# Development

Laravel Sail is the supported local path for PHP, PostgreSQL, Node, and extensions.

## First setup

```bash
cp .env.example .env
# Set a local DB_PASSWORD before starting containers.
docker run --rm --user "$(id -u):$(id -g)" \
  -e HOME=/tmp -v "$PWD":/app -w /app composer:2 composer install
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
./vendor/bin/sail artisan admin:create
```

The Composer bootstrap container is only needed when `vendor/` is absent. Never commit `.env` or `APP_KEY`.

## Configure ordering

1. Sign in as an administrator.
2. Open `/admin/settings/general`.
3. Review combined-dozen prices and cooking surcharges.
4. Set the actual delivery fee. Its migration default is deliberately `0`, not an invented business price.
5. Set the WhatsApp destination. It is deliberately empty after migration; public checkout cannot produce a link until configured.
6. Review topping names, prices, availability, and order.

These settings are database records, not environment secrets. Never hardcode the business phone in React or PHP.

## Daily workflow

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm run dev
```

Useful commands:

```bash
./vendor/bin/sail artisan route:list
./vendor/bin/sail artisan test
./vendor/bin/sail pail
./vendor/bin/sail down
```

Run `./vendor/bin/sail artisan storage:link` once per environment for product images. Seed missing catalog records non-destructively with:

```bash
./vendor/bin/sail artisan db:seed --class=ProductCatalogSeeder
```

## Migration safety

Run normal versioned migrations against development. Do not run `migrate:fresh`: it would delete existing administrators, products, customers, and orders. Clean migration checks belong in the isolated test database.

Create the isolated database and local testing environment once:

```bash
cp .env.testing.example .env.testing
# Set DB_PASSWORD in .env.testing to the same local PostgreSQL password used by Sail.
./vendor/bin/sail exec -T pgsql sh -lc 'psql -U "$POSTGRES_USER" -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname = '\''testing'\''" | grep -qx 1 || createdb -U "$POSTGRES_USER" testing'
```

Then verify migrations from scratch only with the explicit testing environment:

```bash
./vendor/bin/sail artisan migrate:fresh --env=testing
```

`.env.testing` is local and ignored; `.env.testing.example` is the committed template. When `APP_ENV=testing`, application boot refuses any database name that does not contain a standalone `test` or `testing` segment. This guard also checks the database selected by `DB_URL`, when present. It exists because Laravel otherwise falls back to `.env` when `.env.testing` is absent.

PostgreSQL order sequences can contain gaps after rollbacks or sequence refresh. A gap is not corruption; never renumber orders or replace the sequence with `max + 1`.

## Quality gate

```bash
./vendor/bin/sail composer test
./vendor/bin/sail npm run lint:check
./vendor/bin/sail npm run format:check
./vendor/bin/sail npm run types:check
./vendor/bin/sail npm run build
./vendor/bin/sail npm run test:e2e
./vendor/bin/sail composer audit
./vendor/bin/sail npm audit
```

Install Chromium once with `./vendor/bin/sail npx playwright install chromium`. Browser tests use the configured test server/database; inspect `playwright.config.ts` before changing ports.

## Environment contract

Application/database/session/log values come from `.env`; `.env.example` is the non-secret reference. Ordering prices, toppings, delivery fee, and WhatsApp destination live in the typed database settings UI instead of environment variables.

## Operational runtime and mobile installation

The mutable Sail checkout on `:8000` is for development. Do not point Tailscale Serve at it: when `public/hot` exists, rendered pages reference the Vite server and a phone would resolve `localhost:5173` as the phone itself.

`deploy/tailscale/` prepares a separate production-like image on loopback `127.0.0.1:8082`. It uses compiled assets, keeps `APP_DEBUG=false`, contains no project `.env`, and does not copy local storage, logs, screenshots, tests, or development artifacts into the image.

Prepare the local runtime configuration without modifying the development `.env`:

```bash
cp deploy/tailscale/.env.example deploy/tailscale/.env
# Fill a new APP_KEY and the existing PostgreSQL credentials locally.
# Keep APP_URL=https://tuki-notebook.tail2b1906.ts.net.
deploy/tailscale/prepare-runtime.sh
OPERATIONAL_RUNTIME_CONFIRM=yes deploy/tailscale/runtime.sh start
OPERATIONAL_RUNTIME_CONFIRM=yes deploy/tailscale/runtime.sh migrate
deploy/tailscale/runtime.sh verify
```

`deploy/tailscale/.env` is ignored and must never be committed. The runtime joins the existing PostgreSQL Docker network, mounts only public product media, and creates `public/storage` inside the immutable image so those mounted images remain web-accessible. Runtime verification requests one real product asset when media exists. Create and validate a database backup before migrations or cutover.

The route change is intentionally separate and confirmation-gated:

```bash
deploy/tailscale/route.sh status
TAILSCALE_ROUTE_CONFIRM=yes deploy/tailscale/route.sh cutover

# Immediate rollback to Te Pinta 2.0 on 127.0.0.1:8080:
TAILSCALE_ROUTE_CONFIRM=yes deploy/tailscale/route.sh rollback
```

Tailscale Serve remains tailnet-only; do not enable Funnel. Verify `/up`, login, CSRF-protected writes, built assets, and `/manifest.webmanifest` from the phone before treating the cutover as complete. The notebook must remain awake with Docker and Tailscale running.

The operational environment sets `TRUSTED_PROXIES=REMOTE_ADDR` because Tailscale Serve terminates HTTPS and forwards the request to the loopback-only container over HTTP. This lets Laravel honor the direct proxy's `X-Forwarded-Proto: https` header and prevents mixed-content asset URLs without trusting arbitrary proxies in development.

The initial Te Pinta 3.0 cutover was completed and verified on 2026-08-14. `route.sh status` is still the authority because an administrator can deliberately roll back later.

For Android, connect the phone to the same tailnet, open the stable HTTPS URL in Chrome, sign in with **Remember me**, then use **Add to Home screen**. The manifest launches `/admin` as a standalone app and exposes a **Crear pedido** shortcut. No service worker is registered: authenticated pages and order writes are never cached for offline use.

`APP_URL` defines the Fortify/WebAuthn origin. A passkey registered on `localhost` does not belong to the `*.ts.net` origin; after the cutover, sign in with the password once and register a new passkey from the stable HTTPS URL.

## Dependency policy

Prefer Laravel, React, Tailwind, shadcn/ui, and platform-native capabilities. Add a production dependency only after documenting the concrete responsibility, maintenance/security cost, and why composition with existing tools is insufficient.
