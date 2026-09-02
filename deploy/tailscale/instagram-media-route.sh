#!/usr/bin/env bash

set -euo pipefail

public_path="/media/instagram"
backend="${INSTAGRAM_MEDIA_BACKEND:-http://127.0.0.1:8082/media/instagram}"

usage() {
    cat <<EOF
Usage: $0 status|cutover

  status    Read the current Tailscale Funnel configuration.
  cutover   Point only $public_path on public HTTPS :8443 to the operational runtime.

cutover requires TAILSCALE_FUNNEL_CONFIRM=yes. It does not modify the private
Tailscale Serve handler, expose /admin or /api, or activate n8n workflows.
EOF
}

case "${1:-}" in
    status)
        tailscale funnel status
        ;;
    cutover)
        [[ "${TAILSCALE_FUNNEL_CONFIRM:-}" == "yes" ]] || {
            printf 'Refusing Funnel cutover without TAILSCALE_FUNNEL_CONFIRM=yes.\n' >&2
            exit 1
        }

        status="$(curl --silent --output /dev/null --write-out '%{http_code}' http://127.0.0.1:8082/api/automation/instagram/stories/pending)"
        [[ "$status" == "401" ]] || {
            printf 'Operational Instagram API preflight returned %s instead of 401.\n' "$status" >&2
            exit 1
        }

        tailscale funnel --yes --bg --https=8443 --set-path="$public_path" "$backend"
        ;;
    *)
        usage
        exit 2
        ;;
esac
