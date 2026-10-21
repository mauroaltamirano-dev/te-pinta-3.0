# Combined dozen by percentage

Objective: price mixed-flavor dozens as the sum of their unit prices minus a configurable percentage, replacing fixed-dozen-pricing S3. Branch `feat/combined-dozen-percentage` from `feat/inauguration-pos` at `564a92d`. Delivery: ask-on-risk → `feature-branch-chain` (user, L7). Test runner: PHPUnit/Pest through Sail (`vendor/bin/sail artisan test`), Playwright for e2e.

## Specs

- S1 — Scope: "Reemplaza fixed-dozen-pricing S3 ("NO hay docena combinada ni tope"). No revivir los precios fijos 17.500/16.000." Valid doc: `docs/ORDERS_AND_PRICING.md`.
- S2 — "Primero, cada 12 unidades del mismo gusto = D (sin cambios)."
- S3 — "Con los remanentes, mientras haya ≥ 12 unidades de más de un gusto: docena combinada."
- S4 — "Precio combinada = Σ(cantidad × U) de sus 12 unidades × (1 − X%). X = 20% "de momento" → configurable en Configuración."
- S5 — "se mete primero la mas cara dentro de la combinada". "Lo que sobra va a U."
- S6 — "Cruda: misma regla con U cruda (D − descuento de docena, misma fórmula)."
- S7 — "Snapshots históricos intactos; el total se sigue redondeando una sola vez a $100."
- S8 — Required examples as tests ("recargo 25%, D dulce 20.000 / JyQ 22.000 / salteña 18.000 → U 2.100 / 2.300 / 1.900"); the 25% surcharge is fixed in the test fixture:
  - "12 dulce = 20.000"
  - "11 dulce + 1 JyQ = 20.300 (combinada 20.320)"
  - "6 dulce + 6 JyQ = 21.100"
  - "6 dulce + 3 JyQ + 3 salteña = 20.200"
  - "5 dulce + 5 JyQ = 22.000 (no llega a docena)"
  - "13 dulce + 11 JyQ = 41.900"
  - "8 dulce + 8 JyQ = 29.800 (combinada 8 JyQ + 4 dulce = 21.440; quedan 4 dulce a U)"
  - "8 dulce + 8 JyQ + 8 salteña = 40.300"
  - "11 salteña + 1 dulce = 18.400"
  - "Cruda 6 dulce + 6 JyQ = 19.200 · Cruda 6 + 3 + 3 = 18.200"
- S9 — Environment: worktree `~/workspaces/tepinta3.0-worktrees/combined-dozen-percentage`, "Base de dev y COMPOSE_PROJECT_NAME propios en un .env ignorado (como fixed-dozen-pricing S12); no toques tepinta_dev." "Índice .codegraph propio (no copiar el de otro worktree)." Catalog copy: "solo lectura sobre tepinta_dev, intersección de columnas contra el esquema migrado, y copiá users (con membresía owner), categories, tags, products, product_tag, customers, order_addons, order_settings y raw_price_discounts. NO pedidos ni inventario. No modifiques tepinta_dev. Registrá en el Log qué copiaste y cuántas filas."
- S10 — "La migración sobre los checks v2 de order_items es de alto riesgo (datos persistidos): commit propio y verificador independiente."

Assumptions:
- A1 — Tie-break: when two flavors have the same U, the lower product ID enters the combined dozen first, so snapshots are deterministic. Not requested by the user; to confirm.
- A2 — The percentage is stored like the existing settings: `order_settings.combined_dozen_discount_percent`, integer 0–100, seed 20.
- A3 — Shape: each mixed dozen is one `combined_dozen` order item (`product_id` NULL, quantity 1, 12 units) with its flavors in `order_item_compositions`, reusing the version-1 display paths; `regular_price_total` = Σ(q × U), `savings` = the X% discount, `line_total` = Σ(q × U) × (1 − X%). Raw: `cooked_line_total` uses cooked U, `raw_discount` = cooked − applied. U is a multiple of $100, so the line stays in whole pesos and the single $100 rounding in `ExactOrderTotals` is unchanged.

## Tasks

Forecast ≈ 900 authored changed lines.

- T1 — S4 (setting): `combined_dozen_discount_percent` seeded 20, shown and validated in Configuración. Route: inline. Status: done, `8c689c9` (L6).
- T2 — S10: migration widening `order_items_fixed_dozen_snapshot_valid` to allow version-2 `combined_dozen` lines (no `product_id`), `down` refuses with version-2 combined history. High risk (item 1); own commit; independent verifier. Route: inline. Status: pending.
- T3 — S2–S8: `OrderPricingEngine` combined step (cooked and raw), persistence with compositions, S8 examples as tests with a 25% fixture; tests that pinned S3 of fixed-dozen updated. Route: inline. Status: pending.
- T4 — S3/S5 display: kitchen ticket lists the flavors of a mixed dozen, bill labels it, admin/public/WhatsApp views checked; e2e updated. Route: inline. Status: pending.
- T5 — S1: `docs/ORDERS_AND_PRICING.md` and the fixed-dozen S3 note. Route: inline. Status: pending.

## Log

