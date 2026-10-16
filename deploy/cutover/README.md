# Cutover from the legacy production database

Production (`tepinta`) was migrated from an uncommitted checkout whose
migration history differs from this release line. Instead of migrating it in
place, the cutover imports its data into a freshly migrated database.

## What the import does

`import-legacy-database.sh <container> <source_db> <target_db>` runs
`import-legacy-database.sql` inside the target database, in one transaction:

1. Refuses a target that holds any data beyond this release's seed rows, legacy products
   without all three price tiers, or legacy inventory units that differ from
   the system units.
2. Copies every table through `postgres_fdw`, using the columns both schemas
   share; new columns take their defaults and every row belongs to
   restaurant 1. Framework tables (`migrations`, `sessions`, `cache`, jobs,
   password resets) are not copied.
3. Rebuilds packaging components onto `inventory_costing_policy_presentations`
   (one presentation per policy and commercial quantity).
4. Keeps check constraints that production has as `NOT VALID` (for example
   `orders_payment_matches_total`, which three August paid orders without a
   payment method do not satisfy) in the same state.
5. Restores sequences, including `order_public_id_seq`, and re-adds seed rows
   of this release that the legacy data lacks.
6. Verifies each copied table (row count and content hash on the target
   column types), that every single-column foreign key resolves, and
   reconciles wallet balances, stock per item and unit, and order totals by
   status. Any mismatch rolls everything back; the report prints all of them.
   The temporary `postgres_fdw` server and extension are removed.

Row triggers and FK checks are disabled only during the bulk copy
(`session_replication_role = replica`). The import does not re-run this
release's validation triggers; on 2026-10-16 a replay with triggers enabled
accepted every imported row (except append-only guards, which reject any
replay by design). The rehearsal uses the same replica-mode load, so it does
not re-validate production rows written after that date: before the cutover,
repeat the trigger replay on the rehearsal target, or accept that those rows
passed only the legacy triggers. The orphan check covers single-column
foreign keys; the import refuses to run if a composite one is ever added.

Sequences are not rolled back by PostgreSQL: after a failed import, use a
freshly migrated target again (the import sets every sequence explicitly).

## Rehearsal (copies only)

`rehearse-import.sh` restores nothing itself: give it a database restored
from the latest production backup and a command that migrates the database
named `$TARGET_DB` with this release. It asserts that a fresh import
reconciles, that a second import into the populated target is refused
without changes, and that a broken source rolls back completely. It drops
every database it creates.

```bash
MIGRATE_COMMAND='DB_DATABASE=$TARGET_DB php artisan migrate --force' \
    deploy/cutover/rehearse-import.sh <container> tepinta_dev
```

## Production cutover runbook

Run in order; every step names how to undo it. Expect 10–15 minutes without
service. Requires the owner's explicit go. `<sha>` is the release built in
step 1; `<checkout>` is the directory the current production runs from.

| # | Step | Undo |
|---|---|---|
| 1 | Build the release: `deploy/release.sh build <commit>`. | Delete the image. |
| 2 | Backup `tepinta` (`~/tepinta-ops/backup-db.sh`), confirm the off-site copy, restore it into a scratch database and run `rehearse-import.sh` against it. | — |
| 3 | Pause the n8n story workflow; stop writers: `docker compose -p tailscale stop app scheduler`, `docker compose -p public-app stop public-app`. | Start them again. |
| 4 | Stop and remove the Sail-owned database container (the volume stays): `docker stop tepinta30-pgsql-1 && docker rm tepinta30-pgsql-1`. Copy the stopped data into a volume no development compose file references: `docker volume create tepinta-production-pgsql` and `docker run --rm -v tepinta30_sail-pgsql:/from:ro -v tepinta-production-pgsql:/to alpine cp -a /from/. /to/`. `tepinta30_sail-pgsql` is now a frozen copy of production; start a server on it only for the undo, and never while `tepinta-database` is running. | `docker compose -p tepinta30 -f <checkout>/compose.yaml up -d pgsql` (old compose) brings the old server back on the untouched volume. |
| 5 | `docker network create tepinta-production`; start the deploy-owned database: `POSTGRES_USER=<existing superuser> docker compose -p tepinta-database -f deploy/database/compose.yaml up -d --wait`. | Stop it, then the step 4 undo. |
| 6 | `createdb tepinta_v2`; migrate it with the release image (`docker run --rm --env-file <env with DB_DATABASE=tepinta_v2, DB_HOST=tepinta-db> --network tepinta-production --entrypoint php tepinta3:<sha> /var/www/html/artisan migrate --force`); run `import-legacy-database.sh tepinta-database-postgres-1 tepinta tepinta_v2` and keep the report. | Drop `tepinta_v2`. |
| 7 | Production environment file outside the repo (`~/tepinta-ops/production.env`) with `DB_HOST=tepinta-db`, `DB_DATABASE=tepinta_v2`. Change the defaults in `~/tepinta-ops/backup-db.sh` to `CONTAINER=tepinta-database-postgres-1 DATABASE=tepinta_v2` (release.sh calls it without the systemd environment) and run it once by hand. | Keep the old file and script copy. |
| 8 | `RELEASE_CONFIRM=yes RELEASE_ENV_FILE=~/tepinta-ops/production.env APP_STORAGE_ROOT=<checkout>/storage DATABASE_DOCKER_NETWORK=tepinta-production deploy/release.sh deploy <sha>`. This also redeploys the public storefront from the same image. | No previous release exists for `release.sh rollback`. Recreate the old runtime from the old checkout: step 5 and 4 undo, then `docker compose -p tailscale -f <checkout>/deploy/tailscale/compose.yaml up -d --no-build app scheduler` (image `tepinta3-operational:local`) and `PUBLIC_APP_IMAGE=tepinta3-public-app:release-9a4dac2 docker compose -p public-app -f <checkout>/deploy/cloudflare/public-app/compose.yaml up -d --no-build public-app`. |
| 9 | Reconnect n8n to the app, which now lives on `tepinta-production`: `docker network connect tepinta-production n8n-n8n-1` (and set `POS_DOCKER_NETWORK=tepinta-production` in its compose environment so it survives recreation), then `docker network disconnect tepinta30_sail n8n-n8n-1` so it no longer sees any database. Check `docker exec n8n-n8n-1 wget -qO- http://tepinta-operational/up`. | Reverse the connect/disconnect. |
| 10 | Check admin login, a test order end to end, the Instagram media URL from outside, Tailscale Serve (127.0.0.1:8082) and the Cloudflare gateway (127.0.0.1:8083 → public app 8084). Resume the n8n workflow and watch one execution. | — |
| 11 | Keep database `tepinta`, volume `tepinta30_sail-pgsql` and the old images untouched until the release is accepted. | — |

After the cutover the development Sail stack uses its own `pgsql-dev`
service and volume. Older checkouts still define `pgsql` on
`tepinta30_sail-pgsql`; starting it only revives the frozen copy, never the
production volume.

Never rehearse on the `tepinta30_sail` network with the default
`OPERATIONAL_ALIAS`: n8n resolves `tepinta-operational` by DNS and would
round-robin between production and staging. Use a private network, or set
`OPERATIONAL_ALIAS` to another name.

Files under `storage/app` (Instagram assets, management attachments, product
images) are not in the database and stay where they are.
