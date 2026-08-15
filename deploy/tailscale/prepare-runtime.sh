#!/usr/bin/env bash

set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
deployment="$root/deploy/tailscale"

if [[ ! -f "$deployment/.env" ]]; then
    printf 'Missing %s/.env. Copy .env.example and fill secrets locally; never copy the development .env.\n' "$deployment" >&2
    exit 1
fi

compose=(docker compose --env-file "$deployment/.env" --file "$deployment/compose.yaml")
"${compose[@]}" build app
image="$("${compose[@]}" config --images | head -n 1)"

docker run --rm --env-file "$deployment/.env" --entrypoint /bin/bash "$image" -lc '
    set -euo pipefail
    test ! -e public/hot
    test -f public/build/manifest.json
    test -f public/manifest.webmanifest
    php artisan about --only=environment
'

printf 'Prepared %s without starting or replacing any service.\n' "$image"
