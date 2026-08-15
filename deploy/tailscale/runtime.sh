#!/usr/bin/env bash

set -euo pipefail

deployment="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
environment="$deployment/.env"

if [[ ! -f "$environment" ]]; then
    printf 'Missing %s. Copy .env.example and fill secrets locally.\n' "$environment" >&2
    exit 1
fi

compose=(docker compose --env-file "$environment" --file "$deployment/compose.yaml")

usage() {
    cat <<EOF
Usage: $0 status|start|verify|migrate|stop

  status   Show the isolated operational container state.
  start    Start the already prepared image on its loopback-only port.
  verify   Check health, built assets, and manifest metadata.
  migrate  Run versioned migrations after explicit confirmation.
  stop     Stop only the isolated Te Pinta 3.0 operational container.

start, migrate, and stop require OPERATIONAL_RUNTIME_CONFIRM=yes. This script
does not modify Tailscale Serve or the development .env.
EOF
}

require_confirmation() {
    [[ "${OPERATIONAL_RUNTIME_CONFIRM:-}" == "yes" ]] || {
        printf 'Refusing mutation without OPERATIONAL_RUNTIME_CONFIRM=yes.\n' >&2
        exit 1
    }
}

case "${1:-}" in
    status)
        "${compose[@]}" ps
        ;;
    start)
        require_confirmation
        "${compose[@]}" up --detach --no-build --wait --wait-timeout 60 app
        ;;
    verify)
        "$deployment/verify-runtime.sh"
        ;;
    migrate)
        require_confirmation
        "${compose[@]}" exec app php artisan migrate --force
        ;;
    stop)
        require_confirmation
        "${compose[@]}" stop app
        ;;
    *)
        usage
        exit 2
        ;;
esac
