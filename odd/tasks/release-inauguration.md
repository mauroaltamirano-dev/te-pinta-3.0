# Inauguration release

Objective: ship `main` (light theme + fixed dozen pricing + A-03 admin navigation) to production before the opening (~2026-10-30), rehearsed on a production copy first.
Branch: `release/inauguration` (from `main` `58deae2`). Delivery: docs-only commits on this branch; the release itself is image `tepinta3:58deae287bfe` built from `main`. Test runner: rehearsal on an isolated PostgreSQL 18 copy + `deploy/release.sh` smoke checks.

## Specs

- S1 — "Verificá que main incluya feat/admin-navigation. Si no, pará y avisame."
- S2 — "Inventario: qué migraciones nuevas corren contra producción desde 365091a25b9c y qué datos tocan (en especial las que reescriben unit_price y half_dozen_price de empanadas y las de snapshots de pedidos)."
- S3 — "Respaldo validado de la base de producción, siguiendo el procedimiento documentado."
- S4 — "Ensayo: restaurá ese respaldo en una base aislada, corré ahí las migraciones nuevas y mostrame una tabla con cada empanada: dozen_price, unit_price y half_dozen_price antes → después. Verificá que los pedidos históricos queden idénticos (totales y snapshots)."
- S5 — "Prepará la imagen nueva (make ops-prepare) y verificala en loopback sin reemplazar el servicio actual."
- S6 — "PARÁ y pedime autorización antes de: correr migraciones en producción, reemplazar la imagen operativa, cambiar la ruta de Tailscale (route-cutover) o actualizar la app pública. Dame el plan de rollback a 365091a25b9c."
- S7 — No production mutation without explicit owner authorization; backups and rehearsals are allowed. Do not touch the main `tepinta3.0` checkout or other worktrees. No `Co-Authored-By` in commits.

## Tasks

| ID | Specs | Route | Status | Evidence |
|---|---|---|---|---|
| T1 | S1 | inline | done | `feat/admin-navigation`, `feat/light-theme`, `feat/light-theme-integration`, `feat/fixed-dozen-pricing` are ancestors of `main` `58deae2`; `365091a` is an ancestor too (51 commits ahead). |
| T2 | S2 | inline | done | Four pending migrations, see L3. |
| T3 | S3 | inline | done | `~/tepinta-ops/backup-db.sh` → `tepinta_2026-10-20_0405.dump` (616K), sha256 OK, off-site copy OK; restored copy has identical row counts in all 71 tables. Restore needs a workaround (L4). |
| T4 | S4 | inline | done | L5–L6. |
| T5 | S5 | inline | done | L7–L8. |
| T6 | S6 | inline | waiting for owner | Plan in L9–L10. |

## Log

- L1 (2026-10-20, owner): pasted request "preparar la release de producción con main (tema claro + precios por docena fija + navegación A-03) antes de inaugurar (~2026-10-30)" with the five ordered steps quoted in S1–S6.
- L2: The request names the pre-reset procedure (`make ops-prepare`, `docs/DEVELOPMENT.md`, `docs/COMMANDS.md`, `deploy/cloudflare/public-app/README.md` pins, `odd/tasks/cloudflare-public-storefront.md`). On `main` those docs do not exist; production has been managed by `deploy/release.sh` since the 2026-10-16 cutover (`odd/tasks/release-foundation.md` L12). The equivalent of `ops-prepare` is `release.sh build`, and one `release.sh deploy` does backup → migrate → switch operational app, scheduler and public app → smoke. Tailscale Serve already proxies `/` to `127.0.0.1:8082`, so no `route-cutover` is needed.
- L3: Pending migrations against production (`tepinta_v2`, last ran `2026_10_16_000000_prepare_restaurant_tenancy`):
  1. `2026_10_16_020000_add_restaurant_accent_color` — adds `restaurants.accent_color` (default `#b84f3f`) + hex check. No existing value changes.
  2. `2026_10_17_000000_create_restaurant_memberships` — creates `restaurant_user`, inserts one row per admin/staff user (production: 1 owner). `users.is_admin`/`staff_profile` kept.
  3. `2026_10_19_000000_activate_fixed_dozen_pricing` — refuses if any version-2 snapshot exists (none: 143 orders, all v1); inserts `empanada_unit_surcharge_percent = 10`; **rewrites `unit_price` of every empanada** as `round_half_up_100(dozen / 12 × 1.10)`; adds v2 snapshot checks and replaces `orders_total_matches_breakdown` / `orders_discount_valid` keeping the v1 branch verbatim. Order rows are not updated.
  4. `2026_10_19_000001_derive_empanada_half_dozen_prices` — **rewrites `half_dozen_price = 6 × unit_price`** for every empanada. `down()` cannot recover the entered values; only a backup can.
