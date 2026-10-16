#!/usr/bin/env bash
# Imports the legacy production database into a freshly migrated database of
# this release line. Both databases must live in the same PostgreSQL
# container. The import is a single transaction and verifies every copied
# table (row count and content hash) before committing.
#
# Usage: deploy/cutover/import-legacy-database.sh <container> <source_db> <target_db>
set -euo pipefail

if [[ $# -ne 3 ]]; then
    echo "usage: $0 <container> <source_db> <target_db>" >&2
    exit 64
fi

container=$1
source_db=$2
target_db=$3
here=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)

if [[ $source_db == "$target_db" ]]; then
    echo "source and target must be different databases" >&2
    exit 64
fi

psql_in() {
    local database=$1
    shift
    docker exec -i "$container" sh -c 'psql -X -q -U "$POSTGRES_USER" -d "$0" "$@"' "$database" "$@"
}

read -r sequence_last sequence_called < <(
    psql_in "$source_db" -At -F ' ' -c 'select last_value, is_called from order_public_id_seq'
)

psql_in "$target_db" \
    -v source_db="$source_db" \
    -v order_seq_last="$sequence_last" \
    -v order_seq_called="$sequence_called" \
    < "$here/import-legacy-database.sql"
