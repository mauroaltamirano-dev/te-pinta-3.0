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
image="$("${compose[@]}" config --images | sort --unique)"

"${compose[@]}" run --rm --no-deps --entrypoint /bin/bash app -lc '
    set -euo pipefail
    test ! -e public/hot
    test -f public/build/manifest.json
    test -f public/manifest.webmanifest
    test -d app/Modules/Instagram
    test -f database/migrations/2026_08_23_210000_create_instagram_story_automation_tables.php
    php artisan about --only=environment
    php artisan route:list --path=api/automation/instagram | grep -q "automation/instagram/stories/pending"
    php artisan route:list --path=media/instagram | grep -q "media/instagram"
    php artisan schedule:list | grep -q "instagram:generate-publications"
'

printf 'Prepared %s without starting or replacing any service.\n' "$image"