- L4: Finding: a plain `pg_restore --exit-on-error` of the nightly dump fails on `COPY dining_areas`: the check calls `public.restaurant_obstacles_valid`, which calls `restaurant_geometry_valid` unqualified, and `pg_restore` runs with an empty `search_path`. `pg_restore --list` (the script's validation) does not catch it. Working restore: `--section=pre-data`; `ALTER FUNCTION public.restaurant_obstacles_valid(jsonb,integer,integer) SET search_path = public`; `--section=data`; `--section=post-data`; `ALTER FUNCTION … RESET search_path`. Any production restore must follow this until the function is fixed (follow-up: set `search_path` in its definition through a migration).
- L5: Rehearsal: container `tepinta-rehearsal-inauguration-pg` (postgres:18-alpine, private network `tepinta-rehearsal-inauguration`, database `tepinta_rehearsal`), migrations run with the release image exactly as `release.sh deploy` does (`artisan migrate --force`): all four DONE. Per-table content hashes before → after differ only in `migrations`, `order_settings` (new key), `products`, `restaurants` (identical without `accent_color`, also equal to production) and the new `restaurant_user`. `orders`, `order_items`, `order_toppings`, `order_revisions` and every other table are byte-identical; 143 orders, total ARS 3,226,600 unchanged; the new checks validated against all existing rows.
- L6: Empanada prices (ARS, before → after; all 10 active, none soft-deleted). Unit and half dozen go DOWN: the old prices carried ~20 % over dozen/12, the new rule 10 %.

  | ID | Flavor | dozen | unit | half dozen |
  |---|---|---|---|---|
  | 1 | Salteña | 20000 | 2000 → 1800 | 12000 → 10800 |
  | 2 | Humita Tucumana | 18000 | 1800 → 1700 | 10000 → 10200 |
  | 3 | Hongos al puerro | 20000 | 2000 → 1800 | 12000 → 10800 |
  | 4 | Pollo y portobellos | 21000 | 2100 → 1900 | 12500 → 11400 |
  | 5 | Matambre y verduras asadas | 22000 | 2200 → 2000 | 13000 → 12000 |
  | 6 | Criolla Salada | 20000 | 2000 → 1800 | 12000 → 10800 |
  | 7 | Criolla Dulce | 20000 | 2000 → 1800 | 12000 → 10800 |
  | 8 | Jamón y Queso | 18000 | 1800 → 1700 | 10000 → 10200 |
  | 9 | Caprese | 18000 | 1800 → 1700 | 10000 → 10200 |
  | 10 | Fatay | 20000 | 2000 → 1800 | 12000 → 10800 |

- L7: `deploy/release.sh build 58deae2` → `tepinta3:58deae287bfe` (release dir `~/tepinta-releases/58deae287bfe`, assets manifest present). `state` unchanged (`current=365091a25b9c`).
- L8: Loopback verification without touching production: release compose files with projects `tepinta-rehearsal-ops` / `tepinta-rehearsal-public`, ports 8092 / 8094, private network, alias `tepinta-rehearsal-operational`, storage copy in the session scratchpad, rehearsal env (new `APP_KEY`, Instagram automation off). App, scheduler and public app healthy; `/up`, `/login`, `/`, `/menu`, `/menu/catalog` 200; catalog serves Fatay `unit 1800 / half_dozen 10800 / dozen 20000`; rehearsal-only owner logs in and every static admin GET route answers 200 (including `/admin/orders/create`, the POS); no errors logged. Rollback rehearsal: image `365091a25b9c` on 8096 / 8098 against the migrated copy boots healthy, smoke and admin pages 200, and serves the new prices (data is not reverted). Production containers stayed on `365091a25b9c`, up 3 days.
- L9: Deploy plan (needs explicit owner authorization; one command, ~15 s of switch):
  `RELEASE_ENV_FILE=~/tepinta-ops/production.env APP_STORAGE_ROOT=/home/tuki/workspaces/tepinta3.0/storage RELEASE_CONFIRM=yes deploy/release.sh deploy 58deae287bfe` — runs a fresh backup, the four migrations, switches app + scheduler + public app, smoke-tests and switches the image back automatically if smoke fails. Then: re-check prices in `/menu/catalog`, admin login on the tailnet URL, n8n `/up`, the 20:15 story run.
- L10: Rollback to `365091a25b9c`:
  - A. Image only (seconds): `RELEASE_ENV_FILE=… APP_STORAGE_ROOT=… RELEASE_CONFIRM=yes deploy/release.sh rollback`. The schema stays migrated (rehearsed: works). Prices stay at the new values; restore the old ones with an `UPDATE products` from L6 if wanted. `365091a` does not know `pricing_snapshot_version`: orders taken on the new release are v2 and the old code would read and edit them as v1, so A is clean only before the first new order.
  - B. Data (minutes, loses everything written after the deploy): stop app, scheduler, public app and n8n polling; restore the backup that `release.sh deploy` takes first into `tepinta_v2` using the L4 procedure; then A. Needs its own authorization.
- L11: Open: (1) owner decision on the lower unit / half-dozen prices (surcharge 10 % is the migration default and can be changed in settings after deploy); (2) production media live in the main development checkout (`/home/tuki/workspaces/tepinta3.0/storage`); (3) restore workaround L4 to be fixed in code; (4) rclone shared client_id retirement warning still present; (5) rehearsal stack on 8092 / 8094 left running for a visual check.
