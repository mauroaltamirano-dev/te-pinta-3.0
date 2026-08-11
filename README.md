# Te Pinta 3.0

Te Pinta 3.0 is a greenfield ordering and administration application for Te Pinta. This repository currently contains the secure, modular foundation: public navigation, an authenticated admin/POS shell, database foundations, and engineering documentation. Advanced ordering, promotions, analytics, and inventory behavior are intentionally out of scope for this stage.

## Stack

- PHP 8.3+ (Sail uses PHP 8.5) and Laravel 13
- PostgreSQL
- React 19, TypeScript, and Inertia 3
- Tailwind CSS 4 and shadcn/ui primitives
- PHPUnit through Laravel's standard test runner
- Playwright for functional and responsive browser validation
- Laravel Sail for the local runtime

## Start here

Prerequisites: Docker with Docker Compose. Host PHP, Composer, and Node are optional.

```bash
cp .env.example .env
# Set a local DB_PASSWORD in .env before starting the containers.
docker run --rm --user "$(id -u):$(id -g)" \
  -e HOME=/tmp -v "$PWD":/app -w /app composer:2 composer install
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
```

The bootstrap container is only needed when `vendor/` is absent. Never commit
the generated `.env` or reuse local credentials in another environment.

Open the URL configured by `APP_URL`. Create the first administrator interactively with `./vendor/bin/sail artisan admin:create`; public administrator registration is not supported.

See [Development](docs/DEVELOPMENT.md) for setup details and [Project map](docs/PROJECT_MAP.md) to find code quickly.

## Quality checks

```bash
./vendor/bin/sail composer test
./vendor/bin/sail npm run lint:check
./vendor/bin/sail npm run format:check
./vendor/bin/sail npm run types:check
./vendor/bin/sail npm run build
./vendor/bin/sail npm run test:e2e
```

Run only scripts that exist in `composer.json` or `package.json`; [Testing](docs/TESTING.md) is the source of truth for the current test surface. `npm run test:e2e:ui` opens Playwright's interactive runner for local diagnosis.

## Documentation

Read [docs/INDEX.md](docs/INDEX.md) first. Architecture, security, database, design, logging, testing, and module ownership are documented separately so they remain practical to update.

## Scope boundaries

The initial foundation does **not** implement complete promotions, analytics, inventory, stock, combo, discount, checkout, or payment behavior. There is no online payment flow. Future public checkout confirmation will produce a customer-reviewed WhatsApp message; the server remains authoritative for prices, totals, identifiers, and state transitions.
