# Project map

This is the practical navigation guide. Update it whenever an important path moves.

## Backend

| Looking for                                       | Location                                                                      |
| ------------------------------------------------- | ----------------------------------------------------------------------------- |
| Application bootstrap and middleware registration | `bootstrap/app.php`                                                           |
| Global public route entry                         | `routes/web.php`                                                              |
| Module routes                                     | `app/Modules/<Module>/routes.php`, loaded by `routes/web.php`                 |
| Authentication configuration                      | `config/auth.php`, `config/fortify.php`                                       |
| Identity/user model                               | `app/Modules/Identity/`                                                       |
| Authentication actions and provider wiring        | `app/Modules/Identity/`, `app/Providers/FortifyServiceProvider.php`           |
| Administrative access enforcement                 | `/admin` route groups protected by `auth`, `verified`, and `admin` middleware |
| Core contracts and application-wide support       | `app/Core/`                                                                   |
| Narrow shared infrastructure                      | `app/Shared/`                                                                 |
| Business modules                                  | `app/Modules/`                                                                |
| Database migrations                               | `database/migrations/`                                                        |
| Factories and seeders                             | `database/factories/`, `database/seeders/`                                    |
| Database connection                               | `config/database.php`, `.env`                                                 |
| Logging configuration                             | `config/logging.php`                                                          |
| Runtime logs                                      | `storage/logs/` (never commit)                                                |
| Other framework configuration                     | `config/`                                                                     |

## Business areas

| Change                   | Backend                                                                            | Frontend                                                                            |
| ------------------------ | ---------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------- |
| Public home/navigation   | `app/Modules/PublicSite/`                                                          | public pages and public layout                                                      |
| Public menu and products | `app/Modules/PublicSite/routes.php` exposes the page; `Products` owns catalog data | menu page and `resources/js/features/products/`                                     |
| Cart                     | public ordering boundary as it is introduced                                       | cart page and owning feature                                                        |
| Orders / POS             | `app/Modules/Orders/`                                                              | orders pages and `resources/js/features/orders/`                                    |
| Customers                | `app/Modules/Customers/`                                                           | customer pages and `resources/js/features/customers/`                               |
| Product management       | `app/Modules/Products/`                                                            | `resources/js/pages/admin/products/index.tsx` and `resources/js/features/products/` |
| Dashboard                | `app/Modules/Dashboard/`                                                           | admin dashboard page/feature                                                        |
| Analytics                | `app/Modules/Analytics/`                                                           | analytics placeholder/feature                                                       |
| Inventory                | `app/Modules/Inventory/`                                                           | inventory placeholder/feature                                                       |
| Configuration            | `app/Modules/Settings/`                                                            | settings pages/feature                                                              |

Exact page filenames should follow route names and remain thin composition points.

## Frontend

| Looking for                      | Location                                                                     |
| -------------------------------- | ---------------------------------------------------------------------------- |
| Inertia application entry        | `resources/js/app.tsx`                                                       |
| Application composition          | `resources/js/app.tsx`; add `app/` only when composition code needs a folder |
| Public/admin/auth shells         | `resources/js/layouts/`                                                      |
| Route-level pages                | `resources/js/pages/`                                                        |
| Feature-owned UI and hooks       | `resources/js/features/` as non-placeholder features are introduced          |
| shadcn primitives                | `resources/js/components/ui/`                                                |
| Te Pinta shared components       | `resources/js/components/shared/`                                            |
| Shared hooks                     | `resources/js/hooks/`                                                        |
| Focused utilities                | `resources/js/lib/`                                                          |
| Shared TypeScript types          | `resources/js/types/`                                                        |
| Design tokens and Tailwind theme | `resources/css/app.css`                                                      |
| shadcn generator configuration   | `components.json`                                                            |

Generated Wayfinder route/action files live under `resources/js/routes/`, `resources/js/actions/`, and `resources/js/wayfinder/`; change the PHP route or controller source rather than manually editing generated output.

## Products quick map

| Change                                           | Location                                                                                                                                                                                                      |
| ------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Admin product/category routes                    | `app/Modules/Products/routes.php` (`/admin/settings/products`, `/admin/settings/categories`)                                                                                                                  |
| Public catalog route                             | `app/Modules/PublicSite/routes.php` (`GET /menu/catalog`)                                                                                                                                                     |
| HTTP coordination and safe response shapes       | `app/Modules/Products/Http/Controllers/`, `app/Modules/Products/Http/Resources/`                                                                                                                              |
| Input validation and authorization               | `app/Modules/Products/Http/Requests/`, `app/Modules/Products/Policies/`                                                                                                                                       |
| Product/category mutations and price calculation | `app/Modules/Products/Actions/`                                                                                                                                                                               |
| Validated command data passed to actions         | `app/Modules/Products/Data/`                                                                                                                                                                                  |
| Models and relationships                         | `app/Modules/Products/Models/`                                                                                                                                                                                |
| Catalog schema                                   | `database/migrations/2026_08_11_000200_create_catalog_tables.php`, `database/migrations/2026_08_11_000500_expand_product_catalog.php`, `database/migrations/2026_08_11_000600_add_product_catalog_checks.php` |
| Initial catalog and cooking surcharges           | `database/seeders/ProductCatalogSeeder.php`                                                                                                                                                                   |
| Admin page and feature UI                        | `resources/js/pages/admin/products/index.tsx`, `resources/js/features/products/`                                                                                                                              |
| Canonical visual reference                       | `docs/design/references/admin-products.png`                                                                                                                                                                   |
| Product tests                                    | `tests/Feature/Products/`, `tests/e2e/products.spec.ts`                                                                                                                                                       |

### Add a product manually

1. Open `/admin/settings/products` as an administrator.
2. Select its category tab and choose **Add product**.
3. Enter base prices in whole pesos, select any tags, set availability/order, and save the modal.

For uploaded images, initialize the public link once per environment:

```bash
./vendor/bin/sail artisan storage:link
```

Initialize any missing records from the defined catalog with:

```bash
./vendor/bin/sail artisan db:seed --class=ProductCatalogSeeder
```

Repeated runs preserve administrative edits and do not restore soft-deleted records.

## Tests and tooling

| Looking for                        | Location                                                           |
| ---------------------------------- | ------------------------------------------------------------------ |
| Laravel feature tests              | `tests/Feature/`                                                   |
| Laravel unit tests                 | `tests/Unit/`                                                      |
| Browser tests                      | `playwright.config.ts` and `tests/e2e/`                            |
| PHP formatter/static analysis      | `pint.json`, `phpstan.neon`, `composer.json`                       |
| TypeScript lint/format/type checks | `eslint.config.js`, `.prettierrc`, `tsconfig.json`, `package.json` |
| Vite build                         | `vite.config.ts`                                                   |

## Operations

- Environment template: `.env.example`
- Local secrets: `.env` (ignored; never commit)
- Public assets: `public/`
- Compiled assets: generated by Vite; never edit them directly
- Uploaded files: Laravel filesystem disks configured in `config/filesystems.php`
