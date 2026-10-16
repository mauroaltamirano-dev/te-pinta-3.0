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
service. Requires the owner's explicit go.

| # | Step | Undo |
|---|---|---|
| 1 | Build the release: `deploy/release.sh build <commit>`. | Delete the image. |
| 2 | Backup `tepinta` (`~/tepinta-ops/backup-db.sh`), confirm the off-site copy, restore it into a scratch database and run `rehearse-import.sh` against it. | — |
| 3 | Pause n8n story publishing; stop writers: `docker compose -p tailscale stop app scheduler`, `docker compose -p public-app stop public-app`. | Start them again. |
| 4 | `docker network create tepinta-production`; stop the Sail-owned database container `tepinta30-pgsql-1` (do not `down -v`). | `docker start tepinta30-pgsql-1`. |
| 5 | Start the deploy-owned database on the same volume: `docker compose -p tepinta-database -f deploy/database/compose.yaml up -d --wait`. | Stop it, then step 4 undo. |
| 6 | `createdb tepinta_v2`; migrate it with the release image (`docker run --rm --env-file <env with DB_DATABASE=tepinta_v2, DB_HOST=tepinta-db> --network tepinta-production --entrypoint php tepinta3:<sha> /var/www/html/artisan migrate --force`); run `import-legacy-database.sh tepinta-database-postgres-1 tepinta tepinta_v2` and keep the report. | Drop `tepinta_v2`. |
| 7 | Production environment file outside the repo (`~/tepinta-ops/production.env`) with `DB_HOST=tepinta-db`, `DB_DATABASE=tepinta_v2`; point `backup-db.sh` at `CONTAINER=tepinta-database-postgres-1 DATABASE=tepinta_v2`, including the `Environment=` lines of the `tepinta-db-backup` systemd unit, and run it once by hand. | Keep the old file and unit. |
| 8 | `RELEASE_CONFIRM=yes RELEASE_ENV_FILE=~/tepinta-ops/production.env APP_STORAGE_ROOT=<absolute path of the current storage directory> DATABASE_DOCKER_NETWORK=tepinta-production deploy/release.sh deploy <sha>`. This also redeploys the public storefront from the same image. | `release.sh rollback` is not available for the first release: start the old containers (step 3 undo) against the old database (step 4/5 undo). |
| 9 | Check admin login, a test order end to end, Instagram media URL from outside, Tailscale Serve (8082) and the Cloudflare gateway (8084). Resume n8n. | — |
| 10 | Keep database `tepinta` and the old images untouched until the release is accepted. | — |

After the cutover the development Sail stack uses its own `pgsql-dev`
service and volume and can no longer reach or recreate production.

Files under `storage/app` (Instagram assets, management attachments, product
images) are not in the database and stay where they are.
