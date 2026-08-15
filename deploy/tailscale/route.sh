#!/usr/bin/env bash

set -euo pipefail

new_backend="${TEPINTA3_BACKEND:-http://127.0.0.1:8082}"
rollback_backend="${TEPINTA2_BACKEND:-http://127.0.0.1:8080}"

usage() {
    cat <<EOF
Usage: $0 status|cutover|rollback

  status    Read the current Tailscale Serve configuration.
  cutover   Verify Te Pinta 3.0 and route Serve to $new_backend.
  rollback  Route Serve back to $rollback_backend.

cutover and rollback require TAILSCALE_ROUTE_CONFIRM=yes. The script does not
stop either application, modify development environment files, or use Funnel.
EOF
}

case "${1:-}" in
    status)
        tailscale serve status
        ;;
    cutover)
        [[ "${TAILSCALE_ROUTE_CONFIRM:-}" == "yes" ]] || {
            printf 'Refusing cutover without TAILSCALE_ROUTE_CONFIRM=yes.\n' >&2
            exit 1
        }
        TEPINTA3_BACKEND="$new_backend" "$(dirname "$0")/verify-runtime.sh"
        tailscale serve --bg "$new_backend"
        ;;
    rollback)
        [[ "${TAILSCALE_ROUTE_CONFIRM:-}" == "yes" ]] || {
            printf 'Refusing rollback without TAILSCALE_ROUTE_CONFIRM=yes.\n' >&2
            exit 1
        }
        curl --fail --silent --show-error "$rollback_backend" >/dev/null
        tailscale serve --bg "$rollback_backend"
        ;;
    *)
        usage
        exit 2
        ;;
esac
