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
replay by design). New production data written before cutover is covered
only by the rehearsal below, so run it right before the cutover.

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

## Production cutover checklist

1. Fresh backup of `tepinta` (`~/tepinta-ops/backup-db.sh`) and its off-site copy;
   restore it into a scratch database and run the rehearsal.
2. Stop writers: operational app, scheduler and the **public storefront**
   (the import reads `order_public_id_seq` outside its transaction).
   The deployed public image predates `restaurant_id`; it must be redeployed
   from this release before it writes to the new database.
3. Create and migrate `tepinta_v2`, run the import from `tepinta`.
4. Point the operational and public runtimes at `tepinta_v2`, deploy images
   built from this release, run smoke checks.
5. Keep `tepinta` untouched for rollback until the new release is accepted.

Files under `storage/app` (Instagram assets, management attachments, product
images) are not in the database and stay where they are.
