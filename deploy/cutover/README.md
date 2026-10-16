# Cutover from the legacy production database

Production (`tepinta`) was migrated from an uncommitted checkout whose
migration history differs from this release line. Instead of migrating it in
place, the cutover imports its data into a freshly migrated database.

## What the import does

`import-legacy-database.sh <container> <source_db> <target_db>` runs
`import-legacy-database.sql` inside the target database, in one transaction:

1. Refuses a target that already holds orders or customers, legacy products
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
   column types) and that every single-column foreign key resolves. Any
   mismatch rolls everything back.

Row triggers and FK checks are disabled only during the bulk copy
(`session_replication_role = replica`); the data passed the legacy triggers
when it was written.

## Rehearsal (copies only)

```bash
# 1. Restore a production backup into a scratch database (e.g. tepinta_dev).
# 2. Create and migrate an empty target with this release.
docker exec <container> createdb -U <user> etl_target
DB_DATABASE=etl_target php artisan migrate --force
# 3. Import and read the report.
deploy/cutover/import-legacy-database.sh <container> tepinta_dev etl_target
```

## Production cutover checklist

1. Fresh backup of `tepinta` (`~/tepinta-ops/backup-db.sh`) and its off-site copy.
2. Stop writers: operational app, scheduler and the **public storefront**.
   The deployed public image predates `restaurant_id`; it must be redeployed
   from this release before it writes to the new database.
3. Create and migrate `tepinta_v2`, run the import from `tepinta`.
4. Point the operational and public runtimes at `tepinta_v2`, deploy images
   built from this release, run smoke checks.
5. Keep `tepinta` untouched for rollback until the new release is accepted.

Files under `storage/app` (Instagram assets, management attachments, product
images) are not in the database and stay where they are.
