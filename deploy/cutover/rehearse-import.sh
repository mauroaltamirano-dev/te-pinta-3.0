#!/usr/bin/env bash
# Rehearses the legacy import end to end against a copy of production and
# asserts its guarantees. Run it right before every cutover.
#
#   1. a fresh target imports with every table, balance, stock and order
#      total reconciled;
#   2. importing again into the populated target is refused and changes nothing;
#   3. a broken source (an order pointing to a missing customer) fails and
#      leaves a fresh target without data or import leftovers.
#
# Usage: MIGRATE_COMMAND='<command that migrates the database named $TARGET_DB>' \
#        deploy/cutover/rehearse-import.sh <container> <source_copy_db>
#
# The source must be a restored copy, never the production database itself.
set -euo pipefail

if [[ $# -ne 2 || -z ${MIGRATE_COMMAND:-} ]]; then
    echo "usage: MIGRATE_COMMAND='...' $0 <container> <source_copy_db>" >&2
    exit 64
fi

container=$1
source_db=$2
here=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
target_db="${source_db}_rehearsal"
broken_db="${source_db}_rehearsal_broken_source"
broken_target_db="${source_db}_rehearsal_broken_target"

psql_in() {
    local database=$1
    shift
    docker exec -i "$container" sh -c 'psql -X -q -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$0" "$@"' "$database" "$@"
}

# db_admin <dropdb|createdb> <database>: names are passed as arguments, never interpolated.
db_admin() {
    if [[ $1 == dropdb ]]; then
        docker exec "$container" sh -c 'dropdb -U "$POSTGRES_USER" --if-exists "$0"' "$2"
    else
        docker exec "$container" sh -c 'createdb -U "$POSTGRES_USER" "$0"' "$2"
    fi
}

fresh_target() {
    db_admin dropdb "$1"
    db_admin createdb "$1"
    TARGET_DB=$1 bash -c "$MIGRATE_COMMAND" > /dev/null
}

snapshot() {
    # pg_dump 18 writes a random \restrict token into every dump; sequences are
    # never rolled back by PostgreSQL and the import sets them explicitly.
    docker exec "$container" sh -c 'pg_dump -U "$POSTGRES_USER" --data-only --exclude-table-data=migrations "$0" 2>/dev/null' "$1" \
        | grep -v -e '^\\\(un\)\?restrict ' -e 'pg_catalog.setval' | md5sum
}

cleanup() {
    for database in "$target_db" "$broken_db" "$broken_target_db"; do
        db_admin dropdb "$database" > /dev/null 2>&1 || true
    done
}
trap cleanup EXIT

fail() {
    echo "REHEARSAL FAILED: $1" >&2
    exit 1
}

echo "1/3 import into a fresh target"
fresh_target "$target_db"
"$here/import-legacy-database.sh" "$container" "$source_db" "$target_db"

echo "2/3 a second import must be refused"
before=$(snapshot "$target_db")
if output=$("$here/import-legacy-database.sh" "$container" "$source_db" "$target_db" 2>&1); then
    fail "the import accepted a populated target"
fi
[[ $output == *"target database already holds data"* ]] || fail "the import failed for another reason: $output"
[[ $(snapshot "$target_db") == "$before" ]] || fail "a refused import changed the target"

echo "3/3 a broken source must roll back completely"
db_admin dropdb "$broken_db"
db_admin createdb "$broken_db"
docker exec "$container" sh -c 'set -e; pg_dump -U "$POSTGRES_USER" -Fc -f /tmp/rehearsal-source.dump "$0"; pg_restore -U "$POSTGRES_USER" --no-owner --exit-on-error -d "$1" /tmp/rehearsal-source.dump; rm -f /tmp/rehearsal-source.dump' "$source_db" "$broken_db"
psql_in "$broken_db" -c "set session_replication_role = replica; delete from customers where id = (select customer_id from orders where customer_id is not null order by id limit 1);"
fresh_target "$broken_target_db"
before=$(snapshot "$broken_target_db")
if output=$("$here/import-legacy-database.sh" "$container" "$broken_db" "$broken_target_db" 2>&1); then
    fail "the import accepted orders without their customer"
fi
[[ $output == *"orphan rows: orders.customer_id"* ]] || fail "the import failed for another reason: $output"
[[ $(snapshot "$broken_target_db") == "$before" ]] || fail "a failed import left data behind"
leftovers=$(psql_in "$broken_target_db" -At -c "select count(*) from pg_namespace where nspname = 'legacy'")
[[ $leftovers == 0 ]] || fail "a failed import left the legacy schema behind"

echo "REHEARSAL PASSED"
