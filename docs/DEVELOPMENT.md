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

## Dependency policy

Prefer Laravel, React, Tailwind, shadcn/ui, and platform-native capabilities. Add a production dependency only after documenting the concrete responsibility, maintenance/security cost, and why composition with existing tools is insufficient.