- L1 (2026-10-21) — User request: "Nueva feature: docena combinada por porcentaje. Rama nueva desde feat/inauguration-pos (no desde main). Reemplaza fixed-dozen-pricing S3 ("NO hay docena combinada ni tope"). No revivir los precios fijos 17.500/16.000. Doc válido: docs/ORDERS_AND_PRICING.md. Reglas: - Primero, cada 12 unidades del mismo gusto = D (sin cambios). - Con los remanentes, mientras haya ≥ 12 unidades de más de un gusto: docena combinada. - Precio combinada = Σ(cantidad × U) de sus 12 unidades × (1 − X%). X = 20% "de momento" → configurable en Configuración. - "se mete primero la mas cara dentro de la combinada". Lo que sobra va a U. - Cruda: misma regla con U cruda (D − descuento de docena, misma fórmula). - Snapshots históricos intactos; el total se sigue redondeando una sola vez a $100. Ejemplos obligatorios como tests (recargo 25%, D dulce 20.000 / JyQ 22.000 / salteña 18.000 → U 2.100 / 2.300 / 1.900): [S8]. Dos cosas a tener en cuenta: Agregué una regla para el empate. Si dos gustos tienen la misma U, desempato por ID para que los snapshots salgan siempre iguales. Vos no lo pediste, así que no lo metí como regla en el bloque. Si el agente necesita un desempate, conviene que lo deje anotado como supuesto. Los ejemplos dependen del recargo. Están calculados con 25%. Si en Configuración queda 10%, cambian todas las U y la tabla deja de valer. Hay que fijar el 25% en el fixture del test." Plus: "Creá un worktree propio en ~/workspaces/tepinta3.0-worktrees/combined-dozen-percentage con la rama feat/combined-dozen-percentage desde la punta final de feat/inauguration-pos. Base de dev y COMPOSE_PROJECT_NAME propios en un .env ignorado (como fixed-dozen-pricing S12); no toques tepinta_dev. Índice .codegraph propio (no copiar el de otro worktree). La migración sobre los checks v2 de order_items es de alto riesgo (datos persistidos): commit propio y verificador independiente."
- L2 — Waited for the inauguration-pos agent (full Playwright suite, then its follow-up commit) until its session was idle and its worktree clean; final tip `564a92d`. Worktree created from it. `codegraph` is not installed on this machine (`gentle-ai codegraph init`: executable not found), so no index yet; filesystem tools are the fallback.
- L3 (2026-10-21) — User: "El .env ya está creado (COMPOSE_PROJECT_NAME=tepinta-combined-dozen, APP_PORT=8087, VITE_PORT=5187, DB tepinta_combined_dozen). Levantá los contenedores de este worktree y migrá tepinta_combined_dozen. Avisame cuando esté arriba para que yo corra key:generate. Después copiá mi catálogo real desde tepinta_dev igual que fixed-dozen-pricing L4 [...]". The user also set `DB_HOST=pgsql-dev`.
- L4 — Dev env: Composer installed with the `sail-8.5/app` image (`composer:2` lacks `ext-gd`); `sail up -d` (`tepinta-combined-dozen-laravel.test-1` on 8087/5187, own `pgsql-dev`); 53 migrations ran. Catalog copied from `tepinta_dev` in a read-only transaction, column intersection over the migrated schema, one destination transaction: users 1, categories 2, tags 1, products 10, product_tag 3, customers 112, order_addons 2 (the two seeded addons replaced), raw_price_discounts 3, order_settings 4 source keys updated by key (`delivery_fee`, `whatsapp_order_phone`, `combined_dozen_raw_price`, `combined_dozen_cooked_price`; `empanada_unit_surcharge_percent` 10 and `combined_half_dozen_surcharge` kept from the seed); owner membership created in `restaurant_user`; sequences reset. Because the migrations ran before the copy, the same derivations they apply to existing rows were applied to the copied products: U from D at 10% rounded to $100, half dozen = 6 × U. No orders or inventory copied; product images not copied. CSV scratch removed.
- L5 — Session restarted (machine reboot). `node` is not on the shell PATH (nvm v24.21.0 used explicitly); the Vite build runs inside the app container because Wayfinder calls `php artisan`. Tests run inside the container with a throwaway `APP_KEY` env (the user runs `key:generate` on `.env`).
- L6 — T1 RED: props lacked `combined_dozen_discount_percent`, `OrderSettingKey::CombinedDozenDiscountPercent` undefined (3 failed). GREEN: enum case; migration `2026_10_22_000000` seeds 20 for every restaurant (`on conflict do nothing`); request `required|integer|0-100`; data, action, controller prop; "Docena combinada" field in `general.tsx`; e2e asserts 20. The new required field was added to the settings payloads in `FixedDozenRegressionTest` and `DerivedPricingConfigurationTest`. Settings, fixed-dozen regression and derived configuration tests 51 OK; pint, phpstan, eslint, prettier, tsc clean. Dev database migrated.
- L7 (2026-10-22) — User chose `feature-branch-chain` for the ~900-line forecast: slices are PRs along `feat/combined-dozen-percentage`, integrated into `feat/inauguration-pos` at the end.
- L8 — T1 committed `8c689c9`; native assess: medium, `under_budget` (pending in the slice).
- L9 — T2 RED: a version-2 `combined_dozen` line (6 dulce + 6 JyQ at 20%: regular 26400, line 21120, savings 5280, no product) was rejected by `order_items_fixed_dozen_snapshot_valid`, and the rollback test could not load the migration. GREEN: `2026_10_22_000100_allow_combined_dozen_snapshots` recreates the check under the same name as a superset: unit/dozen lines with a product as before, or `combined_dozen` without product, quantity 1, 12 units, with the same numerator and non-null cooked/raw rules. `down` restores the narrow check and refuses while version-2 combined history exists. Schema tests 9 OK (rejections: 11 units, quantity 2, numerator off by one, surcharge numerator, null cooked breakdown, product set; rollback up/down/up with v2 unit history). Dev database migrated.
- L10 — Full PHPUnit before committing T2: 960 passed (7802 assertions); pint, phpstan clean.
