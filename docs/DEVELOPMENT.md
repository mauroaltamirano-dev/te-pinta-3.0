# Development

The supported local path uses Laravel Sail so PHP, PostgreSQL, Node, and extensions are reproducible.

## Prerequisites

- Docker with Docker Compose
- Git

Host PHP, Composer, and Node are optional when commands run through Sail.

## First setup

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
./vendor/bin/sail artisan admin:create
```

The Composer bootstrap container is only needed when `vendor/` is not present.
Do not commit the generated `.env` or any key.

## Daily workflow

```bash
./vendor/bin/sail up -d
./vendor/bin/sail npm run dev
```

Useful commands:

```bash
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan route:list
./vendor/bin/sail artisan test
./vendor/bin/sail pail
./vendor/bin/sail down
```

## Environment variables

Required application/database keys (values are environment-specific and must not be documented as secrets):

```dotenv
APP_NAME=
APP_ENV=
APP_KEY=
APP_DEBUG=
APP_URL=
APP_PORT=

DB_CONNECTION=pgsql
DB_HOST=
DB_PORT=
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

SESSION_DRIVER=
SESSION_ENCRYPT=
SESSION_DOMAIN=

LOG_CHANNEL=
LOG_LEVEL=

VITE_APP_NAME=
```

Use `.env.example` for the complete non-secret contract. A local `.env` must remain ignored. Never use production credentials locally.

## Quality gate

```bash
./vendor/bin/sail composer test
./vendor/bin/sail npm run lint:check
./vendor/bin/sail npm run format:check
./vendor/bin/sail npm run types:check
./vendor/bin/sail npm run build
./vendor/bin/sail npm run test:e2e
```

`composer test` covers PHP formatting, static analysis, and Laravel tests in the current scripts. Use `composer ci:check` inside Sail for the combined repository gate when appropriate.

Install the Chromium binary once with `./vendor/bin/sail npx playwright install chromium`. Use `./vendor/bin/sail npm run test:e2e:ui` for interactive Playwright debugging. Browser tests expect the configured test server and database environment; inspect `playwright.config.ts` before changing ports or base URLs.

## Dependency policy

Before adding a production dependency:

1. Verify Laravel, React, Tailwind, shadcn/ui, or the platform cannot solve the problem.
2. State the concrete responsibility the dependency owns.
3. Evaluate maintenance, security, bundle/runtime cost, and removal cost.
4. Record a durable decision when the tradeoff affects architecture.

Never add a dependency merely to avoid understanding a small native capability.
